<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Validators\TcknChecksumValidator;

final class TcknDetector implements DetectorInterface
{
    public function __construct(
        private readonly ?TcknChecksumValidator $validator = null,
    ) {}

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('TCKN')) {
            return [];
        }

        $validator = $this->validator ?? new TcknChecksumValidator();
        $detections = [];

        // 11 haneli bitişik veya gruplu adaylar (Örn: 12345678901, 123 456 789 01, 01234567890)
        $pattern = '/(?<!\d)(?:\d{11}|\d{3}[\s.-]\d{3}[\s.-]\d{3}[\s.-]\d{2})(?!\d)/u';

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
            if (strlen($digits) !== 11) {
                continue;
            }

            // Çevreleyen bağlamda etiket var mı? (Örn: TCKN:, T.C. Kimlik No)
            $beforeText = substr($text, max(0, $start - 30), min(30, $start));
            $hasLabel = (bool) preg_match('/(?:TCKN|T\.?C\.?\s*Kimlik\s*No(?:su)?|Kimlik\s*No(?:su)?)\s*[:\-\/]?\s*$/ui', $beforeText);

            $isValid = $validator->isValid($digits);

            $evidences = [DetectionEvidence::FORMAT_MATCH->value];
            if ($hasLabel) {
                $evidences[] = DetectionEvidence::LABEL_MATCH->value;
            }

            if ($isValid) {
                $evidences[] = DetectionEvidence::CHECKSUM_VALID->value;
                $confidence = 1.0;
                $status = 'valid';
            } else {
                $evidences[] = DetectionEvidence::CHECKSUM_INVALID->value;
                $confidence = $hasLabel ? 0.85 : 0.55;
                $status = 'invalid';
            }

            $detections[] = new Detection(
                startOffset: $start,
                endOffset: $end,
                entityType: 'TCKN',
                originalValue: $raw,
                normalizedValue: $digits,
                confidence: $confidence,
                ruleId: 'TCKN_CHECKSUM',
                validationStatus: $status,
                evidences: $evidences,
                priority: 95,
            );
        }

        return $detections;
    }
}
