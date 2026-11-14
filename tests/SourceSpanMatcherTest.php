<?php

declare(strict_types=1);

namespace Redakte\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redakte\Exceptions\LlmSourceMismatchException;
use Redakte\Llm\SourceSpanMatcher;

final class SourceSpanMatcherTest extends TestCase
{
    public function test_layout_variants_keep_all_original_utf8_byte_spans(): void
    {
        $source = "Şişli: Zelora\n  Tavren; Zelora Tavren; Zelora\tTavren.";
        $matches = (new SourceSpanMatcher())->match($source, ' Zelora Tavren ');
        self::assertCount(3, $matches);
        self::assertSame(["Zelora\n  Tavren", 'Zelora Tavren', "Zelora\tTavren"], array_column($matches, 1));
        foreach ($matches as [$offset, $value]) {
            self::assertSame($value, substr($source, $offset, strlen($value)));
        }
    }

    #[DataProvider('ungroundedValues')]
    public function test_letters_digits_punctuation_and_word_boundaries_are_never_repaired(string $source, string $value): void
    {
        $this->expectException(LlmSourceMismatchException::class);
        (new SourceSpanMatcher())->match($source, $value);
    }

    public static function ungroundedValues(): array
    {
        return [
            ['Alim', 'Ali'], ['ÖAli', 'Ali'], ['91234', '123'],
            ['Zelora Tavren', 'Zelora Tavran'], ['Zelora Tavren', 'zelora tavren'],
            ['123456', '123457'], ['A. Tavren', 'A Tavren'], ['olağan metin', '   '],
        ];
    }

    public function test_large_literal_values_and_regex_metacharacters_do_not_become_regex_patterns(): void
    {
        $value = str_repeat('Zelora[()+.*] ', 5_000).'Tavren';
        $source = 'Başlangıç: '.$value.'.';
        self::assertSame([[strlen('Başlangıç: '), $value]], (new SourceSpanMatcher())->match($source, $value));
    }
}
