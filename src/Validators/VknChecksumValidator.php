<?php

declare(strict_types=1);

namespace Redakte\Validators;

/**
 * Vergi Kimlik Numarası (VKN) doğrulayıcı.
 * Gelir İdaresi Başkanlığı resmi VKN kontrol algoritmasını destekler.
 */
final class VknChecksumValidator
{
    public function isValid(string $vkn): bool
    {
        $vkn = trim($vkn);

        if (strlen($vkn) !== 10 || !ctype_digit($vkn)) {
            return false;
        }

        // 5 ile başlayan 10 basamaklı numaralar genellikle cep telefonudur (532xxxxxxx gibi)
        // Telefon regex'i daha öncelikli olsa bile VKN kontrolünde ekstra güvenlik
        if ($vkn[0] === '5') {
            return false;
        }

        $digits = array_map('intval', str_split($vkn));
        $sum = 0;

        for ($i = 1; $i <= 9; $i++) {
            $c = ($digits[$i - 1] + 10 - $i) % 10;
            if ($c !== 9) {
                $v = ($c * (2 ** (10 - $i))) % 9;
            } else {
                $v = 9;
            }
            $sum += $v;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $digits[9] === $checkDigit;
    }
}
