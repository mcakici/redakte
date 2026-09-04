<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Redakte\RedakteServiceProvider;

class BladeDirectiveTest extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            RedakteServiceProvider::class,
        ];
    }

    public function test_blade_directives_render(): void
    {
        $rendered = Blade::render('@redakte($val)', ['val' => 'TCKN: 43650391326']);
        $this->assertStringContainsString('TCKN: [TCKN_1]', $rendered);
        $this->assertStringNotContainsString('43650391326', $rendered);

        $renderedPartial = Blade::render('@redaktePartial($val)', ['val' => 'TCKN: 43650391326']);
        $this->assertStringContainsString('TCKN: 436*****326', $renderedPartial);
    }

    public function test_blade_directive_escapes_html_and_prevents_xss(): void
    {
        $payload = '<script>alert("XSS")</script> TCKN: 43650391326';
        $rendered = Blade::render('@redakte($val)', ['val' => $payload]);

        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
        $this->assertStringContainsString('[TCKN_1]', $rendered);
    }
}
