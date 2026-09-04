<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Exceptions\InvalidConfigurationException;
use Redakte\PatternRegistry;
use Redakte\Redakte;

class ConfigMergeTest extends TestCase
{
    public function test_partial_config_does_not_wipe_default_patterns(): void
    {
        // Yalnızca policy_id override edildiğinde varsayılan tespitler (TCKN vb.) çalışmaya devam etmeli (P0-03)
        $customRedactor = Redakte::create(['policy_id' => 'custom_policy', 'token_format' => 'legacy']);

        $text = 'Müvekkil TCKN: 43650391326 olup IBAN: TR33 0006 1005 1978 6457 8413 26';
        $result = $customRedactor->redact($text);

        $this->assertSame('custom_policy', $result->policyId);
        $this->assertStringContainsString('[TCKN_1]', $result->redactedText);
        $this->assertStringContainsString('[IBAN_1]', $result->redactedText);
        $this->assertStringNotContainsString('43650391326', $result->redactedText);
    }

    public function test_custom_names_in_name_redaction_config(): void
    {
        // name_redaction.custom_names altındaki özel adlar tespit edilmeli (P0-04)
        $customRedactor = Redakte::create([
            'token_format' => 'legacy',
            'name_redaction' => [
                'custom_names' => ['Zartuk', 'Bortuk'],
            ],
        ]);

        $text = 'Toplantıya Zartuk Demir katıldı.';
        $result = $customRedactor->redact($text);

        $this->assertStringContainsString('[KISI_1]', $result->redactedText);
        $this->assertStringNotContainsString('Zartuk Demir', $result->redactedText);
    }

    public function test_strict_mode_rejects_unknown_config_keys(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new PatternRegistry(['unknown_key' => 'value'], strictMode: true);
    }
}
