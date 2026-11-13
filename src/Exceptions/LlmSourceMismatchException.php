<?php

declare(strict_types=1);

namespace Redakte\Exceptions;

/** A model candidate has no literal source span; never attach the private value. */
final class LlmSourceMismatchException extends LlmDetectionException
{
    public function __construct()
    {
        parent::__construct('LLM tarafından önerilen ifade kaynak metinde doğrulanamadı.');
    }
}
