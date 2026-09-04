<?php

declare(strict_types=1);

namespace Redakte\Laravel\Logging;

use Monolog\LogRecord;
use Redakte\RedactionOptions;
use Redakte\Redakte;

/**
 * Laravel loglarına hassas kişisel verilerin (TCKN, IBAN, Kredi Kartı vb.)
 * sızmasını otomatik önleyen Monolog log işlemcisi.
 *
 * config/logging.php içerisindeki kanallara eklenebilir:
 * 'processors' => [\Redakte\Laravel\Logging\RedakteLogProcessor::class],
 */
class RedakteLogProcessor
{
    public function __construct(
        private RedactionOptions|array|null $options = null,
    ) {}

    public function __invoke(mixed $record): mixed
    {
        // Monolog 3 (Laravel 10, 11, 12)
        if (class_exists(LogRecord::class) && $record instanceof LogRecord) {
            $redactedMessage = is_string($record->message)
                ? Redakte::clean($record->message, $this->options)
                : $record->message;

            $redactedContext = $this->redactData($record->context);

            return $record->with(
                message: $redactedMessage,
                context: $redactedContext,
            );
        }

        // Monolog 2 fallback
        if (is_array($record)) {
            if (isset($record['message']) && is_string($record['message'])) {
                $record['message'] = Redakte::clean($record['message'], $this->options);
            }
            if (isset($record['context']) && is_array($record['context'])) {
                $record['context'] = $this->redactData($record['context']);
            }
            return $record;
        }

        return $record;
    }

    /**
     * Context içindeki string ve dizi değerlerini özyinelemeli olarak redakte eder
     */
    private function redactData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = Redakte::clean($value, $this->options);
            } elseif (is_array($value)) {
                $data[$key] = $this->redactData($value);
            }
        }

        return $data;
    }
}
