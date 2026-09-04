<?php

declare(strict_types=1);

namespace Redakte;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\OverlapResolver;
use Redakte\Detectors\AddressDetector;
use Redakte\Detectors\CardDetector;
use Redakte\Detectors\CustomPatternDetector;
use Redakte\Detectors\EmailDetector;
use Redakte\Detectors\IbanDetector;
use Redakte\Detectors\IpAddressDetector;
use Redakte\Detectors\LegalNumberDetector;
use Redakte\Detectors\MersisDetector;
use Redakte\Detectors\NameDetector;
use Redakte\Detectors\PhoneDetector;
use Redakte\Detectors\PlateDetector;
use Redakte\Detectors\TcknDetector;
use Redakte\Detectors\VknDetector;
use Redakte\Normalization\NormalizedText;
use Redakte\Support\ReplacementEngine;
use Redakte\Token\RedactionSession;
use Redakte\Validators\IbanMod97Validator;
use Redakte\Validators\LuhnChecksumValidator;
use Redakte\Validators\TcknChecksumValidator;
use Redakte\Validators\VknChecksumValidator;

/**
 * Çekirdek redaksiyon motoru — Orijinal metin üzerinde Detect -> Resolve -> Apply akışını yürütür.
 */
class RedactionService
{
    /** @var list<DetectorInterface> */
    private array $detectors = [];
    private OverlapResolver $resolver;
    private ReplacementEngine $replacementEngine;

    public function __construct(
        private PatternRegistry $registry,
        private ReportSummaryBuilder $reportBuilder,
        ?TcknChecksumValidator $tcknValidator = null,
        ?IbanMod97Validator $ibanValidator = null,
        ?VknChecksumValidator $vknValidator = null,
        ?LuhnChecksumValidator $luhnValidator = null,
        ?OverlapResolver $resolver = null,
        ?ReplacementEngine $replacementEngine = null,
        ?array $customDetectors = null,
    ) {
        $this->resolver = $resolver ?? new OverlapResolver();
        $this->replacementEngine = $replacementEngine ?? new ReplacementEngine();

        if ($customDetectors !== null) {
            $this->detectors = $customDetectors;
        } else {
            $this->detectors = [
                new TcknDetector($tcknValidator),
                new VknDetector($vknValidator),
                new IbanDetector(validator: $ibanValidator),
                new PhoneDetector(),
                new EmailDetector(),
                new CardDetector(validator: $luhnValidator),
                new PlateDetector(),
                new MersisDetector(),
                new NameDetector($this->registry),
                new AddressDetector(),
                new LegalNumberDetector(),
                new IpAddressDetector(),
                new CustomPatternDetector($this->registry, $tcknValidator, $ibanValidator, $vknValidator, $luhnValidator),
            ];
        }
    }

    public function redact(
        string $text,
        ?RedactionOptions $options = null,
        ?RedactionMap $map = null,
    ): RedactionResult {
        $options ??= new RedactionOptions();
        $session = $options->session ?? new RedactionSession();

        // Harita dışarıdan verilmişse oturumla eşle
        if ($map !== null && $session->getMap()->count() === 0) {
            // map referansını koru
        }

        $normalized = NormalizedText::create($text, $options->strictMode);
        $normText = $normalized->getNormalizedText();
        $excludeRanges = $this->computeExcludeRanges($text);

        $context = new DetectionContext(
            originalText: $text,
            normalizedText: $normalized,
            options: $options,
            policy: $options->policy,
            excludeRanges: $excludeRanges,
        );

        // 1. Aşama: DETECT — Bütün dedektörler normalize edilmiş metinden aday üretir
        $allCandidates = [];
        foreach ($this->detectors as $detector) {
            $candidates = $detector->detect($normText, $context);
            if (!empty($candidates)) {
                foreach ($candidates as $candidate) {
                    [$origStart, $origEnd] = $normalized->mapSpanToOriginal($candidate->startOffset, $candidate->endOffset);
                    $origValue = substr($text, $origStart, $origEnd - $origStart);

                    $allCandidates[] = new Detection(
                        startOffset: $origStart,
                        endOffset: $origEnd,
                        entityType: $candidate->entityType,
                        originalValue: $origValue,
                        normalizedValue: $candidate->normalizedValue,
                        confidence: $candidate->confidence,
                        ruleId: $candidate->ruleId,
                        validationStatus: $candidate->validationStatus,
                        evidences: $candidate->evidences,
                        priority: $candidate->priority,
                    );
                }
            }
        }

        // 2. Aşama: RESOLVE — Çakışmalar deterministik O(m log m) interval algoritmasıyla çözülür
        $selected = $this->resolver->resolve($allCandidates, $options->policy);

        // 3. Aşama: APPLY — Değişiklikler tek geçişte sondan başa uygulanır
        $legacyFormat = ($options->tokenFormat === 'legacy');
        [$redactedText, $spans, $warnings] = $this->replacementEngine->apply(
            original: $text,
            detections: $selected,
            strategy: $options->strategy,
            session: $session,
            legacyTokenFormat: $legacyFormat,
        );

        $resultMap = $session->getMap();
        if ($map !== null) {
            foreach ($resultMap->all() as $tok => $orig) {
                $map->add($tok, $orig, $resultMap->getType($tok) ?? '');
            }
            $resultMap = $map;
        }

        $replacementsByType = $this->countByType($spans);
        $reportSummary = $this->reportBuilder->build($replacementsByType);
        $appliedRules = array_values(array_unique(array_map(fn (RedactionSpan $s) => $s->source, $spans)));

        // Risk seviyesi hesaplama (P1-18)
        $hasInvalidCandidates = false;
        foreach ($selected as $s) {
            if ($s->validationStatus === 'invalid') {
                $hasInvalidCandidates = true;
                break;
            }
        }

        $riskLevel = $hasInvalidCandidates ? 'medium' : 'low';
        $status = 'complete';

        return new RedactionResult(
            spans: $spans,
            redactedText: $redactedText,
            replacementsByType: $replacementsByType,
            reportSummary: $reportSummary,
            riskLevel: $riskLevel,
            warnings: $warnings,
            policyId: $this->registry->getPolicyId(),
            policyVersion: $this->registry->getPolicyVersion(),
            appliedRules: $appliedRules,
            requiresHumanReview: false,
            map: $resultMap,
            status: $status,
            strategy: $options->strategy,
        );
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function computeExcludeRanges(string $text): array
    {
        $ranges = [];
        foreach ($this->registry->getExcludePatterns() as $regex) {
            $m = [];
            if (@preg_match_all($regex, $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($m as $match) {
                $ranges[] = [(int) $match[0][1], (int) ($match[0][1] + strlen($match[0][0]))];
            }
        }
        return $ranges;
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

    public function getRegistry(): PatternRegistry
    {
        return $this->registry;
    }
}
