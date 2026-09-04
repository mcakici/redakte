<?php

declare(strict_types=1);

namespace Redakte\Detection;

use Redakte\Normalization\NormalizedText;
use Redakte\Policy\RedactionPolicy;
use Redakte\RedactionOptions;

final class DetectionContext
{
    /**
     * @param list<array{0: int, 1: int}> $excludeRanges
     * @param array<string, true> $customNames
     */
    public function __construct(
        public readonly string $originalText,
        public readonly NormalizedText $normalizedText,
        public readonly RedactionOptions $options,
        public readonly RedactionPolicy $policy,
        public readonly array $excludeRanges = [],
        public readonly array $customNames = [],
    ) {}

    /**
     * Verilen byte aralığının hariç tutulan negatif aralıklarla çakışıp çakışmadığını denetler.
     */
    public function overlapsExclude(int $start, int $end): bool
    {
        foreach ($this->excludeRanges as [$s, $e]) {
            if ($start < $e && $end > $s) {
                return true;
            }
        }
        return false;
    }

    /**
     * Belirtilen entity türünün taranması istenip istenmediğini denetler.
     */
    public function isEntityAllowed(string $entityType): bool
    {
        if ($this->options->entityTypes === null) {
            return true;
        }

        return in_array($entityType, $this->options->entityTypes, true);
    }
}
