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
    'policy_version' => '1.0.3',

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
        'IP_ADRESI' => ['label' => 'IP adresi', 'label_plural' => 'IP adresi'],
        // Aşağıdaki anlamsal türlerin otomatik tespiti isteğe bağlı LLM katmanına aittir.
        'DOGUM_TARIHI' => ['label' => 'doğum tarihi', 'label_plural' => 'doğum tarihi'],
        'KIMLIK_NO' => ['label' => 'pasaport/ehliyet/kimlik belge numarası', 'label_plural' => 'kimlik belge numarası'],
        'HESAP_NO' => ['label' => 'banka/finans hesap numarası', 'label_plural' => 'hesap numarası'],
        'MUSTERI_NO' => ['label' => 'müşteri/sicil/üyelik numarası', 'label_plural' => 'müşteri/sicil numarası'],
        'KULLANICI_ADI' => ['label' => 'kişisel kullanıcı adı', 'label_plural' => 'kullanıcı adı'],
        'SIFRE' => ['label' => 'parola/erişim sırrı', 'label_plural' => 'parola/erişim sırrı'],
        'KONUM' => ['label' => 'kişiye ait hassas konum', 'label_plural' => 'konum bilgisi'],
        'SAGLIK' => ['label' => 'kişiye ait sağlık bilgisi', 'label_plural' => 'sağlık bilgisi'],
        'BIYOMETRIK' => ['label' => 'kişiye ait biyometrik veri', 'label_plural' => 'biyometrik veri'],
        'GENETIK' => ['label' => 'kişiye ait genetik veri', 'label_plural' => 'genetik veri'],
        'KOKEN' => ['label' => 'kişiye ait ırk/etnik köken bilgisi', 'label_plural' => 'köken bilgisi'],
        'INANC' => ['label' => 'kişiye ait din/mezhep/felsefi inanç', 'label_plural' => 'inanç bilgisi'],
        'SIYASI_GORUS' => ['label' => 'kişiye ait siyasi düşünce', 'label_plural' => 'siyasi görüş bilgisi'],
        'UYELIK' => ['label' => 'kişiye ait dernek/vakıf/sendika üyeliği', 'label_plural' => 'üyelik bilgisi'],
        'CINSEL_HAYAT' => ['label' => 'kişiye ait cinsel hayat bilgisi', 'label_plural' => 'cinsel hayat bilgisi'],
        'CEZA_BILGISI' => ['label' => 'kişiye ait ceza mahkumiyeti/güvenlik tedbiri', 'label_plural' => 'ceza/güvenlik tedbiri bilgisi'],
        'KILIK_KIYAFET' => ['label' => 'kişiye ait kılık/kıyafet bilgisi', 'label_plural' => 'kılık/kıyafet bilgisi'],
        'FINANSAL_BILGI' => ['label' => 'kişiye ait gelir/borç/finansal durum', 'label_plural' => 'finansal bilgi'],
        'DIGER_KISISEL' => ['label' => 'diğer türlere girmeyen kişiyi tanımlayıcı veya kişiye atfedilen özel bilgi', 'label_plural' => 'diğer kişisel bilgi'],
    ],

    /**
     * Varsayılan güvenlik ve doğrulama politikası: 'balanced' | 'strict' | 'validated_only' | 'logs'
     */
    'policy' => 'balanced',

    /**
     * Token formatı: 'namespaced' (⟦RDT:xxxx:TCKN:1⟧) veya 'legacy' ([TCKN_1])
     */
    'token_format' => 'namespaced',

    /**
     * Varsayılan maskeleme stratejisi: 'tag' | 'partial' | 'asterisk' | 'label'
     */
    'default_strategy' => 'tag',

    /**
     * İsteğe bağlı Ollama native /api/chat desteği.
     * enabled=null: endpoint + model verilince açılır; boş varsayılanlarda KAPALIDIR.
     * enabled=false: bilgiler verilse de kapalı. Model seçenekleri options içinde aktarılır.
     * Bu URL'leri ve başlıkları yalnızca güvenilir uygulama yapılandırmasından alın.
     */
    'llm' => [
        'enabled' => null,
        'endpoint' => null, // Örn. http://127.0.0.1:11434/api/chat
        'model' => null,
        'system_prompt' => \Redakte\Llm\LlmOptions::DEFAULT_SYSTEM_PROMPT,
        'options' => [
            'num_ctx' => 8192,
            'num_predict' => 2048,
            'temperature' => 0,
            // 'num_gpu' => -1, 'top_p' => 0.9, 'seed' => 42, ...
        ],
        'think' => null, // null: model varsayılanı; bool veya /api/show thinking.values seviyesi
        'keep_alive' => '5m',
        'headers' => [],
        'timeout_seconds' => 120,
        'total_timeout_seconds' => 600,
        'max_response_bytes' => 1048576,
        'chunk_size' => null, // null: num_ctx bütçesine göre otomatik; sayı verilirse byte üst sınırı
        'chunk_overlap' => null, // null: parça boyutuna göre otomatik
        'context_safety_margin' => 0.05,
        'estimated_bytes_per_token' => 2.0, // Tokenizer yoksa başlangıç tahmini; kesin token sayımı değil
        'output_token_reserve' => 2048, // num_predict=-1 ise planlama rezervi
        'max_chunks' => 512,
        'entity_types' => null, // null: kayıtlı ve çağrıda izin verilen bütün türler
        'show_endpoint' => null, // null: chat URL sonundaki /chat -> /show
    ],

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
        // NVİ / TÜİK isim sözlüğü ile cümlenin her yerindeki yalın isimlerin tespiti (Örn: Pınar İpek Parlak)
        'gazetteer' => true,
        // Sözlüğe eklenecek özel / kurumunuza özgü adlar
        'custom_names' => [],
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
