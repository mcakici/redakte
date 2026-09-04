<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Redakte;

class VknPhoneConflictTest extends TestCase
{
    public function test_vkn_starting_with_five_is_detected(): void
    {
        // P1-05: Eski regex [0-46-9] nedeniyle 5 ile başlayan VKN'leri kaçırıyordu
        $text = 'Şirket Vergi No: 5840294821 evrakta yer aldı.';
        $result = Redakte::redact($text);

        $this->assertStringContainsString('Vergi No: [VKN_1]', $result->redactedText);
        $this->assertStringNotContainsString('5840294821', $result->redactedText);
    }

    public function test_phone_starting_with_five_is_detected_as_phone(): void
    {
        $text = 'İletişim için cep no: 5321234567 arayınız.';
        $result = Redakte::redact($text);

        $this->assertStringContainsString('[TELEFON_1]', $result->redactedText);
        $this->assertStringNotContainsString('[VKN_', $result->redactedText);
    }
}
