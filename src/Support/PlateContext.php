<?php

declare(strict_types=1);

namespace Redakte\Support;

use Redakte\Normalization\NormalizedText;

/** The same plate grammar is used by detection, residual checks and saved previews. */
final class PlateContext
{
    public static function pattern(): string
    {
        $province = '(?:0[1-9]|[1-7]\d|8[01])';
        $body = $province.'[\s-]*[A-Za-z]{1,3}[\s-]*\d{2,4}';
        $start = '(?<![\p{L}\p{N}_])(?<!\d[./])';
        $end = '(?![\p{L}\p{N}_]|[./]\d)';
        // Ordinary conjunctions, units and English dates can fit the SDK plate regex.
        // Explicit plate context still wins, including plates with these letter groups.
        $words = '(?:ve|ile|ila|and|or|to|tl|kg|gr|lt|cm|mm|ml|m|l|may|May)';
        $ordinary = $province.'\s+'.$words.'\s+\d{2,4}'.$end;
        // An unlabelled uppercase group can be a real plate. Only exempt it
        // when the following words explicitly describe articles, ranges or units.
        $ordinaryUpper = $province.'\s+(?i:ve|ile|ila|tl|kg|gr|lt|cm|mm|ml|m|l)\s+\d{2,4}'.$end
            .'(?=\s+(?i:madde(?:leri|ler|si)?|arasında|kuruş|TL|kg|gr|lt|cm|mm|ml)(?!\p{L}))';
        $label = '(?i:plaka(?:sı)?|araç)\s*[:/=-]?\s*';
        $suffix = '(?=\s+(?i:plakalı|plakası|plaka)(?!\p{L}))';

        return '~(?:'.$start.$label.'\K'.$body.$end
            .'|'.$start.$body.$end.$suffix
            .'|'.$start.'(?!'.$ordinary.'|'.$ordinaryUpper.')'.$body.$end.')~u';
    }

    /** @return array<string, true> Accepted byte intervals; calculate once per source. */
    public static function acceptedRanges(string $source): array
    {
        $normalized = preg_match('/[\x{00AD}\x{200B}-\x{200D}\x{FEFF}]|\r/u', $source)
            ? NormalizedText::create($source, true) : null;
        preg_match_all(self::pattern(), $normalized?->getNormalizedText() ?? $source, $matches, PREG_OFFSET_CAPTURE);
        $ranges = [];
        foreach ($matches[0] ?? [] as [$value, $start]) {
            [$start, $end] = $normalized?->mapSpanToOriginal($start, $start + strlen($value)) ?? [$start, $start + strlen($value)];
            $ranges[$start.':'.$end] = true;
        }

        return $ranges;
    }

    public static function isFalsePositive(string $source, int $start, int $end, array $accepted): bool
    {
        if (isset($accepted[$start.':'.$end])) {
            return false;
        }
        $value = substr($source, $start, $end - $start);
        $normalized = NormalizedText::create($value, true)->getNormalizedText();

        // Only correct known classic-pattern mistakes. Do not unmask arbitrary LLM
        // entities (e.g. a phone number or a foreign plate given the wrong type).
        return preg_match('/\A(?:0[1-9]|[1-7]\d|8[01])[\s-]*[A-Za-z]{1,3}[\s-]*\d{2,4}\z/u', $normalized) === 1;
    }
}
