<?php

declare(strict_types=1);

namespace Redakte\Normalization\Canonicalizers;

use Redakte\Contracts\CanonicalizerInterface;

final class NameCanonicalizer implements CanonicalizerInterface
{
    public function canonicalize(string $value): string
    {
        // Tekrarlı boşlukları teke indir, Türkçe harflerle küçük harfe çevir
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        return mb_strtolower($trimmed, 'UTF-8');
    }
}
