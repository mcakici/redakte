<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\RedactionOptions;

class RedactorTest extends TestCase
{
    public function test_tckn_redaction(): void
    {
        // 43650391326 geçerli TCKN, 12345678901 geçersiz checksum
        $text = 'Müvekkil TCKN: 43650391326 olup karşı taraf 12345678901 geçersizdir.';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('TCKN: [TCKN_1]', $result->redactedText);
        $this->assertStringContainsString('12345678901', $result->redactedText); // Değişmemeli
    }

    public function test_vkn_redaction(): void
    {
        $text = 'Şirket Vergi No: 1234567890 bildirildi.';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('[VKN_1]', $result->redactedText);
        $this->assertStringNotContainsString('1234567890', $result->redactedText);
    }

    public function test_vkn_does_not_conflict_with_mobile(): void
    {
        // 10 haneli 5xx telefon numarası VKN olarak değil TELEFON olarak redakte edilmeli
        $text = 'İletişim: 5321234567';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('[TELEFON_1]', $result->redactedText);
        $this->assertStringNotContainsString('[VKN_', $result->redactedText);
    }

    public function test_mersis_redaction(): void
    {
        $text = 'MERSİS No: 0123456789012345';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('[MERSIS_1]', $result->redactedText);
    }

    public function test_iban_redaction(): void
    {
        $text = 'Hesap: TR33 0006 1005 1978 6457 8413 26 ve bitişik TR330006100519786457841326';
        $result = $this->redactor->redact($text);

        // İkisi de aynı IBAN olduğu için aynı index almalı
        $this->assertStringContainsString('[IBAN_1]', $result->redactedText);
        $this->assertStringNotContainsString('[IBAN_2]', $result->redactedText);
        $this->assertStringNotContainsString('TR33', $result->redactedText);
    }

    public function test_phone_variants(): void
    {
        $inputs = [
            '0532 123 45 67',
            '+90 532 123 45 67',
            '05321234567',
            '532 123 45 67',
            '+90 312 123 45 67',
            '0 212 555 66 77',
        ];

        foreach ($inputs as $input) {
            $result = $this->redactor->redact("Telefon: {$input}");
            $this->assertStringContainsString('[TELEFON_1]', $result->redactedText, "Telefon yakalanamadı: {$input}");
        }
    }

    public function test_email_and_license_plate(): void
    {
        $text = 'E-posta test.user@example.com ve araç plakası 34 ABC 123';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('[EPOSTA_1]', $result->redactedText);
        $this->assertStringContainsString('[PLAKA_1]', $result->redactedText);
        $this->assertStringNotContainsString('test.user@example.com', $result->redactedText);
        $this->assertStringNotContainsString('34 ABC 123', $result->redactedText);
    }

    public function test_exclude_patterns_preserved(): void
    {
        $text = 'HMK m. 123 ve TCK madde 456 gereğince 123/2 md. uyarınca';
        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('m. 123', $result->redactedText);
        $this->assertStringContainsString('madde 456', $result->redactedText);
        $this->assertStringContainsString('123/2 md.', $result->redactedText);
    }

    public function test_comprehensive_document_redaction(): void
    {
        $text = "Davacı Ayşe Demir (TCKN: 43650391326), Davalı Mehmet Kaya'ya (Tel: 0532 123 45 67) " .
                "karşı dava açmıştır. Kira ödemesi TR33 0006 1005 1978 6457 8413 26 IBAN hesabına yapılacaktır. " .
                "İletişim ayse@example.com adresidir.";

        $result = $this->redactor->redact($text);

        $this->assertStringContainsString('Davacı [KISI_1]', $result->redactedText);
        $this->assertStringContainsString('TCKN: [TCKN_1]', $result->redactedText);
        $this->assertStringContainsString("Davalı [KISI_2]'ya", $result->redactedText);
        $this->assertStringContainsString('Tel: [TELEFON_1]', $result->redactedText);
        $this->assertStringContainsString('[IBAN_1]', $result->redactedText);
        $this->assertStringContainsString('[EPOSTA_1]', $result->redactedText);

        $this->assertStringContainsString('TCKN', $result->reportSummary);
        $this->assertStringContainsString('IBAN', $result->reportSummary);
        $this->assertStringContainsString('telefon', $result->reportSummary);
        $this->assertStringContainsString('e-posta', $result->reportSummary);
        $this->assertStringContainsString('redakte edildi', $result->reportSummary);
    }

    public function test_clean_method_returns_string_only(): void
    {
        $clean = $this->redactor->clean('TCKN: 43650391326');
        $this->assertSame('TCKN: [TCKN_1]', $clean);
    }

    public function test_options_selective_entities(): void
    {
        $text = 'TCKN: 43650391326 ve Tel: 0532 123 45 67';
        // Sadece TCKN redakte edilsin
        $result = $this->redactor->redact($text, RedactionOptions::only(['TCKN']));

        $this->assertStringContainsString('[TCKN_1]', $result->redactedText);
        $this->assertStringContainsString('0532 123 45 67', $result->redactedText); // Telefon değişmemeli
    }
}
