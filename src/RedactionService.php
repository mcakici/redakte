<?php

declare(strict_types=1);

namespace Redakte;

use Redakte\Support\Masker;
use Redakte\Validators\IbanMod97Validator;
use Redakte\Validators\LuhnChecksumValidator;
use Redakte\Validators\TcknChecksumValidator;
use Redakte\Validators\VknChecksumValidator;

/**
 * Çekirdek redaksiyon motoru — Regex desenlerini öncelik sırasına göre uygular,
 * validator'lardan geçirir, çakışmaları eler ve yer tutucuları yerleştirir.
 */
class RedactionService
{
    public function __construct(
        private PatternRegistry $registry,
        private ReportSummaryBuilder $reportBuilder,
        private ?TcknChecksumValidator $tcknValidator = null,
        private ?IbanMod97Validator $ibanValidator = null,
        private ?VknChecksumValidator $vknValidator = null,
        private ?LuhnChecksumValidator $luhnValidator = null,
    ) {
        $this->tcknValidator ??= new TcknChecksumValidator();
        $this->ibanValidator ??= new IbanMod97Validator();
        $this->vknValidator ??= new VknChecksumValidator();
        $this->luhnValidator ??= new LuhnChecksumValidator();
    }

    public function redact(
        string $text,
        ?RedactionOptions $options = null,
        ?RedactionMap $map = null,
    ): RedactionResult {
        $options ??= new RedactionOptions();
        $map ??= new RedactionMap();
        $excludeRanges = $this->computeExcludeRanges($text);
        $spans = [];
        $entityCounters = [];
        /** @var array<string, array<string, int>> entityType => (rawValue => index) */
        $valueToIndex = [];

        foreach ($this->registry->getPatterns() as $pattern) {
            $entityType = $pattern['entity_type'] ?? '';
            if ($options->entityTypes !== null && !in_array($entityType, $options->entityTypes, true)) {
                continue;
            }

            $regex = $pattern['regex'] ?? '';
            if ($regex === '') {
                continue;
            }

            $validator = $pattern['validator'] ?? null;
            $ruleId = $pattern['id'] ?? '';

            if (preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $full = $match[0];
                $value = $full[0];
                $start = $full[1];
                $end = $start + strlen($value);

                if ($this->overlapsExclude($start, $end, $excludeRanges)) {
                    continue;
                }

                if ($this->overlapsExisting($start, $end, $spans)) {
                    continue;
                }

                if ($validator !== null && !$this->validate($value, $validator)) {
                    continue;
                }

                // Aynı değere aynı numara yer tutucu atanması
                $normalizedVal = preg_replace('/\s+/', '', $value) ?? $value;
                if (isset($valueToIndex[$entityType][$normalizedVal])) {
                    $idx = $valueToIndex[$entityType][$normalizedVal];
                } else {
                    $idx = ($entityCounters[$entityType] ?? 0) + 1;
                    $entityCounters[$entityType] = $idx;
                    $valueToIndex[$entityType][$normalizedVal] = $idx;
                }

                $placeholder = Masker::mask($value, $entityType, $options->strategy, $idx);

                $map->add('[' . $entityType . '_' . $idx . ']', $value, $entityType);

                $spans[] = new RedactionSpan(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: $entityType,
                    replacement: $placeholder,
                    confidence: 1.0,
                    source: $ruleId,
                    originalValue: $value,
                );
            }
        }

        $spans = $this->sortSpansByOffset($spans);
        $replacementsByType = $this->countByType($spans);
        $redactedText = $this->applyReplacements($text, $spans);
        $reportSummary = $this->reportBuilder->build($replacementsByType);

        return new RedactionResult(
            spans: $spans,
            redactedText: $redactedText,
            replacementsByType: $replacementsByType,
            reportSummary: $reportSummary,
            policyId: $this->registry->getPolicyId(),
            policyVersion: $this->registry->getPolicyVersion(),
            appliedRules: array_values(array_unique(array_map(fn (RedactionSpan $s) => $s->source, $spans))),
            map: $map,
        );
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function computeExcludeRanges(string $text): array
    {
        $ranges = [];
        foreach ($this->registry->getExcludePatterns() as $regex) {
            if (preg_match_all($regex, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($m as $match) {
                $ranges[] = [$match[0][1], $match[0][1] + strlen($match[0][0])];
            }
        }
        return $ranges;
    }

    private function overlapsExclude(int $start, int $end, array $excludeRanges): bool
    {
        foreach ($excludeRanges as [$s, $e]) {
            if ($start < $e && $end > $s) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<RedactionSpan> $spans
     */
    private function overlapsExisting(int $start, int $end, array $spans): bool
    {
        foreach ($spans as $s) {
            if ($start < $s->endOffset && $end > $s->startOffset) {
                return true;
            }
        }
        return false;
    }

    private function validate(string $value, string $validator): bool
    {
        return match ($validator) {
            'tckn_checksum' => $this->tcknValidator?->isValid($value) ?? true,
            'iban_mod97' => $this->ibanValidator?->isValid($value) ?? true,
            'vkn_checksum' => $this->vknValidator?->isValid($value) ?? true,
            'luhn_checksum' => $this->luhnValidator?->isValid($value) ?? true,
            default => true,
        };
    }

    /**
     * @param list<RedactionSpan> $spans
     * @return list<RedactionSpan>
     */
    private function sortSpansByOffset(array $spans): array
    {
        usort($spans, fn (RedactionSpan $a, RedactionSpan $b) => $a->startOffset <=> $b->startOffset);
        return $spans;
    }

    /**
     * @param list<RedactionSpan> $spans
     * @return array<string, int>
     */
    private function countByType(array $spans): array
    {
        $byType = [];
        foreach ($spans as $s) {
            $byType[$s->entityType] = ($byType[$s->entityType] ?? 0) + 1;
        }
        return $byType;
    }

    /**
     * @param list<RedactionSpan> $spans
     */
    private function applyReplacements(string $text, array $spans): string
    {
        $sorted = $this->sortSpansByOffset($spans);
        // Sondan başa değiştirme yapılarak karakter indeks kaymaları önlenir
        for ($i = count($sorted) - 1; $i >= 0; $i--) {
            $s = $sorted[$i];
            $text = substr_replace($text, $s->replacement, $s->startOffset, $s->length());
        }
        return $text;
    }
}
