<?php

declare(strict_types=1);

namespace Redakte\Contracts;

/**
 * Checksum ve biçim doğrulama arayüzü.
 */
interface ValidatorInterface
{
    /**
     * Değerin geçerli olup olmadığını doğrular.
     */
    public function isValid(string $value): bool;
}
