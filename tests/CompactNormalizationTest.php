<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\TestCase;
use Redakte\Normalization\NormalizedText;

class CompactNormalizationTest extends TestCase
{
    public function test_compact_maps_match_legacy_coordinates_including_inside_utf8_characters(): void
    {
        $original = "Ş\u{00a0}a\u{200b}’\r\n😀\u{200b}";
        $expected = "Ş a'\n😀";
        $legacy = new NormalizedText($original, $expected,
            [0, 0, 2, 4, 8, 11, 13, 13, 13, 13],
            [2, 2, 4, 5, 11, 13, 17, 17, 17, 17],
        );
        $compact = NormalizedText::create($original);
        self::assertSame($expected, $compact->getNormalizedText());
        for ($start = 0; $start <= strlen($expected); $start++) {
            for ($end = $start; $end <= strlen($expected); $end++) {
                self::assertSame($legacy->mapSpanToOriginal($start, $end), $compact->mapSpanToOriginal($start, $end));
            }
        }
    }
}
