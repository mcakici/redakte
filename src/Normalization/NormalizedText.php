<?php

declare(strict_types=1);

namespace Redakte\Normalization;

use Redakte\Exceptions\InvalidUtf8Exception;

/**
 * Orijinal metin ile normalize edilmiş arama metni arasında koordinat eşleşmesini sağlayan değer nesnesi.
 * Unicode boşlukları (NBSP, em-space), görünmez karakterler (zero-width), akıllı tırnaklar ve CRLF farklarını yönetirken
 * her karakterin ve byte'ın orijinal metindeki koordinatını birebir ve O(1) sürede korur.
 */
final class NormalizedText
{
    /** @var list<int>|string Her byte için başlangıç; create() kompakt little-endian uint32 kullanır. */
    private array|string $normByteToOrigStart = [];

    /** @var list<int>|string Her byte için bitiş; PHP integer dizileri büyüklüğünde bellek ayırmaz. */
    private array|string $normByteToOrigEnd = [];

    /**
     * @param string $originalText Değiştirilmemiş gerçek giriş metni
     * @param string $normalizedText Arama ve tespitte kullanılan normalize metin
     * @param list<int>|string $normByteToOrigStart
     * @param list<int>|string $normByteToOrigEnd
     */
    public function __construct(
        private readonly string $originalText,
        private readonly string $normalizedText,
        array|string $normByteToOrigStart = [],
        array|string $normByteToOrigEnd = [],
    ) {
        $this->normByteToOrigStart = $normByteToOrigStart;
        $this->normByteToOrigEnd = $normByteToOrigEnd;
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
     * Normalize edilmiş metindeki bir byte aralığını orijinal metindeki [startByteOffset, endByteOffset] aralığına O(1) sürede dönüştürür.
     *
     * @return array{0: int, 1: int} [startByteOffset, endByteOffset]
     */
    public function mapSpanToOriginal(int $normByteStart, int $normByteEnd): array
    {
        $normLen = strlen($this->normalizedText);
        $origLen = strlen($this->originalText);

        if ($normLen === 0 || $origLen === 0) {
            return [0, 0];
        }

        $safeStart = max(0, min($normByteStart, $normLen));
        $safeEnd = max($safeStart, min($normByteEnd, $normLen));

        if ($safeStart >= $normLen) {
            $origStart = $origLen;
        } else {
            $origStart = $this->mapValue($this->normByteToOrigStart, $safeStart, 0);
        }

        if ($safeEnd <= 0) {
            $origEnd = 0;
        } elseif ($safeEnd >= $normLen) {
            $origEnd = $origLen;
        } else {
            // $safeEnd indeksi aralığın dışındaki ilk byte'tır; önceki byte'ın orijinal bitişi alınır
            $origEnd = $this->mapValue($this->normByteToOrigEnd, $safeEnd - 1, $origStart);
        }

        $origStart = max(0, min($origStart, $origLen));
        $origEnd = max($origStart, min($origEnd, $origLen));

        return [$origStart, $origEnd];
    }

    private function mapValue(array|string $map, int $index, int $fallback): int
    {
        if (is_array($map)) {
            return $map[$index] ?? $fallback;
        }
        $offset = $index * 4;
        if ($offset < 0 || $offset + 4 > strlen($map)) {
            return $fallback;
        }
        return unpack('Vvalue', $map, $offset)['value'];
    }

    /**
     * Orijinal metinden normalize edilmiş ve indeks haritası çıkarılmış NormalizedText nesnesi üretir.
     */
    public static function create(string $text, bool $strictUtf8 = false): self
    {
        $originalInputText = $text;

        if (!mb_check_encoding($text, 'UTF-8')) {
            if ($strictUtf8) {
                throw new InvalidUtf8Exception('Verilen metin geçerli bir UTF-8 dizisi içermiyor.');
            }
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            $originalInputText = $text;
        }

        $origLen = strlen($text);
        if ($origLen > 0xffffffff) {
            throw new \Redakte\Exceptions\DetectionLimitExceededException('Metin koordinat kapasitesini aştı.');
        }
        $normalizedText = '';
        $normByteToOrigStart = '';
        $normByteToOrigEnd = '';

        $bytePos = 0;

        while ($bytePos < $origLen) {
            $b0 = ord($text[$bytePos]);

            if ($b0 < 0x80) {
                $charByteLen = 1;
                $codePoint = $b0;
                $char = $text[$bytePos];
            } elseif (($b0 & 0xE0) === 0xC0) {
                $charByteLen = 2;
                $char = substr($text, $bytePos, 2);
                $codePoint = mb_ord($char, 'UTF-8');
            } elseif (($b0 & 0xF0) === 0xE0) {
                $charByteLen = 3;
                $char = substr($text, $bytePos, 3);
                $codePoint = mb_ord($char, 'UTF-8');
            } else {
                $charByteLen = 4;
                $char = substr($text, $bytePos, 4);
                $codePoint = mb_ord($char, 'UTF-8');
            }

            $origStart = $bytePos;
            $origEnd = $bytePos + $charByteLen;

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
                $normChar = ' ';
                $bytePos += $charByteLen;
            } elseif (
                // 3. Akıllı apostrof/tek tırnak çeşitleri -> düz tek tırnak ' (0x27)
                $codePoint === 0x2018
                || $codePoint === 0x2019
                || $codePoint === 0x201A
                || $codePoint === 0x201B
                || $codePoint === 0x02BC
            ) {
                $normChar = "'";
                $bytePos += $charByteLen;
            } elseif (
                // 4. Akıllı çift tırnak çeşitleri -> düz çift tırnak " (0x22)
                $codePoint === 0x201C
                || $codePoint === 0x201D
                || $codePoint === 0x201E
                || $codePoint === 0x201F
            ) {
                $normChar = '"';
                $bytePos += $charByteLen;
            } elseif ($char === "\r") {
                // 5. CRLF yönetimi (\r\n -> \n)
                $nextChar = $bytePos + 1 < $origLen ? $text[$bytePos + 1] : '';
                if ($nextChar === "\n") {
                    $normChar = "\n";
                    $origEnd = $bytePos + 2;
                    $bytePos += 2;
                } else {
                    $normChar = "\n";
                    $bytePos += 1;
                }
            } else {
                // Standart karakter
                $normChar = $char;
                $bytePos += $charByteLen;
            }

            $normalizedText .= $normChar;
            $normCharByteLen = strlen($normChar);
            $normByteToOrigStart .= str_repeat(pack('V', $origStart), $normCharByteLen);
            $normByteToOrigEnd .= str_repeat(pack('V', $origEnd), $normCharByteLen);
        }

        return new self($originalInputText, $normalizedText, $normByteToOrigStart, $normByteToOrigEnd);
    }
}
