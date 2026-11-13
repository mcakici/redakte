<?php

declare(strict_types=1);

namespace Redakte\Support;

final class NameContext
{
    /** Exclude an imperative idiom only with its grammatical modifier, not a labeled person. */
    public static function isInstructionPhrase(string $source, int $start, int $end): bool
    {
        $value = substr($source, $start, $end - $start);
        if (preg_match('/\Ah[aâ]le\s+get[iİı]r(?:[iİı]n(?:[iİı]z)?)?\z/ui', $value) !== 1) {
            return false;
        }
        $before = substr($source, 0, $start);

        return preg_match('/(?:\A|\s)(?:sağlam|hukuki|uygun|anlaşılır|okunabilir|tutarlı|güçlü|güvenli|hazır|sade|net|düzenli|iyi|bu|aynı)\s+\z/ui', $before) === 1;
    }
}
