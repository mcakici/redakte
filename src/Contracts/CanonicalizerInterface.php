<?php

declare(strict_types=1);

namespace Redakte\Contracts;

/**
 * Entity değerlerini tutarlı eşleşme için kanonik forma dönüştüren arayüz.
 */
interface CanonicalizerInterface
{
    /**
     * Değeri kanonik temsiline dönüştürür.
     */
    public function canonicalize(string $value): string;
}
