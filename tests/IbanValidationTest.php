<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Validators\IbanMod97Validator;

class IbanValidationTest extends TestCase
{
    private IbanMod97Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new IbanMod97Validator();
    }

    public function test_valid_tr_iban(): void
    {
        $this->assertTrue($this->validator->isValid('TR33 0006 1005 1978 6457 8413 26'));
        $this->assertTrue($this->validator->isValid('TR330006100519786457841326'));
    }

    public function test_invalid_tr_iban(): void
    {
        $this->assertFalse($this->validator->isValid('TR99 9999 9999 9999 9999 9999 99'));
        $this->assertFalse($this->validator->isValid('TR00 0000 0000 0000 0000 0000 00'));
    }

    public function test_non_tr_or_short_iban(): void
    {
        $this->assertFalse($this->validator->isValid('DE89 3704 0044 0532 0130 00'));
        $this->assertFalse($this->validator->isValid('TR12345'));
    }
}
