<?php

declare(strict_types=1);

/**
 * Redakte Paketi Konfigürasyonu
 *
 * Kişisel verilerin (TCKN, VKN, IBAN, Telefon, İsim-Soyisim vb.)
 * tespiti, kuralları, öncelikleri ve raporlama ayarları.
 */

return [
    'policy_id' => 'legal_tr_v1',
    'policy_version' => '2026.03',

    /**
     * Entity türleri — kodda tanımlayıcı ve Türkçe rapor etiketi
     */
    'entity_types' => [
        'TCKN' => ['label' => 'TCKN', 'label_plural' => 'TCKN'],
        'VKN' => ['label' => 'VKN', 'label_plural' => 'VKN'],
        'MERSIS' => ['label' => 'MERSİS', 'label_plural' => 'MERSİS'],
        'IBAN' => ['label' => 'IBAN', 'label_plural' => 'IBAN'],
        'TELEFON' => ['label' => 'telefon', 'label_plural' => 'telefon'],
        'EPOSTA' => ['label' => 'e-posta', 'label_plural' => 'e-posta'],
        'KISI' => ['label' => 'kişi adı', 'label_plural' => 'kişi adı'],
        'ADRES' => ['label' => 'adres', 'label_plural' => 'adres'],
        'PLAKA' => ['label' => 'plaka', 'label_plural' => 'plaka'],
        'KREDI_KARTI' => ['label' => 'kredi kartı', 'label_plural' => 'kredi kartı'],
        'ESAS_NO' => ['label' => 'esas no', 'label_plural' => 'esas no'],
        'KARAR_NO' => ['label' => 'karar no', 'label_plural' => 'karar no'],
        'DOSYA_NO' => ['label' => 'dosya no', 'label_plural' => 'dosya no'],
    ],

    /**
     * Varsayılan maskeleme stratejisi: 'tag' | 'partial' | 'asterisk' | 'label'
     */
    'default_strategy' => 'tag',

    /**
     * Pattern tanımları — öncelik sırasına göre (yüksek önce çalışır)
     */
    'patterns' => [
        [
            'id' => 'IBAN_MOD97',
            'entity_type' => 'IBAN',
            'regex' => '/\bTR\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{2}\b/u',
            'validator' => 'iban_mod97',
            'priority' => 100,
        ],
        [
            'id' => 'KREDI_KARTI_LUHN',
            'entity_type' => 'KREDI_KARTI',
            'regex' => '/\b(?:\d{4}[ -]?){3}\d{4}\b/u',
            'validator' => 'luhn_checksum',
            'priority' => 98,
        ],
        [
            'id' => 'TCKN_CHECKSUM',
            'entity_type' => 'TCKN',
            'regex' => '/\b[1-9]\d{10}\b/u',
            'validator' => 'tckn_checksum',
            'priority' => 95,
        ],
        [
            'id' => 'MERSIS_16',
            'entity_type' => 'MERSIS',
            'regex' => '/\b0\d{15}\b/u',
            'validator' => null,
            'priority' => 90,
        ],
        [
            'id' => 'VKN_10',
            'entity_type' => 'VKN',
            'regex' => '/\b[0-46-9]\d{9}\b/u',
            'validator' => 'vkn_checksum',
            'priority' => 85,
        ],
        [
            'id' => 'TELEFON_TR',
            'entity_type' => 'TELEFON',
            'regex' => '/(?:\+90|90|0)\s*5\d{2}\s*\d{3}\s*\d{2}\s*\d{2}/u',
            'validator' => null,
            'priority' => 84,
        ],
        [
            'id' => 'TELEFON_5XX',
            'entity_type' => 'TELEFON',
            'regex' => '/\b5\d{2}\s*\d{3}\s*\d{2}\s*\d{2}\b/u',
            'validator' => null,
            'priority' => 83,
        ],
        [
            'id' => 'TELEFON_LANDLINE',
            'entity_type' => 'TELEFON',
            'regex' => '/(?:\+90|90|0|0090)\s*[234]\d{2}\s*\d{3}\s*\d{2}\s*\d{2}/u',
            'validator' => null,
            'priority' => 79,
        ],
        [
            'id' => 'TELEFON_COMPACT',
            'entity_type' => 'TELEFON',
            'regex' => '/\b0?5\d{9}\b/u',
            'validator' => null,
            'priority' => 78,
        ],
        [
            'id' => 'EPOSTA_RFC',
            'entity_type' => 'EPOSTA',
            'regex' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u',
            'validator' => null,
            'priority' => 70,
        ],
        [
            'id' => 'PLAKA_CLASSIC',
            'entity_type' => 'PLAKA',
            'regex' => '/\b\d{2}\s*[A-Z]{1,3}\s*\d{2,4}\b/ui',
            'validator' => null,
            'priority' => 60,
        ],
    ],

    /**
     * İsim - Soyisim Redaksiyon Ayarları
     */
    'name_redaction' => [
        'enabled' => true,
        // Rol, etiket ve unvan kalıpları
        'roles' => [
            'Davacı', 'Davalı', 'Müşteki', 'Sanık', 'Şüpheli', 'Mağdur', 'Katılan',
            'Müdahil', 'Tanık', 'Mirasçı', 'Müvekkil', 'Borçlu', 'Alacaklı',
            'İhbar Olunan', 'Vasi', 'Kayyım', 'Kiracı', 'Kiraya Veren', 'Müteahhit',
            'İşveren', 'İşçi', 'Taraf', 'Başvuran', 'İtiraz Eden',
        ],
        'titles' => [
            'Av\.', 'Avukat', 'Hakim', 'Hâkim', 'Savcı', 'Dr\.', 'Doktor',
            'Prof\.\s*Dr\.', 'Doç\.\s*Dr\.', 'Bilirkişi', 'Noter', 'Uzman',
        ],
        'labels' => [
            'Adı\s+Soyadı', 'Ad\s+Soyad', 'İsim\s+Soyisim', 'İsim', 'Adı',
            'Vekili', 'Müdafii', 'İmza', 'İmzası', 'Yetkili',
        ],
        'salutations' => [
            'Sayın', 'Sn\.',
        ],
    ],

    /**
     * Madde/kanun numarası — redakte EDİLMEZ (negatif pattern)
     */
    'exclude_patterns' => [
        '/\bm\.\s*\d+/ui',
        '/\bmadde\s+\d+/ui',
        '/\d+\s*\/\s*\d+\s*(?:md\.?|gm\.?|em\.?)/ui',
    ],

    /**
     * İnsan İncelemesi (Human Review) anahtar kelimeleri
     */
    'human_review_keywords' => [
        'çocuk', 'sağlık', 'cinsel', 'şiddet', 'aile içi',
        'küçük yerleşim', 'tekil olay', 'mahkeme', 'ceza',
    ],
];
