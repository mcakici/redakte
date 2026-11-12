<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redakte\Exceptions\LlmDetectionException;
use Redakte\Llm\LlmOptions;
use Redakte\Llm\NativeHttpTransport;
use Redakte\Redakte;

class LlmHttpIntegrationTest extends TestCase
{
    private static mixed $server = null;
    private static string $url;
    private static string $log;

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('proc_open') || !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            self::markTestSkipped('Yerel HTTP testi için proc_open ve allow_url_fopen gerekir.');
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            self::markTestSkipped('Loopback test portu açılamadı.');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$url = 'http://' . $address;
        self::$log = tempnam(sys_get_temp_dir(), 'redakte-http-');
        self::$server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/Fixtures/ollama-router.php'], [
            0 => ['pipe', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a'],
        ], $pipes, __DIR__, null, ['bypass_shell' => true]);
        if (!is_resource(self::$server)) {
            @unlink(self::$log);
            self::fail('Test HTTP sunucusu başlatılamadı.');
        }
        fclose($pipes[0]);
        $context = stream_context_create(['http' => ['timeout' => 0.1]]);
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            if (@file_get_contents(self::$url . '/health', false, $context) !== false) {
                return;
            }
            usleep(20000);
        }
        self::tearDownAfterClass();
        self::fail('Test HTTP sunucusu hazır olmadı.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        if (isset(self::$log)) {
            @unlink(self::$log);
        }
    }

    public function test_native_transport_posts_json_options_and_authorization_header(): void
    {
        $options = new LlmOptions([
            'endpoint' => self::$url . '/echo', 'model' => 'synthetic',
            'headers' => ['Authorization' => 'Bearer synthetic-key'],
        ]);
        $payload = ['text' => 'şifreli Türkçe', 'options' => ['num_gpu' => 7, 'temperature' => 0.2]];
        $response = (new NativeHttpTransport())->post($payload, $options);
        self::assertSame($payload, $response['payload']);
        self::assertSame('Bearer synthetic-key', $response['authorization']);
    }

    public function test_public_factory_runs_full_hybrid_http_redaction_with_thinking(): void
    {
        $redactor = Redakte::create(['token_format' => 'legacy', 'llm' => [
            'endpoint' => self::$url . '/api/chat', 'model' => 'synthetic',
            'reasoning_effort' => 'high', 'options' => ['num_gpu' => 1],
        ]]);
        $text = 'zelora tavren, TCKN: 43650391326, migren tanısı.';
        $result = $redactor->redact($text);
        self::assertSame('[KISI_1], TCKN: [TCKN_1], [SAGLIK_1].', $result->redactedText);
        self::assertSame($text, $result->unmask($result->redactedText));
        self::assertSame('review_required', $result->status);
        self::assertStringNotContainsString('sensitive-thinking', json_encode($result));
    }

    public function test_consecutive_fast_responses_do_not_trigger_false_windows_socket_timeouts(): void
    {
        $options = new LlmOptions(['endpoint' => self::$url . '/echo', 'model' => 'synthetic']);
        $transport = new NativeHttpTransport();
        for ($i = 0; $i < 12; $i++) {
            self::assertSame(['sequence' => $i], $transport->post(['sequence' => $i], $options)['payload']);
        }
    }

    public function test_http_context_overflow_is_classified_without_exposing_response_body(): void
    {
        $this->expectException(\Redakte\Exceptions\LlmContextWindowException::class);
        (new NativeHttpTransport())->post([], new LlmOptions([
            'endpoint' => self::$url . '/context-error', 'model' => 'synthetic',
        ]));
    }

    public static function failedResponses(): array
    {
        return [
            ['failure', []], ['invalid', []], ['redirect', []],
            ['large', ['max_response_bytes' => 100]], ['slow', ['timeout_seconds' => 0.1]],
        ];
    }

    #[DataProvider('failedResponses')]
    public function test_http_errors_redirects_limits_and_timeouts_fail_safely(string $path, array $extra): void
    {
        $options = new LlmOptions(array_replace([
            'endpoint' => self::$url . '/' . $path, 'model' => 'synthetic',
        ], $extra));
        try {
            (new NativeHttpTransport())->post(['sensitive-input' => 'secret'], $options);
            self::fail('HTTP hatası bekleniyordu.');
        } catch (LlmDetectionException $e) {
            self::assertStringNotContainsString('sensitive-response', $e->getMessage());
            self::assertStringNotContainsString('secret', $e->getMessage());
        }
    }
}
