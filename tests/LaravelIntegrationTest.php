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
}
