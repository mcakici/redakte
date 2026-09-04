<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Validators\TcknChecksumValidator;

class TcknValidationTest extends TestCase
{
    private TcknChecksumValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TcknChecksumValidator();
    }

    public function test_valid_tckn_passes(): void
    {
        // 43650391326 geçerli bir TCKN algoritma çıktısıdır
        $this->assertTrue($this->validator->isValid('43650391326'));
    }

    public function test_invalid_checksum_fails(): void
    {
        $this->assertFalse($this->validator->isValid('12345678901'));
        $this->assertFalse($this->validator->isValid('11111111111'));
    }

    public function test_invalid_length_or_characters(): void
    {
        $this->assertFalse($this->validator->isValid('1234567890'));
        $this->assertFalse($this->validator->isValid('123456789012'));
        $this->assertFalse($this->validator->isValid('4365039132A'));
        $this->assertFalse($this->validator->isValid('03650391326')); // 0 ile başlayamaz
    }
}
