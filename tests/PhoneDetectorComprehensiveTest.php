<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Redakte\Redakte;

class PhoneDetectorComprehensiveTest extends TestCase
{
    #[DataProvider('phoneFormatsProvider')]
    public function test_all_standard_phone_formats(string $phoneText): void
    {
        $text = "Bize {$phoneText} üzerinden ulaşabilirsiniz.";
        $result = Redakte::redact($text);

        $this->assertStringContainsString('[TELEFON_1]', $result->redactedText, "Telefon formatı yakalanamadı: {$phoneText}");
    }

    public static function phoneFormatsProvider(): array
    {
        return [
            ['0532 123 45 67'],
            ['0532-123-45-67'],
            ['(0532) 123 45 67'],
            ['0 (532) 123 45 67'],
            ['+90 532 123 45 67'],
            ['+90 (532) 123-45-67'],
            ['0090 532 123 45 67'],
            ['5321234567'],
            ['0212 555 66 77'],
            ['0 (212) 555-66-77'],
        ];
    }

    public function test_unbalanced_parentheses_are_ignored(): void
    {
        $text = 'Kural (532 123 45 67 şeklinde yazılmamalıdır.';
        $result = Redakte::redact($text);

        // Dengesiz tek açık parantez parantezli telefon olarak yanlış yakalanmamalı
        $this->assertStringNotContainsString('(532 123 45 67', $result->redactedText);
    }
}
