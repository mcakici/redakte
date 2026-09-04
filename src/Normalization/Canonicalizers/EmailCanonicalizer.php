<?php

declare(strict_types=1);

namespace Redakte\Normalization\Canonicalizers;

use Redakte\Contracts\CanonicalizerInterface;

final class EmailCanonicalizer implements CanonicalizerInterface
{
    public function canonicalize(string $value): string
    {
        $trimmed = trim($value);
        $parts = explode('@', $trimmed, 2);
        if (count($parts) !== 2) {
            return mb_strtolower($trimmed, 'UTF-8');
        }

        // Domain kısmını küçük harfe çevir, yerel kısmı koru
        return $parts[0] . '@' . mb_strtolower($parts[1], 'UTF-8');
    }
}
