<?php

declare(strict_types=1);

namespace Redakte\Support;

use Redakte\Data\TurkishNames;
use Redakte\PatternRegistry;

/**
 * Türkçe İsim Sözlüğü (Gazetteer) yöneticisi.
 * NVİ/TÜİK listesi ve kullanıcı özel isimlerini birleştirerek O(1) hızla ad sorgusu yapar.
 */
class NameDictionary
{
    /** @var array<string, true>|null */
    private ?array $customNames = null;

    /** @var array<string, true> */
    private array $institutionWords;

    /** @var array<string, true> */
    private array $nonNameWords;

    public function __construct(
        private ?PatternRegistry $registry = null,
    ) {
        $institutions = [
            'mahkemesi', 'mahkeme', 'başsavcılığı', 'başsavcılık', 'savcılığı',
            'dairesi', 'müdürlüğü', 'müdürlük', 'bakanlığı', 'bakanlık',
            'başkanlığı', 'başkanlık', 'valiliği', 'valilik', 'kaymakamlığı',
            'belediyesi', 'belediye', 'üniversitesi', 'fakültesi', 'enstitüsü',
            'hastanesi', 'şirketi', 'şirket', 'holding', 'kurumu', 'kurul', 'kurulu',
            'kulübü', 'derneği', 'vakfı', 'komisyonu', 'partisi', 'bankası',
            'gazetesi', 'kanunu', 'tüzüğü', 'yönetmeliği', 'maddesi', 'fıkrası',
            'sayılı', 'kararı', 'tarihli', 'bölge', 'asliye', 'sulh', 'ağır',
            'komutanlığı', 'emniyet', 'jandarma',
        ];

        $this->institutionWords = array_fill_keys($institutions, true);

        $nonNames = [
            'türkiye', 'istanbul', 'ankara', 'izmir', 'bursa', 'antalya', 'adana',
            'bu', 'şu', 'o', 'her', 'bir', 'tüm', 'bütün', 'sayfa', 'konu',
            'özet', 'hüküm', 'talep', 'karar', 'sonuç', 'gerekçe', 'dosya',
            'tarih', 'ek', 'iç', 'dış', 'kamu', 'özel', 'genel', 'resmi',
        ];

        $this->nonNameWords = array_fill_keys($nonNames, true);
    }

    /**
     * Verilen kelimenin geçerli bir Türkçe ad olup olmadığını denetler
     */
    public function isFirstName(string $word): bool
    {
        $normalized = mb_strtolower(trim($word), 'UTF-8');

        if ($normalized === '' || isset($this->nonNameWords[$normalized])) {
            return false;
        }

        if (TurkishNames::isFirstName($normalized)) {
            return true;
        }

        $this->loadCustomNames();

        return isset($this->customNames[$normalized]);
    }

    /**
     * Verilen kelimenin kurumsal bir niteleyici (Mahkemesi, Üniversitesi vb.) olup olmadığını denetler
     */
    public function isInstitutionWord(string $word): bool
    {
        $normalized = mb_strtolower(trim($word), 'UTF-8');
        return isset($this->institutionWords[$normalized]);
    }

    /**
     * Konfigürasyondan gelen özel isimleri yükler
     */
    private function loadCustomNames(): void
    {
        if ($this->customNames !== null) {
            return;
        }

        $this->customNames = [];
        if ($this->registry !== null) {
            $nameConfig = $this->registry->getNameRedactionConfig();
            $custom = $nameConfig['custom_names'] ?? $this->registry->get('custom_names', []);
            if (is_array($custom)) {
                foreach ($custom as $name) {
                    if (is_string($name) && trim($name) !== '') {
                        $this->customNames[mb_strtolower(trim($name), 'UTF-8')] = true;
                    }
                }
            }
        }
    }
}
