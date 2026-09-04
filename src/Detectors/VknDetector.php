<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Validators\VknChecksumValidator;

final class VknDetector implements DetectorInterface
{
    public function __construct(
        private readonly ?VknChecksumValidator $validator = null,
    ) {}

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('VKN')) {
            return [];
        }

        $validator = $this->validator ?? new VknChecksumValidator();
        $detections = [];

        // Tüm 10 haneli sayılar (P1-05: 5 ile başlayanlar dahil)
        $pattern = '/(?<!\d)(?:\d{10}|\d{3}[\s.-]\d{3}[\s.-]\d{4})(?!\d)/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $raw = $match[0][0];
            $start = (int) $match[0][1];
            $end = $start + strlen($raw);

            if ($context->overlapsExclude($start, $end)) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', $raw) ?? '';
            if (strlen($digits) !== 10) {
                continue;
            }

            // Çevreleyen bağlamda VKN etiketi var mı?
            $beforeText = substr($text, max(0, $start - 35), min(35, $start));
            $hasLabel = (bool) preg_match('/(?:VKN|Vergi\s*(?:Kimlik)?\s*No(?:su)?)\s*[:\-\/]?\s*$/ui', $beforeText);

            $isValid = $validator->isValid($digits);

            $evidences = [DetectionEvidence::FORMAT_MATCH->value];
            if ($hasLabel) {
                $evidences[] = DetectionEvidence::LABEL_MATCH->value;
            }

            if ($isValid) {
                $evidences[] = DetectionEvidence::CHECKSUM_VALID->value;
                $confidence = 0.95;
                $status = 'valid';
            } else {
                $evidences[] = DetectionEvidence::CHECKSUM_INVALID->value;
                $confidence = $hasLabel ? 0.8 : 0.5;
                $status = 'invalid';
            }

            // 5xx ile başlayıp vergi etiketi yoksa telefon lehine önceliği düşük tutulur
            $priority = ($hasLabel || $isValid) ? 88 : 80;
            if (str_starts_with($digits, '5') && !$hasLabel && !$isValid) {
                $priority = 70;
            }

            $detections[] = new Detection(
                startOffset: $start,
                endOffset: $end,
                entityType: 'VKN',
                originalValue: $raw,
                normalizedValue: $digits,
                confidence: $confidence,
                ruleId: 'VKN_10',
                validationStatus: $status,
                evidences: $evidences,
                priority: $priority,
            );
        }

        return $detections;
    }
}
