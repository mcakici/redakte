<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Policy\RedactionPolicy;
use Redakte\RedactionOptions;
use Redakte\Redakte;

class StrictValidationPolicyTest extends TestCase
{
    public function test_invalid_checksum_with_label_is_masked_in_strict_and_balanced(): void
    {
        // 12345678901 geçersiz checksum'a sahiptir fakat açık bir "TCKN:" etiketine sahiptir
        $text = 'Kişi TCKN: 12345678901 olarak kaydedildi.';

        // 1. Balanced politikada etiket olduğu için maskelenmeli
        $balancedResult = Redakte::redact($text, new RedactionOptions(policy: RedactionPolicy::balanced()));
        $this->assertStringContainsString('TCKN: [TCKN_1]', $balancedResult->redactedText);
        $this->assertStringNotContainsString('12345678901', $balancedResult->redactedText);

        // 2. Strict politikada her halükarda maskelenmeli ve uyarı üretmeli
        $strictResult = Redakte::redact($text, new RedactionOptions(policy: RedactionPolicy::strict()));
        $this->assertStringContainsString('TCKN: [TCKN_1]', $strictResult->redactedText);
        $this->assertStringNotContainsString('12345678901', $strictResult->redactedText);

        // Uyarılar arasında UNVALIDATED_FORMAT_CANDIDATE bulunmalı
        $hasUnvalidatedWarning = false;
        foreach ($strictResult->warnings as $w) {
            $code = is_array($w) ? ($w['code'] ?? '') : (is_object($w) ? $w->code : '');
            if ($code === 'UNVALIDATED_FORMAT_CANDIDATE') {
                $hasUnvalidatedWarning = true;
                break;
            }
        }
        $this->assertTrue($hasUnvalidatedWarning, 'Strict modda geçersiz aday için UNVALIDATED_FORMAT_CANDIDATE uyarısı üretilmedi.');
    }

    public function test_validated_only_policy_does_not_mask_invalid_candidate(): void
    {
        $text = 'Kişi TCKN: 12345678901 olarak kaydedildi.';

        $result = Redakte::redact($text, new RedactionOptions(policy: RedactionPolicy::validatedOnly()));
        // Validated only modunda geçersiz checksum maskelenmemeli
        $this->assertStringContainsString('12345678901', $result->redactedText);
    }
}
