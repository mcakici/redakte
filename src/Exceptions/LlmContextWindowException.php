<?php

declare(strict_types=1);

namespace Redakte\Exceptions;

class LlmContextWindowException extends LlmDetectionException
{
    public static function matches(mixed $error): bool
    {
        return is_string($error) && (bool) preg_match(
            '/(?:context (?:window|length|size).{0,80}(?:exceed|too|full)|(?:exceed|too long|too large).{0,80}context)/i',
            $error,
        );
    }
}
