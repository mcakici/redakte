<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\MaskStrategy;
use Redakte\RedactionOptions;
use Redakte\Redakte;
use Redakte\Support\Masker;

class MaskStrategyTest extends TestCase
{
    public function test_partial_masking_tckn(): void
    {
        $text = 'TCKN: 43650391326';
        $result = Redakte::partial($text);

        // 436*****326
        $this->assertSame('TCKN: 436*****326', $result);
    }

    public function test_partial_masking_iban(): void
    {
        $text = 'IBAN: TR33 0006 1005 1978 6457 8413 26';
        $result = Redakte::partial($text);

        $this->assertStringContainsString('TR33 **** **** **** **** **** 26', $result);
        $this->assertStringNotContainsString('1978', $result);
    }

    public function test_partial_masking_phone(): void
    {
        $text = 'Telefon: 0532 123 45 67';
        $result = Redakte::partial($text);

        $this->assertStringContainsString('0532 *** ** 67', $result);
        $this->assertStringNotContainsString('123', $result);
    }

    public function test_partial_masking_email(): void
    {
        $text = 'E-posta: mehmet.kaya@example.com';
        $result = Redakte::partial($text);

        $this->assertStringContainsString('m***a@example.com', $result);
        $this->assertStringNotContainsString('mehmet.kaya', $result);
    }

    public function test_partial_masking_name(): void
    {
        $text = 'Davacı Ahmet Yılmaz duruşmaya katıldı.';
        $result = Redakte::partial($text);

        $this->assertStringContainsString('Davacı A**** Y*****', $result);
        $this->assertStringNotContainsString('Ahmet Yılmaz', $result);
    }

    public function test_asterisk_full_mask_strategy(): void
    {
        $text = 'TCKN: 43650391326';
        $options = RedactionOptions::asterisk();
        $result = Redakte::clean($text, $options);

        $this->assertSame('TCKN: ***********', $result);
    }

    public function test_label_mask_strategy(): void
    {
        $text = 'TCKN: 43650391326 ve IBAN: TR33 0006 1005 1978 6457 8413 26';
        $options = RedactionOptions::label();
        $result = Redakte::clean($text, $options);

        $this->assertSame('TCKN: [TCKN] ve IBAN: [IBAN]', $result);
    }

    public function test_masker_support_direct_unit(): void
    {
        $this->assertSame('123*****890', Masker::maskPartial('12345678890', 'TCKN'));
        $this->assertSame('123****890', Masker::maskPartial('1234567890', 'VKN'));
        $this->assertSame('a***t@test.com', Masker::maskPartial('ahmet@test.com', 'EPOSTA'));
    }
}
