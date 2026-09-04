<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Exception;
use Redakte\Laravel\Logging\RedakteLogProcessor;
use Stringable;

class DummyStringable implements Stringable
{
    public function __construct(private string $val) {}
    public function __toString(): string
    {
        return $this->val;
    }
}

class LogProcessorComprehensiveTest extends TestCase
{
    public function test_log_processor_redacts_extra_and_stringable_and_throwable(): void
    {
        $processor = new RedakteLogProcessor();

        $logRecord = [
            'message' => new DummyStringable('Giriş: TCKN 43650391326'),
            'context' => [
                'exception' => new Exception('Veritabanı hatası: 0532 123 45 67 numarası bulunamadı'),
            ],
            'extra' => [
                'ip' => '192.168.1.1',
                'raw_iban' => 'TR33 0006 1005 1978 6457 8413 26',
            ],
            'level' => 300,
        ];

        $processed = $processor($logRecord);

        // 1. Message (Stringable) redakte edildi mi?
        $this->assertStringContainsString('TCKN [TCKN_1]', $processed['message']);
        $this->assertStringNotContainsString('43650391326', $processed['message']);

        // 2. Exception context redakte edildi mi?
        $this->assertSame('Exception', $processed['context']['exception']['class']);
        $this->assertStringContainsString('[TELEFON_1]', $processed['context']['exception']['message']);
        $this->assertStringNotContainsString('0532 123 45 67', $processed['context']['exception']['message']);

        // 3. Extra alanı redakte edildi mi? (P0-09)
        $this->assertStringContainsString('[IBAN_1]', $processed['extra']['raw_iban']);
        $this->assertStringNotContainsString('TR330006100519786457841326', $processed['extra']['raw_iban']);
    }

    public function test_cyclic_reference_protection(): void
    {
        $processor = new RedakteLogProcessor();

        $a = new \stdClass();
        $b = new \stdClass();
        $a->b = $b;
        $b->a = $a; // Döngüsel referans

        $record = [
            'message' => 'Test log',
            'context' => ['data' => $a],
            'extra' => [],
        ];

        $processed = $processor($record);

        $this->assertIsArray($processed);
        $this->assertSame('Test log', $processed['message']);
    }
}
