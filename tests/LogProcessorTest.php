<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Redakte\Laravel\Logging\RedakteLogProcessor;

class LogProcessorTest extends TestCase
{
    public function test_log_processor_redacts_message_and_context(): void
    {
        $processor = new RedakteLogProcessor();

        $logRecord = [
            'message' => 'Kullanıcı giriş yaptı: TCKN 43650391326',
            'context' => [
                'user_id' => 42,
                'phone' => '0532 123 45 67',
                'meta' => [
                    'email' => 'test@example.com',
                ],
            ],
            'level' => 200,
        ];

        $processed = $processor($logRecord);

        $this->assertStringContainsString('TCKN [TCKN_1]', $processed['message']);
        $this->assertStringNotContainsString('43650391326', $processed['message']);

        $this->assertSame(42, $processed['context']['user_id']);
        $this->assertSame('[TELEFON_1]', $processed['context']['phone']);
        $this->assertSame('[EPOSTA_1]', $processed['context']['meta']['email']);
    }
}
