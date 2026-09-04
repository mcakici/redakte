<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Redakte\PatternRegistry;
use Redakte\RedactionService;
use Redakte\RedactionValidator;
use Redakte\Redactor;
use Redakte\Redactors\ModelIdentityRedactor;
use Redakte\Redactors\NameRedactor;
use Redakte\ReportSummaryBuilder;
use Redakte\Validators\IbanMod97Validator;
use Redakte\Validators\TcknChecksumValidator;
use Redakte\Validators\VknChecksumValidator;

abstract class TestCase extends BaseTestCase
{
    protected Redactor $redactor;
    protected RedactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = new PatternRegistry();
        $reportBuilder = new ReportSummaryBuilder($registry);
        $tcknValidator = new TcknChecksumValidator();
        $ibanValidator = new IbanMod97Validator();
        $vknValidator = new VknChecksumValidator();

        $this->service = new RedactionService(
            registry: $registry,
            reportBuilder: $reportBuilder,
            tcknValidator: $tcknValidator,
            ibanValidator: $ibanValidator,
            vknValidator: $vknValidator,
        );

        $nameRedactor = new NameRedactor($registry);
        $validator = new RedactionValidator($registry);
        $modelIdentityRedactor = new ModelIdentityRedactor();

        $this->redactor = new Redactor(
            service: $this->service,
            nameRedactor: $nameRedactor,
            validator: $validator,
            reportBuilder: $reportBuilder,
            modelIdentityRedactor: $modelIdentityRedactor,
        );
    }
}
