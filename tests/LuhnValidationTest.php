<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Redakte;
use Redakte\Validators\LuhnChecksumValidator;

class LuhnValidationTest extends TestCase
{
    private LuhnChecksumValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new LuhnChecksumValidator();
    }

    public function test_valid_credit_card_numbers(): void
    {
        // Standart geçerli test kart numaraları (Luhn algoritmasına uygun)
        $validCards = [
            '4111111111111111',
            '4111 1111 1111 1111',
            '4532 0150 0000 0007',
            '4532015000000007',
        ];

        foreach ($validCards as $card) {
            $this->assertTrue($this->validator->isValid($card), "Kart numarası geçerli olmalı: {$card}");
        }
    }

    public function test_invalid_credit_card_numbers(): void
    {
        $invalidCards = [
            '4111111111111112', // Checksum hatası
            '4532015000000008', // Checksum hatası
            '1234567890123456',
            '123',
            'abcdefghijklmnop',
        ];

        foreach ($invalidCards as $card) {
            $this->assertFalse($this->validator->isValid($card), "Kart numarası geçersiz olmalı: {$card}");
        }
    }

    public function test_credit_card_redaction_in_text(): void
    {
        $text = 'Ödeme yapılan kart: 4111 1111 1111 1111, tutar 150 TL.';
        $result = Redakte::redact($text);

        $this->assertStringContainsString('[KREDI_KARTI_1]', $result->redactedText);
        $this->assertStringNotContainsString('4111 1111 1111 1111', $result->redactedText);
        $this->assertSame(1, $result->replacementsByType['KREDI_KARTI'] ?? 0);
    }
}
