<?php

declare(strict_types=1);

namespace Redakte\Detection;

use Redakte\Policy\RedactionPolicy;

/**
 * $O(m \log m)$ ağırlıklı aralık çizelgeleme (Weighted Interval Scheduling)
 * algoritması ile çakışan tespit adaylarını en yüksek puanlı ve deterministik
 * biçimde çözen çözümleyici.
 */
final class OverlapResolver
{
    /**
     * @param list<Detection> $candidates
     * @return list<Detection>
     */
    public function resolve(array $candidates, RedactionPolicy $policy, ?string $originalText = null): array
    {
        if (empty($candidates)) {
            return [];
        }

        // 1. Politika filtresi
        $filtered = [];
        foreach ($candidates as $candidate) {
            if ($policy->name === RedactionPolicy::VALIDATED_ONLY && $candidate->validationStatus !== 'valid') {
                continue;
            }

            if ($candidate->validationStatus === 'invalid') {
                if ($policy->name === RedactionPolicy::BALANCED && !$candidate->hasEvidence(DetectionEvidence::LABEL_MATCH)) {
                    continue;
                }
            }

            if ($candidate->confidence < $policy->minConfidence && !$candidate->hasEvidence(DetectionEvidence::LABEL_MATCH)) {
                continue;
            }

            $filtered[] = $candidate;
        }

        $n = count($filtered);
        if ($n === 0) {
            return [];
        }
        if ($n === 1) {
            return $filtered;
        }

        // 2. Bitiş ofsetine göre sırala (eşitlikte başlangıç ofseti, sonra skor)
        usort($filtered, function (Detection $a, Detection $b) {
            if ($a->endOffset !== $b->endOffset) {
                return $a->endOffset <=> $b->endOffset;
            }
            if ($a->startOffset !== $b->startOffset) {
                return $a->startOffset <=> $b->startOffset;
            }
            return $this->computeScore($b) <=> $this->computeScore($a);
        });

        // 3. Her eleman için kendisinden önce çakışmayan son elemanı (p[i]) ikili arama (binary search) ile bul
        $p = array_fill(0, $n, -1);
        for ($i = 0; $i < $n; $i++) {
            $low = 0;
            $high = $i - 1;
            $targetStart = $filtered[$i]->startOffset;
            $lastNonOverlap = -1;

            while ($low <= $high) {
                $mid = (int) (($low + $high) / 2);
                if ($filtered[$mid]->endOffset <= $targetStart) {
                    $lastNonOverlap = $mid;
                    $low = $mid + 1;
                } else {
                    $high = $mid - 1;
                }
            }
            $p[$i] = $lastNonOverlap;
        }

        // 4. Dinamik programlama ile optimal alt küme ağırlığını hesapla
        // dp[i] = 1..n aralığında en yüksek puan
        $dp = array_fill(0, $n + 1, 0.0);
        for ($i = 1; $i <= $n; $i++) {
            $score = $this->computeScore($filtered[$i - 1]);
            $prevIdx = $p[$i - 1];
            $includeScore = $score + ($prevIdx !== -1 ? $dp[$prevIdx + 1] : 0.0);
            $excludeScore = $dp[$i - 1];

            $dp[$i] = max($includeScore, $excludeScore);
        }

        // 5. Seçilen aralıkları geriye doğru izle (backtrack)
        $selected = [];
        $curr = $n;
        while ($curr > 0) {
            $score = $this->computeScore($filtered[$curr - 1]);
            $prevIdx = $p[$curr - 1];
            $includeScore = $score + ($prevIdx !== -1 ? $dp[$prevIdx + 1] : 0.0);

            if ($includeScore >= $dp[$curr - 1]) {
                $selected[] = $filtered[$curr - 1];
                $curr = $prevIdx + 1;
            } else {
                $curr--;
            }
        }

        // Başlangıç ofsetine göre sırala
        usort($selected, fn (Detection $a, Detection $b) => $a->startOffset <=> $b->startOffset);

        return $originalText === null ? $selected : $this->preserveLlmCoverage($selected, $filtered, $originalText);
    }

    /**
     * A shorter, higher-scoring rule must not expose part of an accepted LLM span.
     * Only selected spans and policy-accepted LLM spans participate. Connected
     * overlaps are scanned once after sorting; rule-only resolution is unchanged.
     *
     * @param list<Detection> $selected Non-overlapping weighted selection
     * @param list<Detection> $accepted Policy-filtered candidates
     * @return list<Detection>
     */
    private function preserveLlmCoverage(array $selected, array $accepted, string $originalText): array
    {
        $entries = [];
        foreach ($accepted as $candidate) {
            if ($candidate->hasEvidence(DetectionEvidence::LLM_SOURCE_MATCH)) {
                $entries[] = [$candidate, false];
            }
        }
        if ($entries === []) {
            return $selected;
        }
        foreach ($selected as $candidate) {
            $entries[] = [$candidate, true];
        }
        usort($entries, static fn (array $a, array $b): int =>
            ($a[0]->startOffset <=> $b[0]->startOffset) ?: ($a[0]->endOffset <=> $b[0]->endOffset));

        $result = [];
        $group = [];
        $end = -1;
        foreach ($entries as $entry) {
            if ($group !== [] && $entry[0]->startOffset >= $end) {
                array_push($result, ...$this->coverGroup($group, $originalText));
                $group = [];
            }
            $group[] = $entry;
            $end = max($end, $entry[0]->endOffset);
        }
        array_push($result, ...$this->coverGroup($group, $originalText));
        return $result;
    }

    /** @param list<array{Detection, bool}> $group @return list<Detection> */
    private function coverGroup(array $group, string $originalText): array
    {
        $start = $group[0][0]->startOffset;
        $end = $start;
        $coveredBytes = 0;
        $selected = [];
        $anchor = null;
        foreach ($group as [$candidate, $isSelected]) {
            $end = max($end, $candidate->endOffset);
            if ($isSelected) {
                $selected[] = $candidate;
                $coveredBytes += $candidate->length();
            } elseif ($anchor === null || $this->computeScore($candidate) > $this->computeScore($anchor)) {
                $anchor = $candidate;
            }
        }
        if ($anchor === null || $coveredBytes === $end - $start) {
            return $selected;
        }
        $value = substr($originalText, $start, $end - $start);
        $unchanged = $start === $anchor->startOffset && $end === $anchor->endOffset;
        return [new Detection(
            startOffset: $start, endOffset: $end, entityType: $anchor->entityType,
            originalValue: $value, normalizedValue: $unchanged ? $anchor->normalizedValue : $value,
            confidence: $anchor->confidence, ruleId: $anchor->ruleId,
            validationStatus: $unchanged ? $anchor->validationStatus : 'not_checked',
            evidences: $unchanged ? $anchor->evidences : [DetectionEvidence::LLM_SOURCE_MATCH->value],
            priority: $anchor->priority, metadata: ['coverage_merged' => true],
        )];
    }

    /**
     * Adayın puanını deterministik olarak hesaplar.
     */
    public function computeScore(Detection $d): float
    {
        $score = (float) ($d->priority * 100);

        if ($d->hasEvidence(DetectionEvidence::CHECKSUM_VALID)) {
            $score += 500.0;
        }

        if ($d->hasEvidence(DetectionEvidence::LABEL_MATCH)) {
            $score += 400.0;
        }

        if ($d->hasEvidence(DetectionEvidence::ROLE_CONTEXT)) {
            $score += 250.0;
        }

        if ($d->hasEvidence(DetectionEvidence::DICTIONARY_MATCH)) {
            $score += 150.0;
        }

        if ($d->hasEvidence(DetectionEvidence::CHECKSUM_INVALID)) {
            $score -= 350.0;
        }

        // Güven katsayısı
        $score += ($d->confidence * 100.0);

        // Uzunluk hafif avantaj sağlar (örn. daha spesifik tam eşleşme)
        $score += min($d->length(), 30);

        return max(0.1, $score);
    }
}
