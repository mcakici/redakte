<?php

declare(strict_types=1);

namespace Redakte\Token;

final class TokenFactory
{
    private string $sessionId;

    public function __construct(?string $sessionId = null)
    {
        $this->sessionId = $sessionId ?? bin2hex(random_bytes(3));
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Oturuma ve entity türüne özgü çakışmasız yer tutucu üretir.
     */
    public function createToken(string $entityType, int $index, bool $legacyFormat = false): string
    {
        if ($legacyFormat) {
            return '[' . $entityType . '_' . $index . ']';
        }

        return sprintf('⟦RDT:%s:%s:%d⟧', $this->sessionId, $entityType, $index);
    }

    /**
     * Orijinal metinde çakışma olmadığından emin olur, varsa yeni session ID türetir.
     */
    public function ensureNoCollision(string $originalText, string $entityType, int $index, bool $legacyFormat = false): string
    {
        $token = $this->createToken($entityType, $index, $legacyFormat);

        $attempts = 0;
        while (str_contains($originalText, $token) && $attempts < 10) {
            $this->sessionId = bin2hex(random_bytes(3));
            $token = $this->createToken($entityType, $index, $legacyFormat);
            $attempts++;
        }

        return $token;
    }

    /**
     * Verilen metnin geçerli bir RDT token olup olmadığını denetler.
     *
     * @return array{sessionId: string, entityType: string, index: int}|null
     */
    public static function parseToken(string $token): ?array
    {
        if (preg_match('/^⟦RDT:([a-zA-Z0-9_-]+):([a-zA-Z0-9_]+):(\d+)⟧$/u', $token, $m)) {
            return [
                'sessionId' => $m[1],
                'entityType' => $m[2],
                'index' => (int) $m[3],
            ];
        }

        if (preg_match('/^\[([a-zA-Z0-9_]+)_(\d+)\]$/u', $token, $m)) {
            return [
                'sessionId' => '',
                'entityType' => $m[1],
                'index' => (int) $m[2],
            ];
        }

        return null;
    }
}
