<?php

declare(strict_types=1);

namespace Redakte\Llm;

use Redakte\Contracts\LlmTransportInterface;
use Redakte\Exceptions\LlmDetectionException;
use Redakte\Exceptions\LlmContextWindowException;

/** Ek HTTP paketi gerektirmez; yönlendirmeleri izlemez, TLS doğrulamasını kapatmaz. */
final class NativeHttpTransport implements LlmTransportInterface
{
    public function post(array $payload, LlmOptions $options, ?string $endpoint = null, ?float $timeoutSeconds = null): array
    {
        $timeout = min($options->timeoutSeconds, $timeoutSeconds ?? $options->timeoutSeconds);
        if ($timeout <= 0) {
            throw new LlmDetectionException('LLM işlemi toplam zaman sınırını aştı.');
        }
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            throw new LlmDetectionException('LLM isteği JSON olarak kodlanamadı.');
        }
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Connection: close'];
        foreach ($options->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $json,
                'timeout' => $timeout, 'ignore_errors' => true,
                'follow_location' => 0, 'max_redirects' => 0, 'protocol_version' => 1.1,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $deadline = microtime(true) + $timeout;
        $stream = @fopen($endpoint ?? $options->endpoint ?? '', 'rb', false, $context);
        if ($stream === false) {
            throw new LlmDetectionException('LLM endpoint bağlantısı kurulamadı.');
        }
        try {
            $body = '';
            while (!feof($stream)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw new LlmDetectionException('LLM yanıtı zaman aşımına uğradı.');
                }
                // Windows soketlerinde saniye+kesir kombinasyonu erken timeout üretebilir.
                // Uzun beklemelerde tam saniye; son saniyede mikro saniye kullan.
                $seconds = $remaining >= 1 ? (int) ceil($remaining) : 0;
                $microseconds = $seconds === 0 ? max(1, (int) ($remaining * 1000000)) : 0;
                stream_set_timeout($stream, $seconds, $microseconds);
                $part = @fread($stream, min(8192, $options->maxResponseBytes + 1 - strlen($body)));
                if ($part === false) {
                    throw new LlmDetectionException('LLM yanıtı okunamadı.');
                }
                $body .= $part;
                // Windows HTTP wrapper normal EOF sırasında da timed_out bildirebilir.
                // Sokete yukarıdaki kalan süre uygulanır; gerçek duvar saati esas alınır.
                if (microtime(true) > $deadline) {
                    throw new LlmDetectionException('LLM yanıtı zaman aşımına uğradı.');
                }
                if (strlen($body) > $options->maxResponseBytes) {
                    throw new LlmDetectionException('LLM yanıtı boyut sınırını aştı.');
                }
            }
            $metadata = stream_get_meta_data($stream);
        } finally {
            fclose($stream);
        }
        $status = 0;
        foreach ($metadata['wrapper_data'] ?? [] as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($status < 200 || $status >= 300) {
            $failure = json_decode($body, true);
            if (LlmContextWindowException::matches(is_array($failure) ? ($failure['error'] ?? null) : null)) {
                throw new LlmContextWindowException('LLM context sınırı aşıldı.');
            }
            throw new LlmDetectionException('LLM endpoint başarısız HTTP yanıtı verdi (kod: ' . $status . ').');
        }
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new LlmDetectionException('LLM endpoint geçerli JSON döndürmedi.');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new LlmDetectionException('LLM endpoint yanıtı bir JSON nesnesi olmalıdır.');
        }
        return $decoded;
    }
}
