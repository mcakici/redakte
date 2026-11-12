<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redakte\Contracts\LlmTransportInterface;
use Redakte\Exceptions\InvalidConfigurationException;
use Redakte\Exceptions\LlmDetectionException;
use Redakte\Llm\LlmOptions;
use Redakte\Llm\OllamaClient;
use Redakte\Llm\OllamaDetector;
use Redakte\PatternRegistry;
use Redakte\RedactionOptions;
use Redakte\RedactionService;
use Redakte\Redactor;
use Redakte\ReportSummaryBuilder;

final class RecordingLlmTransport implements LlmTransportInterface
{
    public array $calls = [];
    public mixed $reply;
    public array $modelInfo = ['thinking' => ['values' => [true, false], 'default' => false]];

    public function __construct()
    {
        $this->reply = ['done' => true, 'done_reason' => 'stop', 'message' => ['content' => '{"entities":[]}']];
    }

    public function post(array $payload, LlmOptions $options, ?string $endpoint = null, ?float $timeoutSeconds = null): array
    {
        $this->calls[] = ['payload' => $payload, 'options' => $options, 'endpoint' => $endpoint ?? $options->endpoint];
        if ($endpoint !== null) {
            return $this->modelInfo;
        }
        return is_callable($this->reply) ? ($this->reply)($payload) : $this->reply;
    }

    public function entities(array $entities): void
    {
        $this->reply = ['done' => true, 'done_reason' => 'stop', 'message' => [
            'content' => json_encode(['entities' => $entities], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'thinking' => 'Hassas düşünme içeriği ASLA sonuca taşınmamalı.',
        ]];
    }
}

class LlmRedactionTest extends TestCase
{
    private function redactor(RecordingLlmTransport $transport, array $config = [], bool $rules = false): Redactor
    {
        $registry = new PatternRegistry(array_replace(['token_format' => 'legacy'], $config));
        $builder = new ReportSummaryBuilder($registry);
        $service = new RedactionService($registry, $builder,
            customDetectors: $rules ? null : [],
            llmDetector: new OllamaDetector($registry, new OllamaClient($transport)),
        );
        return new Redactor($service);
    }

    private function settings(array $extra = []): array
    {
        return array_replace(['endpoint' => 'http://127.0.0.1:11434/api/chat', 'model' => 'test-model'], $extra);
    }

    public function test_default_is_off_and_explicit_false_prevents_all_network_calls(): void
    {
        $transport = new RecordingLlmTransport();
        $redactor = $this->redactor($transport);
        self::assertSame('sıradan metin', $redactor->clean('sıradan metin'));
        $configured = $this->redactor($transport, ['llm' => $this->settings()]);
        self::assertSame('sıradan metin', $configured->clean('sıradan metin', ['llm' => false]));
        self::assertSame([], $transport->calls);
    }

    public function test_endpoint_and_model_auto_enable_and_forward_custom_options_and_prompt(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => 1]]);
        $result = $this->redactor($transport)->redact('başvuran zelora tavren.', ['strict' => true, 'llm' => $this->settings([
            'system_prompt' => 'Özel kurum politikası.',
            'options' => ['num_ctx' => 16384, 'num_predict' => 4096, 'num_gpu' => 12,
                'temperature' => 0.15, 'top_k' => 30, 'custom_future_option' => ['key' => 'value']],
        ])]);
        self::assertSame('başvuran [KISI_1].', $result->redactedText);
        self::assertSame('review_required', $result->status);
        self::assertTrue($result->requiresHumanReview);
        self::assertSame('LLM_SUGGESTION_APPLIED', $result->warnings[0]->code);
        $payload = $transport->calls[0]['payload'];
        self::assertSame(16384, $payload['options']['num_ctx']);
        self::assertSame(12, $payload['options']['num_gpu']);
        self::assertSame(['key' => 'value'], $payload['options']['custom_future_option']);
        self::assertStringStartsWith('Özel kurum politikası.', $payload['messages'][0]['content']);
        self::assertFalse($payload['stream']);
        self::assertFalse($payload['truncate']);
        self::assertArrayNotHasKey('think', $payload);
        self::assertStringNotContainsString('Hassas düşünme', json_encode($result));
        self::assertStringNotContainsString('zelora', json_encode($result));
    }

    public function test_call_settings_merge_without_leaking_into_later_requests(): void
    {
        $transport = new RecordingLlmTransport();
        $redactor = $this->redactor($transport, ['llm' => $this->settings([
            'options' => ['num_ctx' => 16384, 'temperature' => 0.1],
        ])]);
        $redactor->redact('ilk metin', ['llm' => ['options' => ['temperature' => 0.4, 'num_gpu' => 5]]]);
        $redactor->redact('ikinci metin');
        $redactor->redact('üçüncü metin', new RedactionOptions(llm: false));
        self::assertCount(2, $transport->calls);
        self::assertSame(16384, $transport->calls[0]['payload']['options']['num_ctx']);
        self::assertSame(0.4, $transport->calls[0]['payload']['options']['temperature']);
        self::assertSame(0.1, $transport->calls[1]['payload']['options']['temperature']);
        self::assertArrayNotHasKey('num_gpu', $transport->calls[1]['payload']['options']);
        self::assertCount(2, $transport->calls[1]['payload']['messages']);
    }

    public function test_partial_and_reversible_wrappers_preserve_llm_options(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => 1]]);
        $redactor = $this->redactor($transport);
        $options = new RedactionOptions(llm: $this->settings());
        self::assertStringNotContainsString('zelora tavren', $redactor->partial('zelora tavren', $options));
        [$masked, $map] = $redactor->maskForLLM('zelora tavren', $options);
        self::assertSame('[KISI_1]', $masked);
        self::assertSame('zelora tavren', $map->unmask($masked));
        self::assertCount(2, $transport->calls);
    }

    public function test_unicode_normalization_uses_original_byte_coordinates_and_occurrences(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([
            ['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => 1],
            ['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => 2],
        ]);
        $text = "Şişli\u{200b}: zelora\u{00a0}tavren ve zelora tavren.";
        $result = $this->redactor($transport)->redact($text, ['llm' => $this->settings()]);
        self::assertSame("Şişli\u{200b}: [KISI_1] ve [KISI_1].", $result->redactedText);
        self::assertCount(2, $result->spans);
        foreach ($result->spans as $span) {
            self::assertSame($span->originalValue, substr($text, $span->startOffset, $span->length()));
        }
    }

    public function test_hallucinated_text_fails_without_echoing_sensitive_values(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([['type' => 'KISI', 'text' => 'gizli hayali isim', 'occurrence' => 1]]);
        try {
            $this->redactor($transport)->redact('kaynak-PII-örneği', ['llm' => $this->settings()]);
            self::fail('Kaynakta olmayan entity kabul edilmemeli.');
        } catch (LlmDetectionException $e) {
            self::assertStringNotContainsString('gizli', $e->getMessage());
            self::assertStringNotContainsString('kaynak-PII-örneği', $e->getMessage());
        }
    }

    public static function invalidReplies(): array
    {
        return [
            'unfinished' => [['done' => false, 'message' => ['content' => '{"entities":[]}']]],
            'truncated' => [['done' => true, 'done_reason' => 'length', 'message' => ['content' => '{"entities":[]}']]],
            'not json' => [['done' => true, 'message' => ['content' => '<think>reasoning</think>']]],
            'wrong shape' => [['done' => true, 'message' => ['content' => '{"entities":{}}']]],
            'wrong type' => [['done' => true, 'message' => ['content' => '{"entities":[{"type":"UNKNOWN","text":"x","occurrence":1}]}']]],
            'invalid occurrence' => [['done' => true, 'message' => ['content' => '{"entities":[{"type":"KISI","text":"x","occurrence":0}]}']]],
            'upstream error' => [['error' => 'sensitive body']],
        ];
    }

    #[DataProvider('invalidReplies')]
    public function test_invalid_or_incomplete_replies_fail_closed(array $reply): void
    {
        $transport = new RecordingLlmTransport();
        $transport->reply = $reply;
        $this->expectException(LlmDetectionException::class);
        $this->redactor($transport)->redact('x', ['llm' => $this->settings()]);
    }

    public function test_entity_filters_and_disabled_names_apply_to_llm_too(): void
    {
        $transport = new RecordingLlmTransport();
        $redactor = $this->redactor($transport);
        $redactor->redact('zelora tavren', ['names' => false, 'only' => ['KISI'], 'llm' => $this->settings()]);
        self::assertSame([], $transport->calls);
        $redactor->redact('adres metni', ['only' => ['ADRES'], 'llm' => $this->settings()]);
        self::assertSame(['ADRES'], $transport->calls[0]['payload']['format']['properties']['entities']['items']['properties']['type']['enum']);
    }

    public function test_rules_and_checksum_policy_remain_in_control(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => 1]]);
        $result = $this->redactor($transport, rules: true)->redact('zelora tavren TCKN: 43650391326', ['llm' => $this->settings()]);
        self::assertStringContainsString('[KISI_1]', $result->redactedText);
        self::assertStringContainsString('[TCKN_1]', $result->redactedText);
        $transport->entities([['type' => 'TCKN', 'text' => '12345678901', 'occurrence' => 1]]);
        $result = $this->redactor($transport)->redact('12345678901', ['policy' => 'balanced', 'llm' => $this->settings()]);
        self::assertSame('12345678901', $result->redactedText);
        $transport->entities([['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => 1]]);
        $result = $this->redactor($transport)->redact('zelora tavren', ['policy' => 'validated_only', 'llm' => $this->settings()]);
        self::assertSame('zelora tavren', $result->redactedText);
    }

    public function test_wider_llm_detection_is_not_partially_exposed_by_a_shorter_rule(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([['type' => 'SAGLIK', 'text' => 'Ayşe Yılmaz için migren tanısı', 'occurrence' => 1]]);
        $text = 'Dosya: Ayşe Yılmaz için migren tanısı. İnceleme tamamlandı.';
        $result = $this->redactor($transport, rules: true)->redact($text, [
            'policy' => 'strict', 'llm' => $this->settings(),
        ]);
        self::assertSame('Dosya: [SAGLIK_1]. İnceleme tamamlandı.', $result->redactedText);
        self::assertSame($text, $result->unmask($result->redactedText));
        self::assertTrue($result->requiresHumanReview);
    }

    public function test_overlapping_llm_detections_preserve_all_sensitive_bytes_and_round_trip(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([
            ['type' => 'SAGLIK', 'text' => 'zelora tavren için migren', 'occurrence' => 1],
            ['type' => 'SAGLIK', 'text' => 'migren tanısı ve ilaç bilgisi', 'occurrence' => 1],
        ]);
        $text = 'Not: zelora tavren için migren tanısı ve ilaç bilgisi.';
        $result = $this->redactor($transport)->redact($text, ['llm' => $this->settings()]);
        self::assertSame('Not: [SAGLIK_1].', $result->redactedText);
        self::assertSame($text, $result->unmask($result->redactedText));
        self::assertCount(1, $result->spans);
    }

    public function test_coverage_completion_does_not_restore_candidates_rejected_by_policy(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([
            ['type' => 'KISI', 'text' => 'Ayşe Yılmaz için migren tanısı', 'occurrence' => 1],
        ]);
        $text = 'Ayşe Yılmaz için migren tanısı. TCKN: 43650391326';
        $result = $this->redactor($transport, rules: true)->redact($text, [
            'policy' => 'validated_only', 'llm' => $this->settings(),
        ]);
        self::assertStringContainsString('Ayşe Yılmaz için migren tanısı', $result->redactedText);
        self::assertStringContainsString('[TCKN_1]', $result->redactedText);
    }

    public function test_fully_covered_llm_span_keeps_the_more_precise_validated_rule(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([['type' => 'KIMLIK_NO', 'text' => '43650391326', 'occurrence' => 1]]);
        $text = 'TCKN: 43650391326';
        $result = $this->redactor($transport, rules: true)->redact($text, ['llm' => $this->settings()]);
        self::assertSame('TCKN: [TCKN_1]', $result->redactedText);
        self::assertSame($text, $result->unmask($result->redactedText));
    }

    public function test_overlapping_utf8_chunks_are_deduplicated_and_scan_all_text(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->reply = static function (array $payload): array {
            $chunk = json_decode($payload['messages'][1]['content'], true)['text'];
            $entities = [];
            for ($i = 1; $i <= substr_count($chunk, 'zelora'); $i++) {
                $entities[] = ['type' => 'KISI', 'text' => 'zelora', 'occurrence' => $i];
            }
            return ['done' => true, 'message' => ['content' => json_encode(['entities' => $entities])]];
        };
        $text = str_repeat('ş ', 20) . 'zelora ' . str_repeat('ö ', 20) . 'zelora';
        $result = $this->redactor($transport)->redact($text, ['llm' => $this->settings(['chunk_size' => 96, 'chunk_overlap' => 32])]);
        self::assertSame(2, $result->totalReplacements());
        self::assertStringNotContainsString('zelora', $result->redactedText);
        self::assertGreaterThan(1, count($transport->calls));
        foreach ($transport->calls as $call) {
            self::assertTrue(mb_check_encoding($call['payload']['messages'][1]['content'], 'UTF-8'));
        }
    }

    public function test_chunk_limit_is_checked_before_any_request(): void
    {
        $transport = new RecordingLlmTransport();
        try {
            $this->redactor($transport)->redact(str_repeat('x', 200), ['llm' => $this->settings([
                'chunk_size' => 64, 'chunk_overlap' => 0, 'max_chunks' => 1,
            ])]);
            self::fail('Parça sınırı aşılmalı.');
        } catch (LlmDetectionException) {
            self::assertSame([], $transport->calls);
        }
    }

    public static function thinkingAliases(): array
    {
        return [
            [['think' => true], true], [['think' => false], false], [['thinking' => true], true],
            [['enable_thinking' => true], true], [['reasoning_effort' => 'high'], 'high'],
            [['enable_thinking' => true, 'reasoning_effort' => 'medium'], 'medium'],
            [['reasoning_effort' => 'none'], false],
        ];
    }

    #[DataProvider('thinkingAliases')]
    public function test_thinking_aliases_are_normalized_using_model_metadata(array $settings, bool|string $expected): void
    {
        $transport = new RecordingLlmTransport();
        $transport->modelInfo = ['thinking' => ['values' => [true, false, 'low', 'medium', 'high'], 'default' => 'medium']];
        $this->redactor($transport)->redact('metin', ['llm' => $this->settings($settings)]);
        self::assertCount(2, $transport->calls);
        self::assertSame('http://127.0.0.1:11434/api/show', $transport->calls[0]['endpoint']);
        $payload = $transport->calls[1]['payload'];
        self::assertSame($expected, $payload['think']);
        self::assertArrayNotHasKey('reasoning_effort', $payload);
        self::assertArrayNotHasKey('enable_thinking', $payload);
    }

    public function test_named_model_maps_true_to_advertised_default_and_rejects_unsupported_level(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->modelInfo = ['thinking' => ['values' => ['low', 'medium', 'high'], 'default' => 'medium']];
        $redactor = $this->redactor($transport);
        $redactor->redact('metin', ['llm' => $this->settings(['think' => true])]);
        self::assertSame('medium', $transport->calls[1]['payload']['think']);
        $this->expectException(LlmDetectionException::class);
        $redactor->redact('metin', ['llm' => $this->settings(['think' => 'ultra'])]);
    }

    public function test_older_server_allows_boolean_but_does_not_silently_downgrade_named_effort(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->modelInfo = ['capabilities' => ['completion', 'thinking']];
        $redactor = $this->redactor($transport);
        $redactor->redact('metin', ['llm' => $this->settings(['think' => false])]);
        self::assertFalse($transport->calls[1]['payload']['think']);
        $this->expectException(LlmDetectionException::class);
        $redactor->redact('metin', ['llm' => $this->settings(['reasoning_effort' => 'high'])]);
    }

    public static function invalidSettings(): array
    {
        return [
            [['enabled' => true]], [['endpoint' => 'http://localhost/api/chat']],
            [['endpoint' => 'file:///secret', 'model' => 'x']], [['endpoint' => 'http://user:secret@localhost/api/chat', 'model' => 'x']],
            [['think' => 1]], [['think' => false, 'reasoning_effort' => 'high']],
            [['think' => 'high', 'thinking' => 'low']], [['enable_thinking' => 'true']],
            [['options' => ['think' => true]]], [['options' => ['num_ctx' => '8192']]],
            [['options' => ['value' => new \stdClass()]]], [['headers' => ['Authorization' => "Bearer x\r\nInjected: y"]]],
            [['chunk_size' => 64, 'chunk_overlap' => 64]], [['unknown_setting' => true]],
        ];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_configuration_is_rejected(array $settings): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new LlmOptions($settings);
    }

    public function test_per_call_thinking_alias_replaces_global_thinking_group(): void
    {
        $options = LlmOptions::resolve(['think' => true], ['reasoning_effort' => 'high']);
        self::assertSame('high', $options->think);
        $options = LlmOptions::resolve(['reasoning_effort' => 'high'], ['think' => false]);
        self::assertFalse($options->think);
    }

    public function test_excluded_ranges_remain_excluded_after_unicode_normalization(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->entities([['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => 1]]);
        $result = $this->redactor($transport, ['exclude_patterns' => ['/zelora\x{00a0}tavren/u']])
            ->redact("ş\u{200b} zelora\u{00a0}tavren", ['llm' => $this->settings()]);
        self::assertSame(0, $result->totalReplacements());
    }

    public function test_model_without_thinking_rejects_enabling_before_chat(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->modelInfo = ['thinking' => ['values' => [false], 'default' => false]];
        try {
            $this->redactor($transport)->redact('metin', ['llm' => $this->settings(['think' => true])]);
            self::fail('Düşünme desteği olmayan model kabul edilmemeli.');
        } catch (LlmDetectionException) {
            self::assertCount(1, $transport->calls);
        }
    }

    public function test_show_url_derivation_preserves_query_and_supports_explicit_proxy_url(): void
    {
        $transport = new RecordingLlmTransport();
        $client = new OllamaClient($transport);
        $client->inspectModel(new LlmOptions($this->settings(['endpoint' => 'https://localhost/proxy/api/chat?route=/chat'])));
        self::assertSame('https://localhost/proxy/api/show?route=/chat', $transport->calls[0]['endpoint']);
        $client->inspectModel(new LlmOptions($this->settings([
            'endpoint' => 'https://localhost/inference', 'show_endpoint' => 'https://localhost/model-info',
        ])));
        self::assertSame('https://localhost/model-info', $transport->calls[1]['endpoint']);
    }

    public function test_context_window_and_small_safety_margin_automatically_resize_chunks(): void
    {
        $small = new RecordingLlmTransport();
        $large = new RecordingLlmTransport();
        $text = str_repeat('örnek metin içeriği ', 2000);
        $this->redactor($small)->redact($text, ['llm' => $this->settings(['options' => ['num_ctx' => 8192]])]);
        $this->redactor($large)->redact($text, ['llm' => $this->settings(['options' => ['num_ctx' => 16384]])]);
        $smallChunk = json_decode($small->calls[0]['payload']['messages'][1]['content'], true)['text'];
        $largeChunk = json_decode($large->calls[0]['payload']['messages'][1]['content'], true)['text'];
        self::assertGreaterThan(strlen($smallChunk), strlen($largeChunk));
        self::assertLessThan(count($small->calls), count($large->calls));
        self::assertSame(0.05, $small->calls[0]['options']->contextSafetyMargin);
        foreach (array_merge($small->calls, $large->calls) as $call) {
            $p = $call['payload'];
            self::assertTrue((new \Redakte\Llm\ContextBudget())->fits($call['options'],
                $p['messages'][0]['content'], json_decode($p['messages'][1]['content'], true), $p['format']));
        }
    }

    public static function capacityFailures(): array
    {
        return [['context'], ['output']];
    }

    #[DataProvider('capacityFailures')]
    public function test_context_and_output_limits_trigger_smaller_windows_without_losing_tail(string $kind): void
    {
        $transport = new RecordingLlmTransport();
        $transport->reply = static function (array $payload) use ($kind): array {
            $text = json_decode($payload['messages'][1]['content'], true)['text'];
            if (strlen($text) > 600) {
                return $kind === 'context' ? ['error' => 'input length exceeds context window']
                    : ['done' => true, 'done_reason' => 'length'];
            }
            $entities = [];
            for ($i = 1; $i <= substr_count($text, 'zelora'); $i++) {
                $entities[] = ['type' => 'KISI', 'text' => 'zelora', 'occurrence' => $i];
            }
            return ['done' => true, 'message' => ['content' => json_encode(['entities' => $entities])]];
        };
        $text = 'zelora ' . str_repeat('açıklama ', 250) . 'zelora';
        $result = $this->redactor($transport)->redact($text, ['llm' => $this->settings()]);
        self::assertSame(2, $result->totalReplacements());
        self::assertSame(str_replace('zelora', '[KISI_1]', $text), $result->redactedText);
        self::assertGreaterThan(1, count($transport->calls));
    }

    public function test_total_deadline_stops_a_long_multi_chunk_operation(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->reply = static function (): array {
            usleep(30000);
            return ['done' => true, 'message' => ['content' => '{"entities":[]}']];
        };
        $this->expectException(LlmDetectionException::class);
        $this->redactor($transport)->redact('metin', ['llm' => $this->settings(['total_timeout_seconds' => 0.01])]);
    }

    public function test_fifty_pages_are_all_scanned_and_entities_remain_consistent(): void
    {
        $transport = new RecordingLlmTransport();
        $transport->reply = static function (array $payload): array {
            $text = json_decode($payload['messages'][1]['content'], true)['text'];
            $entities = [];
            for ($i = 1; $i <= substr_count($text, 'zelora tavren'); $i++) {
                $entities[] = ['type' => 'KISI', 'text' => 'zelora tavren', 'occurrence' => $i];
            }
            return ['done' => true, 'message' => ['content' => json_encode(['entities' => $entities])]];
        };
        $page = str_repeat('Bu sıradan açıklama metnidir. ', 150) . "zelora tavren TCKN: 43650391326\n";
        $text = str_repeat($page, 50);
        $result = $this->redactor($transport, rules: true)->redact($text, ['llm' => $this->settings()]);
        self::assertSame(50, $result->replacementsByType['KISI']);
        self::assertSame(50, $result->replacementsByType['TCKN']);
        self::assertSame(str_replace(['zelora tavren', '43650391326'], ['[KISI_1]', '[TCKN_1]'], $text), $result->redactedText);
        self::assertSame($text, $result->unmask($result->redactedText));
        self::assertLessThan(512, count($transport->calls));
    }

    public function test_context_cannot_fit_system_and_output_fails_before_network(): void
    {
        $transport = new RecordingLlmTransport();
        try {
            $this->redactor($transport)->redact('metin', ['llm' => $this->settings(['options' => ['num_ctx' => 512]])]);
            self::fail('Context sınırı aşılmalı.');
        } catch (LlmDetectionException) {
            self::assertSame([], $transport->calls);
        }
    }
}
