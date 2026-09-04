<?php

declare(strict_types=1);

namespace Redakte\Normalization\Canonicalizers;

use Redakte\Contracts\CanonicalizerInterface;

final class DigitCanonicalizer implements CanonicalizerInterface
{
    public function canonicalize(string $value): string
    {
        // TCKN, VKN, Kart için yalnızca rakamları çeker
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
