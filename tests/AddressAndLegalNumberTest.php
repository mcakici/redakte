<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Redakte;

class AddressAndLegalNumberTest extends TestCase
{
    public function test_label_based_address_redaction(): void
    {
        $text = "Tebligat Adresi: Atatürk Mahallesi İnönü Caddesi No: 42 Daire: 5 Çankaya/Ankara";
        $result = Redakte::redact($text);

        $this->assertStringContainsString('Tebligat Adresi: [ADRES_1]', $result->redactedText);
        $this->assertStringNotContainsString('Atatürk Mahallesi', $result->redactedText);
    }

    public function test_legal_numbers_redaction(): void
    {
        $text = "İstanbul 2. Asliye Hukuk Mahkemesi 2023/123 Esas, 2023/456 Karar sayılı ilamı ve Dosya No: 2024/987 incelendi.";
        $result = Redakte::redact($text);

        $this->assertStringContainsString('[ESAS_NO_1]', $result->redactedText);
        $this->assertStringContainsString('[KARAR_NO_1]', $result->redactedText);
        $this->assertStringContainsString('[DOSYA_NO_1]', $result->redactedText);
    }

    public function test_ip_address_redaction(): void
    {
        $text = "Sunucuya 192.168.1.100 adresinden erişim sağlandı.";
        $result = Redakte::redact($text);

        $this->assertStringContainsString('[IP_ADRESI_1]', $result->redactedText);
        $this->assertStringNotContainsString('192.168.1.100', $result->redactedText);
    }
}
