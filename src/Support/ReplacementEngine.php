<?php

declare(strict_types=1);

namespace Redakte\Support;

use Redakte\Detection\Detection;
use Redakte\MaskStrategy;
use Redakte\RedactionSpan;
use Redakte\Result\RedactionWarning;
use Redakte\Token\RedactionSession;

/**
 * Çözümlenmiş tespit adaylarını tek bir geçişte, orijinal metin koordinatları üzerinde
 * sondan başa uygulayarak yer tutucuları yerleştiren motor.
 */
final class ReplacementEngine
{
    /**
     * @param string $original Orijinal metin
     * @param list<Detection> $detections Çözümlenmiş tespit listesi
     * @param MaskStrategy $strategy Maskeleme stratejisi
     * @param RedactionSession $session Oturum yöneticisi
     * @param bool $legacyTokenFormat [ENTITY_idx] formatı kullanılsın mı
     * @return array{0: string, 1: list<RedactionSpan>, 2: list<RedactionWarning>}
     */
    public function apply(
        string $original,
        array $detections,
        MaskStrategy $strategy,
        RedactionSession $session,
        bool $legacyTokenFormat = false,
    ): array {
        if (empty($detections)) {
            return [$original, [], []];
        }

        // Başlangıç ofsetine göre sırala
        usort($detections, fn (Detection $a, Detection $b) => $a->startOffset <=> $b->startOffset);

        $spans = [];
        $warnings = [];

        $storeInMap = ($strategy === MaskStrategy::TAG);

        foreach ($detections as $d) {
            // Oturumdan token ve indeks al (yalnızca TAG stratejisinde haritada sakla)
            $tokenInfo = $session->getOrCreateToken(
                entityType: $d->entityType,
                canonicalValue: $d->normalizedValue,
                originalValue: $d->originalValue,
                originalTextContext: $original,
                legacyFormat: $legacyTokenFormat,
                storeInMap: $storeInMap,
            );

            $token = $tokenInfo['token'];
            $idx = $tokenInfo['index'];

            $replacement = Masker::mask(
                value: $d->originalValue,
                entityType: $d->entityType,
                strategy: $strategy,
                index: $idx,
                token: $token,
            );

            // Geçersiz checksum ile maskelenmişse uyarı üret (P0-06)
            if ($d->validationStatus === 'invalid') {
                $warnings[] = new RedactionWarning(
                    code: 'UNVALIDATED_FORMAT_CANDIDATE',
                    entityType: $d->entityType,
                    severity: 'medium',
                    message: sprintf('Doğrulama kontrolünden geçmeyen olası %s adayı politika gereği maskelendi.', $d->entityType),
                    span: [$d->startOffset, $d->endOffset],
                );
            }

            $spans[] = new RedactionSpan(
                startOffset: $d->startOffset,
                endOffset: $d->endOffset,
                entityType: $d->entityType,
                replacement: $replacement,
                confidence: $d->confidence,
                source: $d->ruleId,
                originalValue: $d->originalValue,
            );
        }

        // Sondan başa değiştirme yapılarak karakter indeks kaymaları önlenir (P0-02)
        $redactedText = $original;
        $reverseSpans = $spans;
        usort($reverseSpans, fn (RedactionSpan $a, RedactionSpan $b) => $b->startOffset <=> $a->startOffset);

        foreach ($reverseSpans as $s) {
            $redactedText = substr_replace($redactedText, $s->replacement, $s->startOffset, $s->length());
        }

        return [$redactedText, $spans, $warnings];
    }
}
