<?php

declare(strict_types=1);

namespace Redakte\Llm;

use Redakte\Exceptions\LlmDetectionException;

/** Token sayımı değildir: model tokenizer'ı olmadığında muhafazakâr bir başlangıç tahmini. */
final class ContextBudget
{
    public function chunkBytes(LlmOptions $options, string $system, array $emptyInput, array $schema): int
    {
        $encode = static fn (array $value): string => json_encode($value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $overhead = $this->estimate($system, $options)
            + $this->estimate($encode($emptyInput), $options)
            + $this->estimate($encode($schema), $options)
            + 128; // Model chat şablonu ve özel tokenlar için pay.
        $output = $options->options['num_predict'] > 0 ? $options->options['num_predict'] : $options->outputTokenReserve;
        $available = (int) floor($options->options['num_ctx'] * (1 - $options->contextSafetyMargin)) - $overhead - $output;
        $bytes = (int) floor($available * $options->estimatedBytesPerToken);
        if ($bytes < 64) {
            throw new LlmDetectionException('Context bütçesi sistem talimatı ve çıktı rezervinden sonra metne yetmiyor; num_ctx artırın veya num_predict/talimatı küçültün.');
        }
        return $options->chunkSize === null ? $bytes : min($bytes, $options->chunkSize);
    }

    private function estimate(string $text, LlmOptions $options): int
    {
        return (int) ceil(strlen($text) / $options->estimatedBytesPerToken);
    }

    /** JSON kaçışları da bütçeye dahildir; satır sonu yoğun metni küçültmek gerekebilir. */
    public function fits(LlmOptions $options, string $system, array $input, array $schema): bool
    {
        $encode = static fn (array $value): string => json_encode($value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $output = $options->options['num_predict'] > 0 ? $options->options['num_predict'] : $options->outputTokenReserve;
        return $this->estimate($system, $options) + $this->estimate($encode($input), $options)
            + $this->estimate($encode($schema), $options) + 128 + $output
            <= (int) floor($options->options['num_ctx'] * (1 - $options->contextSafetyMargin));
    }
}
