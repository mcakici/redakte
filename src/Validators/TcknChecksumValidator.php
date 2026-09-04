<?php

declare(strict_types=1);

namespace Redakte\Validators;

/**
 * TCKN 11 hane ve checksum algoritması doğrulayıcı
 */
final class TcknChecksumValidator
{
    public function isValid(string $tckn): bool
    {
        $tckn = trim($tckn);

        if (strlen($tckn) !== 11 || !ctype_digit($tckn) || $tckn[0] === '0') {
            return false;
        }

        $digits = array_map('intval', str_split($tckn));

        // 10. hane: ((1+3+5+7+9)*7 - (2+4+6+8)) mod 10
        $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        $check10 = ($odd * 7 - $even) % 10;
        if ($check10 < 0) {
            $check10 += 10;
        }
        if ($digits[9] !== $check10) {
            return false;
        }

        // 11. hane: (ilk 10 rakam toplamı) mod 10
        $sum10 = array_sum(array_slice($digits, 0, 10));
        $check11 = $sum10 % 10;
        if ($digits[10] !== $check11) {
            return false;
        }

        return true;
    }
}
