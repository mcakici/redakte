<?php

declare(strict_types=1);

namespace Redakte\Laravel\Logging;

use Monolog\LogRecord;
use Redakte\RedactionOptions;
use Redakte\Redakte;
use Stringable;
use Throwable;

/**
 * Laravel loglarına hassas kişisel verilerin (TCKN, IBAN, Kredi Kartı vb.)
 * sızmasını otomatik önleyen Monolog log işlemcisi (P0-09).
 *
 * message, context ve extra alanlarını kapsar; Stringable, Throwable, iç içe dizileri
 * ve döngüsel nesneleri güvenli biçimde işler.
 */
class RedakteLogProcessor
{
    private const MAX_DEPTH = 10;

    /**
     * @param RedactionOptions|array<string, mixed>|null $options
     * @param bool $failClosed Hata durumunda hassas logu tamamen gizle (varsayılan true)
     */
    public function __construct(
        private RedactionOptions|array|null $options = null,
        private bool $failClosed = true,
    ) {}

    public function __invoke(mixed $record): mixed
    {
        try {
            // Monolog 3 (Laravel 10, 11, 12)
            if (class_exists(LogRecord::class) && $record instanceof LogRecord) {
                $seen = [];
                $redactedMessage = is_string($record->message)
                    ? Redakte::clean($record->message, $this->options)
                    : $this->redactValue($record->message, 0, $seen);

                $redactedContext = $this->redactData($record->context, 0, $seen);
                $redactedExtra = $this->redactData($record->extra, 0, $seen);

                return $record->with(
                    message: is_string($redactedMessage) ? $redactedMessage : (string) $redactedMessage,
                    context: $redactedContext,
                    extra: $redactedExtra,
                );
            }

            // Monolog 2 fallback
            if (is_array($record)) {
                $seen = [];
                if (isset($record['message'])) {
                    $record['message'] = is_string($record['message'])
                        ? Redakte::clean($record['message'], $this->options)
                        : $this->redactValue($record['message'], 0, $seen);
                }
                if (isset($record['context']) && is_array($record['context'])) {
                    $record['context'] = $this->redactData($record['context'], 0, $seen);
                }
                if (isset($record['extra']) && is_array($record['extra'])) {
                    $record['extra'] = $this->redactData($record['extra'], 0, $seen);
                }
                return $record;
            }

            return $record;
        } catch (Throwable $e) {
            if ($this->failClosed) {
                // Güvenlik gereği fail-closed: hassas veriyi açık bırakma
                if (class_exists(LogRecord::class) && $record instanceof LogRecord) {
                    return $record->with(
                        message: '[LOG_REDACTION_ERROR: Hassas veri sızıntısını önlemek için kayıt temizlendi]',
                        context: ['error' => $e->getMessage()],
                        extra: [],
                    );
                }
                if (is_array($record)) {
                    $record['message'] = '[LOG_REDACTION_ERROR: Hassas veri sızıntısını önlemek için kayıt temizlendi]';
                    $record['context'] = ['error' => $e->getMessage()];
                    $record['extra'] = [];
                    return $record;
                }
            }

            return $record;
        }
    }

    /**
     * Dizi verilerini özyinelemeli olarak redakte eder.
     *
     * @param array<mixed, mixed> $data
     * @param int $depth
     * @param array<int, true> $seen
     * @return array<mixed, mixed>
     */
    private function redactData(array $data, int $depth, array &$seen): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['... [Maksimum derinlik aşıldı]'];
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->redactValue($value, $depth + 1, $seen);
        }

        return $data;
    }

    /**
     * Tek bir değeri güvenli biçimde redakte eder.
     */
    private function redactValue(mixed $value, int $depth, array &$seen): mixed
    {
        if (is_string($value)) {
            return Redakte::clean($value, $this->options);
        }

        if (is_array($value)) {
            return $this->redactData($value, $depth, $seen);
        }

        if ($value instanceof Throwable) {
            return [
                'class' => get_class($value),
                'message' => Redakte::clean($value->getMessage(), $this->options),
                'code' => $value->getCode(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        }

        if ($value instanceof Stringable) {
            return Redakte::clean((string) $value, $this->options);
        }

        if (is_object($value)) {
            $oid = spl_object_id($value);
            if (isset($seen[$oid])) {
                return '[Döngüsel Referans Önleme: ' . get_class($value) . ']';
            }
            $seen[$oid] = true;

            // __toString desteği varsa
            if (method_exists($value, '__toString')) {
                try {
                    return Redakte::clean((string) $value, $this->options);
                } catch (Throwable) {
                    return '[' . get_class($value) . ']';
                }
            }

            return '[' . get_class($value) . ']';
        }

        return $value;
    }
}
