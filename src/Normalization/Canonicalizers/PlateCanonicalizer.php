<?php

declare(strict_types=1);

namespace Redakte\Normalization\Canonicalizers;

use Redakte\Contracts\CanonicalizerInterface;

final class PlateCanonicalizer implements CanonicalizerInterface
{
    public function canonicalize(string $value): string
    {
        // Boşluk, nokta ve tireleri kaldırıp harfleri büyüt
        return mb_strtoupper(preg_replace('/[\s\.\-]+/u', '', $value) ?? '', 'UTF-8');
    }
}
