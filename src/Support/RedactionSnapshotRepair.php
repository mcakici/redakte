<?php

declare(strict_types=1);

namespace Redakte\Support;

/** Deterministic, occurrence-specific corrections without re-running the LLM. */
final class RedactionSnapshotRepair
{
    public static function apply(array $snapshot, ?string $source = null): array
    {
        if (!is_string($snapshot['text'] ?? null) || !is_array($snapshot['map'] ?? null)
            || !is_array($snapshot['types'] ?? null) || !is_array($snapshot['spans'] ?? null)) {
            return $snapshot;
        }
        foreach ($snapshot['map'] as $token => $value) {
            if (!is_string($token) || $token === '' || !is_string($value)) {
                return $snapshot;
            }
        }
        $source ??= strtr($snapshot['text'] ?? '', $snapshot['map'] ?? []);
        if (!mb_check_encoding($source, 'UTF-8') || ($snapshot['hash'] ?? null) !== hash('sha256', $source)) {
            return $snapshot;
        }
        // A malformed saved interval must never cause an otherwise masked value
        // to be restored while rebuilding another corrected occurrence.
        foreach ($snapshot['spans'] as $span) {
            if (!is_array($span) || !is_int($span['start'] ?? null) || !is_int($span['end'] ?? null)
                || $span['start'] < 0 || $span['end'] <= $span['start'] || $span['end'] > strlen($source)
                || !is_string($span['token'] ?? null) || !array_key_exists($span['token'], $snapshot['map'])) {
                return $snapshot;
            }
        }
        $accepted = null;
        $kept = [];
        $changed = false;
        foreach ($snapshot['spans'] as $span) {
            $type = $snapshot['types'][$span['token'] ?? ''] ?? '';
            $mistake = $type === 'KISI' && NameContext::isInstructionPhrase($source, $span['start'], $span['end']);
            if ($type === 'PLAKA') {
                $accepted ??= PlateContext::acceptedRanges($source);
                $mistake = PlateContext::isFalsePositive($source, $span['start'], $span['end'], $accepted);
            }
            if ($mistake) {
                $changed = true;
            } else {
                $kept[] = $span;
            }
        }
        if (! $changed) {
            return $snapshot;
        }
        // Validate complete coverage only when a correction is actually needed.
        $ordered = $snapshot['spans'];
        usort($ordered, static fn ($a, $b) => $a['start'] <=> $b['start']);
        $previousEnd = 0;
        foreach ($ordered as $span) {
            if ($span['start'] < $previousEnd) {
                return $snapshot;
            }
            $previousEnd = $span['end'];
        }
        if (self::render($source, $ordered) !== $snapshot['text']) {
            return $snapshot;
        }
        // Equal values can share a token; restore only the mistaken occurrence.
        usort($kept, static fn ($a, $b) => $a['start'] <=> $b['start']);
        $snapshot['text'] = self::render($source, $kept);
        $tokens = array_fill_keys(array_column($kept, 'token'), true);
        $snapshot['map'] = array_intersect_key($snapshot['map'], $tokens);
        $snapshot['types'] = array_intersect_key($snapshot['types'], $tokens);
        $snapshot['spans'] = $kept;

        return $snapshot;
    }

    /** Render ordered, non-overlapping byte intervals without copying the whole source per span. */
    private static function render(string $source, array $spans): string
    {
        $parts = [];
        $cursor = 0;
        foreach ($spans as $span) {
            $parts[] = substr($source, $cursor, $span['start'] - $cursor);
            $parts[] = $span['token'];
            $cursor = $span['end'];
        }
        $parts[] = substr($source, $cursor);

        return implode('', $parts);
    }
}
