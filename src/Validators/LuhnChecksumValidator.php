<?php

declare(strict_types=1);

namespace Redakte\Validators;

/**
 * Kredi kartı ve banka kartları için Luhn (Mod 10) algoritması doğrulayıcısı
 */
final class LuhnChecksumValidator
{
    public function isValid(string $number): bool
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';
        $len = strlen($digits);

        // Standart kart numaraları 13 ile 19 hane arasındadır
        if ($len < 13 || $len > 19) {
            return false;
        }

        $sum = 0;
        $alternate = false;

        for ($i = $len - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];

            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }

            $sum += $n;
            $alternate = !$alternate;
        }

        return $sum % 10 === 0;
    }
}
