<?php

declare(strict_types=1);

namespace Redakte;

use JsonSerializable;
use Redakte\Exceptions\UnsafeUnmaskException;
use Redakte\Token\TokenFactory;

/**
 * Redakte edilen yer tutucular (token) ile orijinal veriler arasındaki eşleşmeleri
 * tutan ve metni orijinal haline geri döndürmeyi (de-anonymization / unmask) sağlayan sınıf.
 *
 * GÜVENLİK UYARISI:
 * Bu sınıf orijinal kişisel verileri içerir. Yalnızca güvenli bellek alanında tutulmalı,
 * loglara yazdırılmamalı ve kalıcılaştırılacaksa mutlaka şifrelenerek saklanmalıdır.
 */
class RedactionMap implements JsonSerializable
{
    /**
     * @param array<string, string> $tokens [ 'TOKEN' => 'Orijinal Değer' ]
     * @param array<string, string> $types  [ 'TOKEN' => 'ENTITY_TYPE' ]
     * @param string $sessionId Oturum kimliği
     */
    public function __construct(
        private array $tokens = [],
        private array $types = [],
        private string $sessionId = '',
    ) {}

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Yeni bir eşleşme ekler
     */
    public function add(string $token, string $originalValue, string $entityType = ''): void
    {
        $this->tokens[$token] = $originalValue;
        if ($entityType !== '') {
            $this->types[$token] = $entityType;
        }
    }

    /**
     * Belirli bir token'ın orijinal değerini döndürür
     */
    public function get(string $token): ?string
    {
        return $this->tokens[$token] ?? null;
    }

    /**
     * Token'ın entity tipini döndürür
     */
    public function getType(string $token): ?string
    {
        return $this->types[$token] ?? null;
    }

    /**
     * Tüm token => orijinal değer haritasını döndürür
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->tokens;
    }

    /**
     * Kayıtlı token listesi
     *
     * @return list<string>
     */
    public function tokens(): array
    {
        return array_keys($this->tokens);
    }

    /**
     * Toplam eşleşme sayısı
     */
    public function count(): int
    {
        return count($this->tokens);
    }

    /**
     * Haritadaki token'ları hedef metinde orijinal değerleriyle değiştirerek çözer (Unmask).
     *
     * @param string $text Token içeren metin
     * @param string|null $expectedSessionId Belirtilirse, başka session'a ait tokenların çözülmesini engeller
     * @return string Orijinal değerlerine kavuşturulmuş metin
     * @throws UnsafeUnmaskException
     */
    public function unmask(string $text, ?string $expectedSessionId = null): string
    {
        if (empty($this->tokens)) {
            return $text;
        }

        // Çapraz oturum kontrolü
        if ($expectedSessionId !== null && $this->sessionId !== '' && $expectedSessionId !== $this->sessionId) {
            throw new UnsafeUnmaskException(
                sprintf('Oturum uyuşmazlığı tespit edildi: Harita session ID (%s) ile beklenen (%s) eşleşmiyor.', $this->sessionId, $expectedSessionId)
            );
        }

        // strtr en uzun anahtarları önceleyecek şekilde C seviyesinde güvenli eşleştirme yapar
        return strtr($text, $this->tokens);
    }

    /**
     * Güvenli, hassas veri içermeyen metadata özeti
     *
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'count' => $this->count(),
            'session_id' => $this->sessionId,
            'types' => array_values(array_unique(array_values($this->types))),
        ];
    }

    /**
     * Dizi formatında temsil (hassas veri içerir)
     *
     * @return array{tokens: array<string, string>, types: array<string, string>, session_id: string}
     */
    public function toArray(): array
    {
        return [
            'tokens' => $this->tokens,
            'types' => $this->types,
            'session_id' => $this->sessionId,
        ];
    }

    /**
     * JSON serileştirme
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->jsonSerialize(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Diziden RedactionMap nesnesi oluşturur
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $tokens = $data['tokens'] ?? $data;
        $types = $data['types'] ?? [];
        $sessionId = is_string($data['session_id'] ?? null) ? $data['session_id'] : '';

        $cleanTokens = [];
        foreach ($tokens as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $cleanTokens[$k] = $v;
            }
        }

        $cleanTypes = [];
        foreach ($types as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $cleanTypes[$k] = $v;
            }
        }

        return new self($cleanTokens, $cleanTypes, $sessionId);
    }

    /**
     * JSON string'inden nesne oluşturur
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return self::fromArray($decoded);
    }
}
