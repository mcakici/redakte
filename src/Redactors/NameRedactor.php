<?php

declare(strict_types=1);

namespace Redakte\Redactors;

use Redakte\MaskStrategy;
use Redakte\PatternRegistry;
use Redakte\RedactionMap;
use Redakte\RedactionSpan;
use Redakte\Support\Masker;

/**
 * Rol, unvan, etiket ve bağlamsal kalıplara göre isim-soyisim tespiti ve redaksiyonu.
 * Metin sırasına göre indeksleme ve ek koruma (örn. Mehmet Kaya'ya -> [KISI_1]'ya) sağlar.
 */
final class NameRedactor
{
    /** Türkçe ad-soyad: 2-4 kelime, her kelime büyük harfle başlayan */
    private const NAME_REGEX = '(?:\p{Lu}\p{L}+(?:\s+\p{Lu}\p{L}+){1,3})';

    public function __construct(
        private ?PatternRegistry $registry = null,
    ) {
        $this->registry ??= new PatternRegistry();
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
        $patterns = $this->buildPatterns();
        $candidates = [];

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
                ];
            }
        }

        // Metindeki başlangıç sırasına göre sırala
        usort($candidates, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        // Çakışan adayları filtrele
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

        // Sondan başa değiştir
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
     * @return list<string>
     */
    private function buildPatterns(): array
    {
        $config = $this->registry->getNameRedactionConfig();
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
