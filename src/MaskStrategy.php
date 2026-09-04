<?php

declare(strict_types=1);

namespace Redakte;

/**
 * Maskeleme stratejileri
 */
enum MaskStrategy: string
{
    /** [TCKN_1], [KISI_1], [IBAN_1] şeklinde indeksli yer tutucular (Varsayılan ve LLM için ideal) */
    case TAG = 'tag';

    /** 123*****890, TR33 **** 12, a***@domain.com şeklinde kısmi okunabilir maskeleme */
    case PARTIAL = 'partial';

    /** *********** şeklinde karakter sayısınca tam maskeleme */
    case ASTERISK = 'asterisk';

    /** [TCKN], [IBAN], [KİŞİ] şeklinde indeks numarası olmayan genel etiket */
    case LABEL = 'label';
}
