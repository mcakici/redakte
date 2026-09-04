<?php

declare(strict_types=1);

namespace Redakte\Result;

/**
 * Makine tarafından işlenebilir ve insan tarafından okunabilir uyarı yapısı.
 */
final readonly class RedactionWarning
{
    public function __construct(
        public string $code,
        public ?string $entityType = null,
        public string $severity = 'medium', // 'low' | 'medium' | 'high'
        public string $message = '',
        /** @var array{0: int, 1: int}|null */
        public ?array $span = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'code' => $this->code,
            'entity_type' => $this->entityType,
            'severity' => $this->severity,
            'message' => $this->message,
            'span' => $this->span,
        ];
    }
}
