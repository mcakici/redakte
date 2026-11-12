<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Redakte\Facades\Redakte;
use Redakte\Redactor;
use Redakte\RedakteServiceProvider;

class LaravelIntegrationTest extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            RedakteServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Redakte' => Redakte::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('redakte.token_format', 'legacy');
    }

    public function test_service_provider_registers_singleton(): void
    {
        $this->assertInstanceOf(Redactor::class, $this->app->make(Redactor::class));
        $this->assertInstanceOf(Redactor::class, $this->app->make('redakte'));
    }

    public function test_facade_resolves_and_redacts(): void
    {
        $text = 'Davacı Ali Veli (TCKN: 43650391326)';
        $result = Redakte::redact($text);

        $this->assertStringContainsString('Davacı [KISI_1]', $result->redactedText);
        $this->assertStringContainsString('TCKN: [TCKN_1]', $result->redactedText);
        $this->assertStringNotContainsString('43650391326', $result->redactedText);
    }

    public function test_clean_facade_method(): void
    {
        $clean = Redakte::clean('Tel: 0532 123 45 67');
        $this->assertSame('Tel: [TELEFON_1]', $clean);
    }

    public function test_config_is_loaded(): void
    {
        $this->assertSame('legal_tr_v1', config('redakte.policy_id'));
    }

    public function test_facade_partial_and_llm_methods(): void
    {
        $partial = Redakte::partial('TCKN: 43650391326');
        $this->assertSame('TCKN: 436*****326', $partial);

        [$masked, $map] = Redakte::maskForLLM('TCKN: 43650391326');
        $this->assertSame('TCKN: [TCKN_1]', $masked);

        $restored = Redakte::unmask('Onaylanan TCKN: [TCKN_1]', $map);
        $this->assertSame('Onaylanan TCKN: 43650391326', $restored);
    }

    public function test_llm_configuration_and_transport_are_resolved_from_container(): void
    {
        $previous = $this->app['config']->get('redakte.llm');
        try {
            $this->app['config']->set('redakte.llm', [
                'endpoint' => 'http://localhost:11434/api/chat', 'model' => 'synthetic',
            ]);
            $transport = new class implements \Redakte\Contracts\LlmTransportInterface {
                public array $calls = [];
                public function post(array $payload, \Redakte\Llm\LlmOptions $options, ?string $endpoint = null, ?float $timeoutSeconds = null): array
                {
                    $this->calls[] = $payload;
                    return ['done' => true, 'message' => ['content' => '{"entities":[{"type":"KISI","text":"zelora tavren","occurrence":1}]}']];
                }
            };
            $this->app->instance(\Redakte\Contracts\LlmTransportInterface::class, $transport);
            $result = Redakte::redact('zelora tavren');
            $this->assertSame('[KISI_1]', $result->redactedText);
            $this->assertCount(1, $transport->calls);
            Redakte::redact('zelora tavren', ['llm' => false]);
            $this->assertCount(1, $transport->calls);
        } finally {
            $this->app['config']->set('redakte.llm', $previous);
        }
    }
}
