<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Normalization\Canonicalizers\PhoneCanonicalizer;

final class PhoneDetector implements DetectorInterface
{
    private PhoneCanonicalizer $canonicalizer;

    public function __construct(?PhoneCanonicalizer $canonicalizer = null)
    {
        $this->canonicalizer = $canonicalizer ?? new PhoneCanonicalizer();
    }

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('TELEFON')) {
            return [];
        }

        $detections = [];

        // Telefon adayı regex'i: Dengeli veya parantezsiz, opsiyonel +90, 0090, 0 veya doğrudan alan kodu
        // Örn: +90 (532) 123-45-67, 0 (532) 123 45 67, (0532) 123 45 67, 0532 123 45 67, 5321234567, 0212 555 66 77
        $pattern = '/(?<!\w)(?:(?:\+90|0090|90)\s*)?(?:0\s*)?(?:\((?:0?[2-5]\d{2})\)|[2-5]\d{2})[\s.\-]*(?:\d{3})[\s.\-]*(?:\d{2})[\s.\-]*(?:\d{2})(?!\d)/u';

        // Ayrıca bitişik 10-11 haneli mobil ve alan kodlu numaralar: \b0?5\d{9}\b veya \b0?[2-4]\d{9}\b
        $compactPattern = '/(?<!\d)(?:(?:\+90|0090|90)\s*)?0?[2-5]\d{9}(?!\d)/u';

        $allPatterns = [$pattern, $compactPattern];

        foreach ($allPatterns as $p) {
            if (preg_match_all($p, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $raw = $match[0][0];
                $start = (int) $match[0][1];
                $end = $start + strlen($raw);

                if ($context->overlapsExclude($start, $end)) {
                    continue;
                }

                // Dengeli parantez kontrolü
                $openCount = substr_count($raw, '(');
                $closeCount = substr_count($raw, ')');
                if ($openCount !== $closeCount) {
                    continue;
                }

                $digits = preg_replace('/\D+/', '', $raw) ?? '';
                // 10 hane (5321234567), 11 hane (05321234567), 12 hane (905321234567), 13 hane (9005321234567), 14 hane (00905321234567)
                if (!in_array(strlen($digits), [10, 11, 12, 13, 14], true)) {
                    continue;
                }

                // Öncesinde telefon etiketi var mı?
                $beforeText = substr($text, max(0, $start - 30), min(30, $start));
                $hasLabel = (bool) preg_match('/(?:Tel(?:efon)?|GSM|Cep|Mobil|İletişim|Faks|Fax)\s*[:\-\/]?\s*$/ui', $beforeText);

                $canonical = $this->canonicalizer->canonicalize($raw);

                $evidences = [DetectionEvidence::FORMAT_MATCH->value];
                if ($hasLabel) {
                    $evidences[] = DetectionEvidence::LABEL_MATCH->value;
                }

                $confidence = 0.9;
                if ($hasLabel) {
                    $confidence = 0.98;
                }

                // +90 sonrası hatalı 0 kontrolü (Örn: +90 0532...)
                if (preg_match('/(?:\+90|0090)\s*0[2-5]/', $raw)) {
                    $confidence -= 0.15;
                }

                $priority = $hasLabel ? 89 : (str_contains($raw, '(') || str_contains($raw, '+90') ? 86 : 82);

                $detections[] = new Detection(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: 'TELEFON',
                    originalValue: $raw,
                    normalizedValue: $canonical,
                    confidence: max(0.4, $confidence),
                    ruleId: 'TELEFON_PARSER',
                    validationStatus: 'not_checked',
                    evidences: $evidences,
                    priority: $priority,
                );
            }
        }

        return $detections;
    }
}
