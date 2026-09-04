<?php

declare(strict_types=1);

namespace Redakte\Normalization;

use Normalizer;
use Redakte\Exceptions\InvalidUtf8Exception;

/**
 * Orijinal metin ile normalize edilmiş arama metni arasında koordinat eşleşmesini sağlayan değer nesnesi.
 * Unicode boşlukları, görünmez karakterler, akıllı tırnaklar ve CRLF farklarını yönetirken
 * her karakterin orijinal metindeki byte ofsetini birebir korur.
 */
final class NormalizedText
{
    /** @var list<int> Normalize edilmiş her karakterin orijinal metindeki byte ofseti */
    private array $charToOrigByteMap = [];

    /** @var list<int> Normalize edilmiş her karakterin orijinal metindeki karakter uzunluğu */
    private array $charToOrigByteLen = [];

    public function __construct(
        private readonly string $originalText,
        private readonly string $normalizedText,
        array $charToOrigByteMap,
        array $charToOrigByteLen,
    ) {
        $this->charToOrigByteMap = $charToOrigByteMap;
        $this->charToOrigByteLen = $charToOrigByteLen;
    }

    public function getOriginalText(): string
    {
        return $this->originalText;
    }

    public function getNormalizedText(): string
    {
        return $this->normalizedText;
    }

    /**
     * Normalize edilmiş metindeki bir byte/karakter aralığını orijinal metindeki byte aralığına [start, end] dönüştürür.
     *
     * @return array{0: int, 1: int} [startByteOffset, endByteOffset]
     */
    public function mapSpanToOriginal(int $normByteStart, int $normByteEnd): array
    {
        $normCharStart = mb_strlen(substr($this->normalizedText, 0, $normByteStart), 'UTF-8');
        $normCharLen = mb_strlen(substr($this->normalizedText, $normByteStart, $normByteEnd - $normByteStart), 'UTF-8');
        $normCharEnd = $normCharStart + $normCharLen;

        $mapCount = count($this->charToOrigByteMap);
        if ($mapCount === 0) {
            return [0, 0];
        }

        $safeStartIdx = min($normCharStart, $mapCount - 1);
        $origByteStart = $this->charToOrigByteMap[$safeStartIdx] ?? 0;

        if ($normCharLen <= 0) {
            return [$origByteStart, $origByteStart];
        }

        $lastCharIdx = min($normCharEnd - 1, $mapCount - 1);
        $origByteEnd = ($this->charToOrigByteMap[$lastCharIdx] ?? 0) + ($this->charToOrigByteLen[$lastCharIdx] ?? 0);

        return [$origByteStart, $origByteEnd];
    }

    /**
     * Orijinal metinden normalize edilmiş ve indeks haritası çıkarılmış NormalizedText nesnesi üretir.
     */
    public static function create(string $text, bool $strictUtf8 = false): self
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            if ($strictUtf8) {
                throw new InvalidUtf8Exception('Verilen metin geçerli bir UTF-8 dizisi içermiyor.');
            }
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        if (class_exists(Normalizer::class)) {
            $nfc = Normalizer::normalize($text, Normalizer::FORM_C);
            if ($nfc !== false) {
                $text = $nfc;
            }
        }

        $origLen = strlen($text);
        $normChars = [];
        $charToOrigByteMap = [];
        $charToOrigByteLen = [];

        $bytePos = 0;
        while ($bytePos < $origLen) {
            // Mevcut UTF-8 karakterini oku
            $char = mb_substr(substr($text, $bytePos), 0, 1, 'UTF-8');
            $charByteLen = strlen($char);
            if ($charByteLen === 0) {
                break;
            }

            $codePoint = mb_ord($char, 'UTF-8');

            // 1. Görünmez ve yumuşak tire karakterleri (zero-width space, non-joiner, soft hyphen vb.)
            // Bu karakterler arama metninde boşaltılır ama orijinal yerleri korunur
            if (
                $codePoint === 0x00AD // soft hyphen
                || $codePoint === 0x200B // zero-width space
                || $codePoint === 0x200C // zero-width non-joiner
                || $codePoint === 0x200D // zero-width joiner
                || $codePoint === 0xFEFF // zero-width non-breaking space / BOM
            ) {
                $bytePos += $charByteLen;
                continue;
            }

            // 2. Unicode boşluk çeşitleri -> normal boşluk ' ' (0x20)
            if (
                $codePoint === 0x00A0 // NBSP
                || $codePoint === 0x202F // Narrow NBSP
                || ($codePoint >= 0x2000 && $codePoint <= 0x200A) // En/Em spaces
                || $codePoint === 0x3000 // Ideographic space
            ) {
                $normChars[] = ' ';
                $charToOrigByteMap[] = $bytePos;
                $charToOrigByteLen[] = $charByteLen;
                $bytePos += $charByteLen;
                continue;
            }

            // 3. Akıllı apostrof/tek tırnak çeşitleri -> düz tek tırnak ' (0x27)
            if (
                $codePoint === 0x2018
                || $codePoint === 0x2019
                || $codePoint === 0x201A
                || $codePoint === 0x201B
                || $codePoint === 0x02BC
            ) {
                $normChars[] = "'";
                $charToOrigByteMap[] = $bytePos;
                $charToOrigByteLen[] = $charByteLen;
                $bytePos += $charByteLen;
                continue;
            }

            // 4. Akıllı çift tırnak çeşitleri -> düz çift tırnak " (0x22)
            if (
                $codePoint === 0x201C
                || $codePoint === 0x201D
                || $codePoint === 0x201E
                || $codePoint === 0x201F
            ) {
                $normChars[] = '"';
                $charToOrigByteMap[] = $bytePos;
                $charToOrigByteLen[] = $charByteLen;
                $bytePos += $charByteLen;
                continue;
            }

            // 5. CRLF yönetimi (\r\n -> \n)
            if ($char === "\r") {
                $nextChar = $bytePos + 1 < $origLen ? $text[$bytePos + 1] : '';
                if ($nextChar === "\n") {
                    $normChars[] = "\n";
                    $charToOrigByteMap[] = $bytePos;
                    $charToOrigByteLen[] = 2;
                    $bytePos += 2;
                    continue;
                }
                $normChars[] = "\n";
                $charToOrigByteMap[] = $bytePos;
                $charToOrigByteLen[] = 1;
                $bytePos += 1;
                continue;
            }

            // Standart karakter
            $normChars[] = $char;
            $charToOrigByteMap[] = $bytePos;
            $charToOrigByteLen[] = $charByteLen;
            $bytePos += $charByteLen;
        }

        $normalizedText = implode('', $normChars);

        return new self($text, $normalizedText, $charToOrigByteMap, $charToOrigByteLen);
    }
}
