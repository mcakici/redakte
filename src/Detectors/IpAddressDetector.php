<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;

final class IpAddressDetector implements DetectorInterface
{
    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->isEntityAllowed('IP_ADRESI')) {
            return [];
        }

        $detections = [];

        // 1. IPv4 regex
        $ipv4Pattern = '/(?<![0-9.])(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(?![0-9.])/u';

        if (preg_match_all($ipv4Pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
            foreach ($matches as $m) {
                $raw = $m[0][0];
                $start = (int) $m[0][1];
                $end = $start + strlen($raw);

                if ($context->overlapsExclude($start, $end)) {
                    continue;
                }

                $isValid = filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

                $detections[] = new Detection(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: 'IP_ADRESI',
                    originalValue: $raw,
                    normalizedValue: $raw,
                    confidence: $isValid ? 0.95 : 0.60,
                    ruleId: 'IP_V4',
                    validationStatus: $isValid ? 'valid' : 'invalid',
                    evidences: [DetectionEvidence::FORMAT_MATCH->value],
                    priority: 68,
                );
            }
        }

        // 2. IPv6 regex (tam veya sıkıştırılmış adresler: 2001:db8::1, ::1, fe80::1)
        $ipv6Pattern = '/(?<![a-fA-F0-9:])(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}(?![a-fA-F0-9:])|(?<![a-fA-F0-9:])(?:[0-9a-fA-F]{1,4}:){1,7}:(?![a-fA-F0-9:])|(?<![a-fA-F0-9:])::(?:[0-9a-fA-F]{1,4}:){0,6}[0-9a-fA-F]{1,4}(?![a-fA-F0-9:])|(?<![a-fA-F0-9:])(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}(?![a-fA-F0-9:])/u';

        if (preg_match_all($ipv6Pattern, $text, $v6Matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) !== false) {
            foreach ($v6Matches as $m) {
                $raw = $m[0][0];
                $start = (int) $m[0][1];
                $end = $start + strlen($raw);

                if ($context->overlapsExclude($start, $end) || strlen($raw) < 3) {
                    continue;
                }

                $isValid = filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
                if (!$isValid) {
                    continue;
                }

                $detections[] = new Detection(
                    startOffset: $start,
                    endOffset: $end,
                    entityType: 'IP_ADRESI',
                    originalValue: $raw,
                    normalizedValue: strtolower($raw),
                    confidence: 0.95,
                    ruleId: 'IP_V6',
                    validationStatus: 'valid',
                    evidences: [DetectionEvidence::FORMAT_MATCH->value],
                    priority: 69,
                );
            }
        }

        return $detections;
    }
}
