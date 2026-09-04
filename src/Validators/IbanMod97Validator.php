<?php

declare(strict_types=1);

namespace Redakte\Validators;

/**
 * IBAN ISO 7064 Mod97 algoritması doğrulayıcı (TR IBAN)
 */
final class IbanMod97Validator
{
    public function isValid(string $iban): bool
    {
        $normalized = preg_replace('/\s+/', '', strtoupper(trim($iban)));
        if ($normalized === null || strlen($normalized) < 15) {
            return false;
        }

        // Türkiye IBAN standartları: TR ile başlar ve 26 karakterdir
        if (!str_starts_with($normalized, 'TR') || strlen($normalized) !== 26) {
            return false;
        }

        // İlk 4 karakteri sona al
        $rearranged = substr($normalized, 4) . substr($normalized, 0, 4);
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $c = $rearranged[$i];
            if (ctype_alpha($c)) {
                $numeric .= (string) (ord($c) - ord('A') + 10);
            } else {
                $numeric .= $c;
            }
        }

        // Büyük sayılar için mod97 hesaplama
        $remainder = 0;
        $len = strlen($numeric);
        for ($i = 0; $i < $len; $i++) {
            $remainder = ($remainder * 10 + (int) $numeric[$i]) % 97;
        }

        return $remainder === 1;
    }
}
