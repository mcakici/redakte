<?php

declare(strict_types=1);

namespace Redakte\Contracts;

use Redakte\MaskStrategy;

/**
 * Redaksiyon maskeleme arayüzü.
 */
interface MaskerInterface
{
    /**
     * Değeri belirlenen strateji ve indekse göre maskeler.
     */
    public function mask(
        string $value,
        string $entityType,
        MaskStrategy $strategy,
        int $index = 1,
        ?string $token = null,
    ): string;
}
