<?php

declare(strict_types=1);

namespace Redakte\Detectors;

use Redakte\Contracts\DetectorInterface;
use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;
use Redakte\Detection\DetectionEvidence;
use Redakte\Normalization\Canonicalizers\NameCanonicalizer;
use Redakte\PatternRegistry;
use Redakte\Support\NameDictionary;

final class NameDetector implements DetectorInterface
{
    private const NAME_WORD_REGEX = '(?:\p{Lu}\p{L}+|\p{Lu}\.)';
    private const NAME_REGEX = '(?:\p{Lu}(?:\p{L}+|\.)(?:\s+' . self::NAME_WORD_REGEX . '){1,3})';

    private NameDictionary $dictionary;
    private NameCanonicalizer $canonicalizer;

    public function __construct(
        private readonly ?PatternRegistry $registry = null,
        ?NameDictionary $dictionary = null,
        ?NameCanonicalizer $canonicalizer = null,
    ) {
        $this->dictionary = $dictionary ?? new NameDictionary($this->registry);
        $this->canonicalizer = $canonicalizer ?? new NameCanonicalizer();
    }

    public function detect(string $text, DetectionContext $context): array
    {
        if (!$context->options->redactNames || !$context->isEntityAllowed('KISI')) {
            return [];
        }

        // name_redaction.enabled kontrolü (P0-05)
        $nameConfig = $this->registry?->getNameRedactionConfig() ?? [];
        if (isset($nameConfig['enabled']) && $nameConfig['enabled'] === false) {
            return [];
        }

        $detections = [];

        // 1. Katman: Bağlamsal kalıplar (Etiket, Rol, Unvan, Hitap)
        $this->detectContextualNames($text, $context, $detections);

        // 2. Katman: Gazetteer (NVİ/TÜİK İsim Sözlüğü) ile serbest isimler
        $isGazetteer = (bool) ($nameConfig['gazetteer'] ?? true);
        if ($isGazetteer) {
            $this->detectGazetteerNames($text, $context, $detections);
        }

        return $detections;
    }

    /**
     * @param list<Detection> &$detections
     */
    private function detectContextualNames(string $text, DetectionContext $context, array &$detections): void
    {
        $patterns = $this->buildContextualPatterns();

        foreach ($patterns as $patternConfig) {
            $regex = $patternConfig['regex'];
            $priority = $patternConfig['priority'];
            $evidenceType = $patternConfig['evidence'];

            if (preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $name = $match['name'][0] ?? '';
                $nameStart = (int) ($match['name'][1] ?? 0);
                $name = trim($name);

                if ($name === '' || $this->isExcludedName($name)) {
                    continue;
                }

                $nameEnd = $nameStart + strlen($name);

                if ($context->overlapsExclude($nameStart, $nameEnd)) {
                    continue;
                }

                $canonical = $this->canonicalizer->canonicalize($name);

                $detections[] = new Detection(
                    startOffset: $nameStart,
                    endOffset: $nameEnd,
                    entityType: 'KISI',
                    originalValue: $name,
                    normalizedValue: $canonical,
                    confidence: 0.95,
                    ruleId: 'NAME_CONTEXTUAL',
                    validationStatus: 'not_checked',
                    evidences: [DetectionEvidence::ROLE_CONTEXT->value, $evidenceType],
                    priority: $priority,
                );
            }
        }
    }

    /**
     * @param list<Detection> &$detections
     */
    private function detectGazetteerNames(string $text, DetectionContext $context, array &$detections): void
    {
        // Sözcükleri ofsetleriyle yakala (apostrof eklerini ayır)
        if (preg_match_all('/\b\p{Lu}\p{L}*(?:[\'\x{2019}][\p{L}]+)?\b/u', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        $words = [];
        foreach ($matches[0] as $m) {
            $raw = $m[0];
            $clean = preg_replace('/[\'\x{2019}][\p{L}]+$/u', '', $raw) ?? $raw;
            $words[] = [
                'raw' => $raw,
                'clean' => $clean,
                'offset' => (int) $m[1],
                'len' => strlen($raw),
                'clean_len' => strlen($clean),
            ];
        }

        $count = count($words);

        for ($i = 0; $i < $count; $i++) {
            $w = $words[$i];
            if ($this->dictionary->isFirstName($w['clean'])) {
                $nameWords = [$w['clean']];
                $lastWord = $w;
                $j = $i + 1;

                while ($j < $count && ($j - $i) <= 3) {
                    $nextW = $words[$j];
                    $between = substr($text, $lastWord['offset'] + $lastWord['len'], $nextW['offset'] - ($lastWord['offset'] + $lastWord['len']));
                    if ($between !== ' ') {
                        break;
                    }
                    $nameWords[] = $nextW['clean'];
                    $lastWord = $nextW;
                    $j++;
                }

                if (count($nameWords) >= 2) {
                    $nameStart = $w['offset'];
                    $nameEnd = $lastWord['offset'] + $lastWord['clean_len'];
                    $name = substr($text, $nameStart, $nameEnd - $nameStart);

                    if ($this->isExcludedName($name) || $context->overlapsExclude($nameStart, $nameEnd)) {
                        continue;
                    }

                    // Kurumsal niteleyici kontrolü
                    $hasInstitution = false;
                    foreach ($nameWords as $nw) {
                        if ($this->dictionary->isInstitutionWord($nw)) {
                            $hasInstitution = true;
                            break;
                        }
                    }
                    if ($hasInstitution) {
                        continue;
                    }

                    // İsmin hemen sonrasındaki kelime kurum niteleyicisi mi?
                    $afterChunk = substr($text, $nameEnd, 40);
                    if (preg_match('/^\s+(\p{Lu}\p{L}+)/u', $afterChunk, $afterMatch)) {
                        if ($this->dictionary->isInstitutionWord($afterMatch[1])) {
                            continue;
                        }
                    }

                    $canonical = $this->canonicalizer->canonicalize($name);

                    $detections[] = new Detection(
                        startOffset: $nameStart,
                        endOffset: $nameEnd,
                        entityType: 'KISI',
                        originalValue: $name,
                        normalizedValue: $canonical,
                        confidence: 0.85,
                        ruleId: 'NAME_GAZETTEER',
                        validationStatus: 'not_checked',
                        evidences: [DetectionEvidence::DICTIONARY_MATCH->value],
                        priority: 65,
                    );

                    $i = $j - 1;
                }
            }
        }
    }

    /**
     * @return list<array{regex: string, priority: int, evidence: string}>
     */
    private function buildContextualPatterns(): array
    {
        $config = $this->registry?->getNameRedactionConfig() ?? [];

        $roles = $config['roles'] ?? [
            'Davacı', 'Davalı', 'Müşteki', 'Sanık', 'Şüpheli', 'Mağdur', 'Katılan',
            'Müdahil', 'Tanık', 'Mirasçı', 'Müvekkil', 'Borçlu', 'Alacaklı',
            'İhbar Olunan', 'Vasi', 'Kayyım', 'Kiracı', 'Kiraya Veren', 'Müteahhit',
            'İşveren', 'İşçi', 'Taraf', 'Başvuran', 'İtiraz Eden',
        ];

        $titles = $config['titles'] ?? [
            'Av\.', 'Avukat', 'Hakim', 'Hâkim', 'Savcı', 'Dr\.', 'Doktor',
            'Prof\.\s*Dr\.', 'Doç\.\s*Dr\.', 'Bilirkişi', 'Noter', 'Uzman',
        ];

        $labels = $config['labels'] ?? [
            'Adı\s+Soyadı', 'Ad\s+Soyad', 'İsim\s+Soyisim', 'İsim', 'Adı',
            'Vekili', 'Müdafii', 'İmza', 'İmzası', 'Yetkili',
        ];

        $salutations = $config['salutations'] ?? ['Sayın', 'Sn\.'];

        $patterns = [];

        // 1. Etiket: "Adı Soyadı: Ahmet Yılmaz"
        $labelGroup = implode('|', $labels);
        $patterns[] = [
            'regex' => '/\b(?:' . $labelGroup . ')\s*[:\-\/]\s*(?:Av\.\s*)?(?<name>' . self::NAME_REGEX . ')(?:[\'\x{2019}][\p{L}]+)?\b/ui',
            'priority' => 96,
            'evidence' => DetectionEvidence::LABEL_MATCH->value,
        ];

        // 2. Rol: "Davacı Ahmet Yılmaz", "Müvekkilim Mehmet Kaya"
        $roleGroup = implode('|', $roles);
        $patterns[] = [
            'regex' => '/\b(?:Davacı\s+|Davalı\s+)?(?:' . $roleGroup . ')(?:[ıi]m|[ıi]|imiz)?(?:\s+müvekkil(?:im|i|imiz)?)?\s*[:\-\/]?\s+(?:Av\.\s*)?(?<name>' . self::NAME_REGEX . ')(?:[\'\x{2019}][\p{L}]+)?(?!\s+(?:Mahkemesi|Başsavcılığı|Dairesi|Müdürlüğü|Bakanlığı|Kanunu))\b/ui',
            'priority' => 93,
            'evidence' => DetectionEvidence::ROLE_CONTEXT->value,
        ];

        // 3. Unvan: "Av. Mehmet Kaya", "Hakim Ali Çelik"
        $titleGroup = implode('|', $titles);
        $patterns[] = [
            'regex' => '/\b(?:' . $titleGroup . ')\s+(?<name>' . self::NAME_REGEX . ')(?:[\'\x{2019}][\p{L}]+)?(?!\s+(?:Mahkemesi|Başsavcılığı|Dairesi|Müdürlüğü|Kanunu))\b/ui',
            'priority' => 92,
            'evidence' => DetectionEvidence::ROLE_CONTEXT->value,
        ];

        // 4. Hitap: "Sayın Ahmet Yılmaz"
        $salutationGroup = implode('|', $salutations);
        $patterns[] = [
            'regex' => '/\b(?:' . $salutationGroup . ')\s+(?<name>' . self::NAME_REGEX . ')(?:[\'\x{2019}][\p{L}]+)?\b/ui',
            'priority' => 90,
            'evidence' => DetectionEvidence::ROLE_CONTEXT->value,
        ];

        return $patterns;
    }

    private function isExcludedName(string $name): bool
    {
        $exclusions = [
            'türk ceza kanunu', 'ceza muhakemesi kanunu', 'hukuk muhakemeleri kanunu',
            'borçlar kanunu', 'icra iflas kanunu', 'iş kanunu', 'medeni kanun',
            'yargıtay', 'danıştay', 'anayasa mahkemesi', 'bölge adliye mahkemesi',
            'asliye ceza', 'asliye hukuk', 'sulh ceza', 'sulh hukuk', 'ağır ceza',
        ];

        $lower = mb_strtolower($name, 'UTF-8');
        foreach ($exclusions as $ex) {
            if (str_contains($lower, $ex)) {
                return true;
            }
        }

        return false;
    }
}
