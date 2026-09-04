<?php

declare(strict_types=1);

namespace Redakte\Normalization\Canonicalizers;

use Redakte\Contracts\CanonicalizerInterface;

final class PhoneCanonicalizer implements CanonicalizerInterface
{
    public function canonicalize(string $value): string
    {
        // Yalnızca rakamları ve baştaki + işaretini al
        $digits = preg_replace('/[^\d+]/', '', $value) ?? '';

        if (str_starts_with($digits, '+90')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0090')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        return '+90' . $digits;
    }
}
