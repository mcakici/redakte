<?php

declare(strict_types=1);

namespace Redakte;

use JsonSerializable;

/**
 * Redakte edilen yer tutucular (token) ile orijinal veriler arasındaki eşleşmeleri
 * tutan ve metni orijinal haline geri döndürmeyi (de-anonymization / unmask) sağlayan sınıf.
 *
 * LLM / AI entegrasyonlarında prompt gönderilmeden önce anonimleştirme ve
 * AI cevabındaki etiketleri tekrar gerçek değerlerle takas etme için kullanılır.
 */
class RedactionMap implements JsonSerializable
{
    /**
     * @param array<string, string> $tokens [ '[TOKEN]' => 'Orijinal Değer' ]
     * @param array<string, string> $types  [ '[TOKEN]' => 'ENTITY_TYPE' ]
     */
    public function __construct(
        private array $tokens = [],
        private array $types = [],
    ) {}

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
     * Haritadaki token'ları hedef metinde orijinal değerleriyle değiştirerek çözer (Unmask / De-anonymize).
     *
     * @param string $text Token içeren metin (örneğin LLM'den dönen yanıt)
     * @return string Orijinal değerlerine kavuşturulmuş metin
     */
    public function unmask(string $text): string
    {
        if (empty($this->tokens)) {
            return $text;
        }

        // strtr PHP'de en uzun eşleşmeyi önceleyecek şekilde optimize çalışır
        return strtr($text, $this->tokens);
    }

    /**
     * Dizi formatında temsil
     *
     * @return array{tokens: array<string, string>, types: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'tokens' => $this->tokens,
            'types' => $this->types,
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

        return new self($cleanTokens, $cleanTypes);
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
