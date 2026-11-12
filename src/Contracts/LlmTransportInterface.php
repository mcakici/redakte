<?php

declare(strict_types=1);

namespace Redakte\Contracts;

use Redakte\Llm\LlmOptions;

interface LlmTransportInterface
{
    /** @return array<string, mixed> Ollama JSON yanıtı */
    public function post(array $payload, LlmOptions $options, ?string $endpoint = null, ?float $timeoutSeconds = null): array;
}
