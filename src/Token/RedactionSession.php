<?php

declare(strict_types=1);

namespace Redakte\Token;

use Redakte\RedactionMap;

/**
 * Çok sayfalı, çok parçalı metinlerde veya bir oturum boyunca
 * aynı veriye aynı tokenın verilmesini sağlayan oturum yöneticisi.
 */
final class RedactionSession
{
    private TokenFactory $tokenFactory;
    private RedactionMap $map;

    /** @var array<string, int> */
    private array $entityCounters = [];

    /** @var array<string, array<string, int>> entityType => (canonicalValue => index) */
    private array $canonicalToIndex = [];

    /** @var array<string, array<string, string>> entityType => (canonicalValue => token) */
    private array $canonicalToToken = [];

    public function __construct(?string $sessionId = null, ?TokenFactory $tokenFactory = null)
    {
        $this->tokenFactory = $tokenFactory ?? new TokenFactory($sessionId);
        $this->map = new RedactionMap([], [], $this->tokenFactory->getSessionId());
    }

    public static function create(?string $sessionId = null): self
    {
        return new self($sessionId);
    }

    public function getSessionId(): string
    {
        return $this->tokenFactory->getSessionId();
    }

    public function getMap(): RedactionMap
    {
        return $this->map;
    }

    /**
     * Entity türü ve kanonik değer için oturum genelinde geçerli token ve index döner.
     *
     * @return array{token: string, index: int}
     */
    public function getOrCreateToken(
        string $entityType,
        string $canonicalValue,
        string $originalValue,
        string $originalTextContext = '',
        bool $legacyFormat = false,
        bool $storeInMap = true,
    ): array {
        if (isset($this->canonicalToToken[$entityType][$canonicalValue])) {
            return [
                'token' => $this->canonicalToToken[$entityType][$canonicalValue],
                'index' => $this->canonicalToIndex[$entityType][$canonicalValue],
            ];
        }

        $idx = ($this->entityCounters[$entityType] ?? 0) + 1;
        $token = $this->tokenFactory->ensureNoCollision($originalTextContext, $entityType, $idx, $legacyFormat);

        $this->entityCounters[$entityType] = $idx;
        $this->canonicalToIndex[$entityType][$canonicalValue] = $idx;
        $this->canonicalToToken[$entityType][$canonicalValue] = $token;

        if ($storeInMap) {
            $this->map->add($token, $originalValue, $entityType);
        }

        return [
            'token' => $token,
            'index' => $idx,
        ];
    }
}
