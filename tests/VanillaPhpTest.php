<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\TestCase;
use Redakte\Redakte;

class VanillaPhpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redakte::setInstance(Redakte::create(['token_format' => 'legacy']));
    }

    protected function tearDown(): void
    {
        Redakte::setInstance(null);
        parent::tearDown();
    }

    public function test_standalone_php_redaction(): void
    {
        $text = "Sayın Ahmet Yılmaz, TCKN: 43650391326 ve IBAN: TR33 0006 1005 1978 6457 8413 26";
        
        // Laravel olmadan doğrudan statik sınıf çağrısı
        $result = Redakte::redact($text);

        $this->assertStringContainsString('Sayın [KISI_1]', $result->redactedText);
        $this->assertStringContainsString('TCKN: [TCKN_1]', $result->redactedText);
        $this->assertStringContainsString('IBAN: [IBAN_1]', $result->redactedText);
        $this->assertStringContainsString('redakte edildi', $result->reportSummary);
    }

    public function test_standalone_clean_method(): void
    {
        $clean = Redakte::clean('Davacı Fatma Kaya');
        $this->assertSame('Davacı [KISI_1]', $clean);
    }

    public function test_custom_config_factory(): void
    {
        // Özel ayarla sıfırdan oluşturma
        $customRedactor = Redakte::create([
            'policy_id' => 'custom_policy',
        ]);

        $result = $customRedactor->redact('TCKN: 43650391326');
        $this->assertSame('custom_policy', $result->policyId);
    }

    public function test_standalone_partial_and_llm_masking(): void
    {
        $text = 'Sn. Ali Veli (TCKN: 43650391326)';

        // Kısmi maskeleme
        $partial = Redakte::partial($text);
        $this->assertStringContainsString('436*****326', $partial);

        // LLM maskeleme ve unmask
        [$masked, $map] = Redakte::maskForLLM($text);
        $this->assertStringContainsString('[TCKN_1]', $masked);

        $unmasked = Redakte::unmask($masked, $map);
        $this->assertSame($text, $unmasked);
    }
}
