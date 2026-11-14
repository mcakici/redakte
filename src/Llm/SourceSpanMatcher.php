<?php

declare(strict_types=1);

namespace Redakte\Llm;

use Redakte\Exceptions\LlmSourceMismatchException;

/** Ground every candidate in source bytes; tolerate layout whitespace only. */
final class SourceSpanMatcher
{
    /** @return list<array{0: int, 1: string}> Byte offset and the actual source value. */
    public function match(string $source, string $value): array
    {
        $value = preg_replace('/\A\s+|\s+\z/u', '', $value) ?? $value;
        if ($value === '') {
            throw new LlmSourceMismatchException();
        }

        // Keep letters, accents, casing, punctuation and digits exact. In particular,
        // never repair a name by fuzzy matching or an identifier by changing digits.
        $parts = preg_split('/\s+/u', $value);
        $first = array_shift($parts);
        $wordStart = preg_match('/\A[\p{L}\p{N}\p{M}_]/u', $value) === 1;
        $wordEnd = preg_match('/[\p{L}\p{N}\p{M}_]\z/u', $value) === 1;
        $matches = [];
        $from = 0;
        // Never compile model output into a regex: even escaped long values can
        // exceed PCRE's compiled-pattern limit. Only fixed boundary rules use PCRE.
        while (($position = strpos($source, $first, $from)) !== false) {
            $cursor = $position + strlen($first);
            $from = $cursor;
            if ($wordStart && preg_match('/\G(?<![\p{L}\p{N}\p{M}_])/u', $source, $unused, 0, $position) !== 1) {
                continue;
            }
            foreach ($parts as $part) {
                if (preg_match('/\G\s+/u', $source, $space, 0, $cursor) !== 1) {
                    continue 2;
                }
                $cursor += strlen($space[0]);
                if (substr($source, $cursor, strlen($part)) !== $part) {
                    continue 2;
                }
                $cursor += strlen($part);
            }
            if ($wordEnd && preg_match('/\G(?![\p{L}\p{N}\p{M}_])/u', $source, $unused, 0, $cursor) !== 1) {
                continue;
            }
            $matches[] = [$position, substr($source, $position, $cursor - $position)];
            $from = $cursor;
        }
        if ($matches === []) {
            throw new LlmSourceMismatchException();
        }

        return $matches;
    }
}
