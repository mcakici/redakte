<?php

declare(strict_types=1);

namespace Redakte\Normalization\Canonicalizers;

use Redakte\Contracts\CanonicalizerInterface;

final class IbanCanonicalizer implements CanonicalizerInterface
{
    public function canonicalize(string $value): string
    {
        // Boşlukları ve tireleri kaldır, harfleri büyüt
        return strtoupper(preg_replace('/[\s\-]+/', '', $value) ?? '');
    }
}
