<?php

declare(strict_types=1);

namespace Redakte\Redactors;

use Redakte\MaskStrategy;
use Redakte\PatternRegistry;
use Redakte\RedactionMap;
use Redakte\RedactionSpan;
use Redakte\Support\Masker;
use Redakte\Support\NameDictionary;

/**
 * Rol, unvan, etiket kalıpları ve NVİ/TÜİK İsim Sözlüğü (Gazetteer) desteğiyle
 * metindeki tüm kişi isimlerini (örneğin "Pınar İpek Parlak", "Ali Yılmaz")
 * unvan şartı olmaksızın, mikrosaniye hızında tespit ve redakte eder.
 */
final class NameRedactor
{
    /** Türkçe ad-soyad: 2-4 kelime, her kelime büyük harfle başlayan */
    private const NAME_REGEX = '(?:\p{Lu}\p{L}+(?:\s+\p{Lu}\p{L}+){1,3})';

    private NameDictionary $dictionary;

    public function __construct(
        private ?PatternRegistry $registry = null,
        ?NameDictionary $dictionary = null,
    ) {
        $this->registry ??= new PatternRegistry();
        $this->dictionary = $dictionary ?? new NameDictionary($this->registry);
    }

    /**
     * Metindeki isim-soyisimleri metin sırasına göre redakte eder
     *
     * @param array<string, int> &$nameToIndex Mevcut isim-indeks haritası
     * @param int &$counter Mevcut sayaç
     * @param MaskStrategy $strategy Maskeleme stratejisi
     * @param RedactionMap|null $map Eşleşmelerin kaydedileceği geri dönüş haritası
     * @param list<RedactionSpan> &$spans Tespit edilen parçalar
     * @return array{text: string, replacementsByType: array<string, int>}
     */
    public function redact(
        string $text,
        array &$nameToIndex = [],
        int &$counter = 0,
        MaskStrategy $strategy = MaskStrategy::TAG,
        ?RedactionMap $map = null,
        array &$spans = [],
    ): array {
        $candidates = [];

        // 1. Aşama: Bağlamsal kalıplar (Davacı, Davalı, Sayın, Av., Adı Soyadı: vb.)
        $patterns = $this->buildPatterns();
        foreach ($patterns as $regex) {
            if (preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $fullMatch = $match[0][0];
                $fullStart = $match[0][1];
                $fullEnd = $fullStart + strlen($fullMatch);

                $prefix = isset($match['prefix']) ? $match['prefix'][0] : '';
                $name = isset($match['name']) ? $match['name'][0] : (isset($match[1]) ? $match[1][0] : '');
                $suffix = isset($match['suffix']) ? $match['suffix'][0] : '';

                $name = trim($name);
                if ($name === '' || $this->isExcludedName($name)) {
                    continue;
                }

                $candidates[] = [
                    'start' => $fullStart,
                    'end' => $fullEnd,
                    'prefix' => $prefix,
                    'name' => $name,
                    'suffix' => $suffix,
                    'length' => strlen($fullMatch),
                    'priority' => 10, // Bağlamsal kalıplar öncelikli
                ];
            }
        }

        // 2. Aşama: NVİ İsim Sözlüğü (Gazetteer) ile unvansız yalın isim tespiti
        // (Örn: "Toplantıya Pınar İpek Parlak katıldı", "Ali Yılmaz konuştu")
        if ($this->isGazetteerEnabled()) {
            $this->scanGazetteerCandidates($text, $candidates);
        }

        // Metindeki başlangıç sırasına göre sırala (aynı başlangıçta daha yüksek öncelik veya daha uzun aday öne geçer)
        usort($candidates, function (array $a, array $b) {
            if ($a['start'] === $b['start']) {
                $pDiff = ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
                return $pDiff !== 0 ? $pDiff : ($b['length'] <=> $a['length']);
            }
            return $a['start'] <=> $b['start'];
        });

        // Çakışan adayları filtrele (daha önce eklenen kapsamlı adayı korur)
        $selected = [];
        foreach ($candidates as $cand) {
            $overlap = false;
            foreach ($selected as $sel) {
                if ($cand['start'] < $sel['end'] && $cand['end'] > $sel['start']) {
                    $overlap = true;
                    break;
                }
            }
            if (!$overlap) {
                $selected[] = $cand;
            }
        }

        // İndeksleri ata ve yer değiştirmeleri belirle
        $replacements = 0;
        foreach ($selected as &$item) {
            $normalizedName = mb_strtolower($item['name'], 'UTF-8');
            if (!isset($nameToIndex[$normalizedName])) {
                $counter++;
                $nameToIndex[$normalizedName] = $counter;
            }
            $idx = $nameToIndex[$normalizedName];
            $maskedName = Masker::mask($item['name'], 'KISI', $strategy, $idx);
            $item['replacement'] = $item['prefix'] . $maskedName . $item['suffix'];

            if ($map !== null) {
                $map->add('[KISI_' . $idx . ']', $item['name'], 'KISI');
            }

            $nameStart = $item['start'] + strlen($item['prefix']);
            $spans[] = new RedactionSpan(
                startOffset: $nameStart,
                endOffset: $nameStart + strlen($item['name']),
                entityType: 'KISI',
                replacement: $maskedName,
                confidence: 0.95,
                source: 'name_and_role_patterns',
                originalValue: $item['name'],
            );

            $replacements++;
        }
        unset($item);

        // Sondan başa değiştir (karakter ofsetlerinin kaymasını önlemek için)
        usort($selected, fn (array $a, array $b) => $b['start'] <=> $a['start']);
        foreach ($selected as $item) {
            $text = substr_replace($text, $item['replacement'], $item['start'], $item['length']);
        }

        return [
            'text' => $text,
            'replacementsByType' => ['KISI' => $replacements],
        ];
    }

    /**
     * NVİ/TÜİK İsim Sözlüğü ile cümlenin herhangi bir yerindeki yalın ad-soyadları tarar
     *
     * @param list<array<string, mixed>> &$candidates
     */
    private function scanGazetteerCandidates(string $text, array &$candidates): void
    {
        if (preg_match_all('/\b\p{Lu}\p{L}*(?:[\'\x{2019}][\p{L}]+)?\b/u', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        $words = [];
        foreach ($matches[0] as $m) {
            $raw = $m[0];
            $clean = preg_replace('/[\'\x{2019}][\p{L}]+$/u', '', $raw);
            $words[] = [
                'raw' => $raw,
                'clean' => $clean,
                'offset' => $m[1],
                'len' => strlen($raw),
                'clean_len' => strlen($clean),
                'suffix' => substr($raw, strlen($clean)),
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
                    $fullStart = $w['offset'];
                    $fullEnd = $lastWord['offset'] + $lastWord['len'];
                    $fullMatch = substr($text, $fullStart, $fullEnd - $fullStart);
                    $cleanName = substr($text, $fullStart, ($lastWord['offset'] + $lastWord['clean_len']) - $fullStart);
                    $suffix = $lastWord['suffix'];

                    if ($this->isExcludedName($cleanName)) {
                        continue;
                    }

                    // Aday içinde veya hemen sonrasında kurum niteleyicisi var mı?
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

                    $afterOffset = $fullEnd;
                    $afterChunk = substr($text, $afterOffset, 40);
                    if (preg_match('/^\s+(\p{Lu}\p{L}+)/u', $afterChunk, $afterMatch)) {
                        if ($this->dictionary->isInstitutionWord($afterMatch[1])) {
                            continue;
                        }
                    }

                    $candidates[] = [
                        'start' => $fullStart,
                        'end' => $fullEnd,
                        'prefix' => '',
                        'name' => $cleanName,
                        'suffix' => $suffix,
                        'length' => strlen($fullMatch),
                        'priority' => 5,
                    ];

                    $i = $j - 1;
                }
            }
        }
    }

    private function isGazetteerEnabled(): bool
    {
        $config = $this->registry?->getNameRedactionConfig() ?? [];
        return (bool) ($config['gazetteer'] ?? true);
    }

    /**
     * @return list<string>
     */
    private function buildPatterns(): array
    {
        $config = $this->registry?->getNameRedactionConfig() ?? [];
        $roles = $config['roles'] ?? [
            'Davacı', 'Davalı', 'Müşteki', 'Sanık', 'Şüpheli', 'Mağdur', 'Katılan',
            'Müdahil', 'Tanık', 'Mirasçı', 'Müvekkil', 'Borçlu', 'Alacaklı',
            'İhbar Olunan', 'Vasi', 'Kayyım', 'Kiracı', 'Kiraya Veren',
        ];
        $titles = $config['titles'] ?? [
            'Av\.', 'Avukat', 'Hakim', 'Hâkim', 'Savcı', 'Dr\.', 'Doktor',
            'Prof\.\s*Dr\.', 'Doç\.\s*Dr\.', 'Bilirkişi', 'Noter', 'Uzman',
        ];
        $labels = $config['labels'] ?? [
            'Adı\s+Soyadı', 'Ad\s+Soyad', 'İsim\s+Soyisim', 'İsim', 'Adı',
            'Vekili', 'Müdafii', 'İmza', 'İmzası',
        ];
        $salutations = $config['salutations'] ?? ['Sayın', 'Sn\.'];

        $patterns = [];

        // 1. Etiket kalıpları: "Adı Soyadı: Ahmet Yılmaz", "Vekili: Av. Mehmet Kaya"
        $labelGroup = implode('|', $labels);
        $patterns[] = '/(?<prefix>\b(?:' . $labelGroup . ')\s*[:\-\/]\s*(?:Av\.\s*)?)(?<name>' . self::NAME_REGEX . ')(?<suffix>[\'\x{2019}][\p{L}]+)?\b/ui';

        // 2. Rol kalıpları (Müvekkilim, Davacı müvekkili, kiracı, sanık vb. ve opsiyonel kesme işaretli ek)
        $roleGroup = implode('|', $roles);
        $patterns[] = '/(?<prefix>\b(?:Davacı\s+|Davalı\s+)?(?:' . $roleGroup . ')(?:[ıi]m|[ıi]|imiz)?(?:\s+müvekkil(?:im|i|imiz)?)?\s*[:\-\/]?\s+(?:Av\.\s*)?)(?<name>' . self::NAME_REGEX . ')(?<suffix>[\'\x{2019}][\p{L}]+)?(?!\s+(?:Mahkemesi|Başsavcılığı|Dairesi|Müdürlüğü|Bakanlığı|Kanunu|Gereğince|Uyarınca))\b/ui';

        // 3. Unvan ve Vekil kalıpları: "Av. Mehmet Kaya", "Hakim Ali Çelik"
        $titleGroup = implode('|', $titles);
        $patterns[] = '/(?<prefix>\b(?:' . $titleGroup . ')\s+)(?<name>' . self::NAME_REGEX . ')(?<suffix>[\'\x{2019}][\p{L}]+)?(?!\s+(?:Mahkemesi|Başsavcılığı|Dairesi|Müdürlüğü|Kanunu))\b/ui';

        // 4. Hitap kalıpları: "Sayın Ahmet Yılmaz", "Sn. Fatma Öz"
        $salutationGroup = implode('|', $salutations);
        $patterns[] = '/(?<prefix>\b(?:' . $salutationGroup . ')\s+)(?<name>' . self::NAME_REGEX . ')(?<suffix>[\'\x{2019}][\p{L}]+)?\b/ui';

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
