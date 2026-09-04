# Redakte - PHP & Laravel Kişisel Veri Redaksiyon ve Anonimleştirme Paketi

[![Latest Version](https://img.shields.io/badge/version-v1.0.2-blue.svg?style=flat-square)](https://packagist.org/packages/mcakici/redakte)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-98%20passed-brightgreen.svg?style=flat-square)](#-testleri-çalıştırma)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-777bb4.svg?style=flat-square)](composer.json)

**Redakte**, metinlerde, hukuki belgelerde, veri tabanı modellerinde ve kullanıcı girdilerinde bulunan hassas kişisel verileri (KVKK / GDPR teknik uyumluluk süreçleri için) deterministik ve yüksek doğruluklu algoritmalarla tespit edip güvenli yer tutucularla (`⟦RDT:xxxx:TCKN:1⟧` veya `[TCKN_1]`) ya da kısmi maskeleme (`123*****890`) ile redakte eden bağımsız bir PHP & Laravel kütüphanesidir.

> [!NOTE]
> **Önemli Bilgilendirme:** Bu dokümantasyonda ve test senaryolarında yer alan tüm kişi isimleri (*Ahmet Yılmaz, Ali Veli vb.*), TCKN, IBAN, telefon, kredi kartı ve e-posta adresleri, kütüphanenin algoritmik doğrulamalarını (Luhn, Mod97, TCKN checksum vb.) göstermek amacıyla üretilmiş **tamamen sentetik / sahte (dummy) test verileridir**; gerçek kişi veya kurumlarla herhangi bir ilgisi bulunmamaktadır.

---

## 🚀 Öne Çıkan Yetenekler ve Özellik Matrisi

| Entity Türü | Tespit Türü | Doğrulayıcı / Algoritma | Kısmi Maskeleme | Geri Açılabilir (Reversible) | Durum |
|---|---|---|:---:|:---:|:---:|
| **TCKN** | Regex + Bağlam | Mod 10/11 Checksum Algoritması (0 ile başlayan adaylar dahil) | Evet | Evet | Kararlı (v1.0.2) |
| **VKN** | Regex + Bağlam | Resmi GİB Mod 10 Checksum (5xx dahil tüm 10 haneli VKN'ler) | Evet | Evet | Kararlı (v1.0.2) |
| **IBAN** | Regex + Bağlam | ISO 7064 Mod97 (26 karakter TR IBAN) | Evet | Evet | Kararlı (v1.0.2) |
| **TELEFON** | Tokenize + Regex | 10 farklı biçim (+90, 0090, 05xx, parantezli, sabit, 13 hane dahil) | Evet | Evet | Kararlı (v1.0.2) |
| **KREDİ KARTI** | Regex + Bağlam | Luhn (Mod 10) Algoritması (13-19 hane) | Evet | Evet | Kararlı (v1.0.2) |
| **KİŞİ ADI** | Çok Katmanlı | NVİ/TÜİK Sözlüğü + Hukuki Roller & Unvanlar (`A. Yılmaz` dahil) | Evet | Evet | Kararlı (v1.0.2) |
| **ADRES** | Etiket + Bileşen | Adres etiketi (`Adres:`) ve bileşenler (`Mah.`, `Cad.`), sınır kontrollü | Evet | Evet | Kararlı (v1.0.2) |
| **MERSİS** | Regex + Bağlam | 16 haneli sicil formatı ve etiket bağlamı | Evet | Evet | Kararlı (v1.0.2) |
| **E-POSTA** | Regex + Doğrulama | RFC uyumlu formatlar ve `filter_var` e-posta doğrulaması | Evet | Evet | Kararlı (v1.0.2) |
| **PLAKA** | Regex + Doğrulama | 01-81 il kodu doğrulaması ve Türkiye plaka formatı | Evet | Evet | Kararlı (v1.0.2) |
| **HUKUKİ NO** | Regex + Bağlam | `ESAS_NO`, `KARAR_NO`, `DOSYA_NO` (Etiketler korunur, sadece numara redakte edilir) | Evet | Evet | Kararlı (v1.0.2) |
| **IP ADRESİ** | Regex + Doğrulama | IPv4 ve IPv6 adres kalıpları (`filter_var` doğrulama denetimli) | Evet | Evet | Kararlı (v1.0.2) |

---

## 🔒 Güvenlik Sözleşmesi (Security Contract)

1. **Hassas Veri İzolasyonu (P0-01):**
   `$result->toArray()` ve `json_encode($result)` çıktıları varsayılan olarak **asla** `token_map` veya span'lardaki `original_value` değerlerini sızdırmaz. Benzer şekilde `RedactionMap::jsonSerialize()` yalnızca güvenli metadata döndürür. Orijinal hassas haritaya yalnızca açık ve bilinçli olarak `$result->toSensitiveArray()` veya `$map->toSensitiveJson()` çağrılarak erişilebilir.
2. **Tek Koordinat Alanı ve O(1) Haritalama (P0-02):**
   Tüm dedektörler normalize edilmiş metin üzerinde çalışır, tespit edilen tüm span'lar $O(1)$ harita tablosuyla orijinal metin koordinatlarına geri çevrilir (`Detect -> Resolve -> Apply`). NBSP, zero-width ve akıllı tırnak altında hiçbir ofset kayması yaşanmaz (`substr($original, $start, $len) === $originalValue`).
3. **128-Bit Token & Oturum Güvenliği (P0-07):**
   Varsayılan tokenlar en az 128-bit rastgele oturum anahtarlarıyla (`⟦RDT:xxxxxxxxxxxxxxxx:ENTITY:idx⟧`) üretilir. Orijinal metin içinde önceden bulunan sahte yer tutucularla çakışmalar deterministik olarak çözülür.
4. **Otomatik Çapraz Oturum (Cross-Session) Engeli:**
   `unmask()` işlemi sırasında namespaced tokenlardaki oturum kimliği ile harita oturumu eşleşmezse kütüphane otomatik olarak `UnsafeUnmaskException` fırlatır.
5. **Tek Yönlü Strateji İzolasyonu:**
   `PARTIAL`, `ASTERISK` ve `LABEL` stratejileri tek yönlü (irreversible) olduğu için haritada kişisel veriler biriktirilmez.
6. **Blade XSS Koruması (P0-08):**
   `@redakte` ve `@redaktePartial` direktifleri çıktıyı otomatik olarak `e()` (HTML escaping) ile sarmalar.
7. **Log Güvenliği (P0-09):**
   `RedakteLogProcessor`, Monolog `message`, `context` ve `extra` alanlarını kapsar; `Stringable`, `Throwable`, iç içe diziler ve döngüsel referansları güvenle temizler.

---

## 📦 Kurulum

### Composer ile Yükleme

```bash
composer require mcakici/redakte
```

### Konfigürasyonu Yayınlama (Laravel için İsteğe Bağlı)

```bash
php artisan vendor:publish --tag=redakte-config
```

---

## 💡 Kullanım Örnekleri

### 1. LLM & Yapay Zeka Çift Yönlü Maskeleme (Reversible Redaction)

Prompt'u OpenAI, Anthropic veya yerel bir LLM'e göndermeden önce kişisel verileri maskeleyip, AI yanıtındaki etiketleri tekrar orijinal verileriyle takas edebilirsiniz:

```php
use Redakte\Redakte;

$prompt = "Davacı Ahmet Yılmaz (TCKN: 43650391326), 0532 123 45 67 telefon numarasından arandı.";

// 1. LLM için maskele ve token haritasını al:
[$maskedPrompt, $map] = Redakte::maskForLLM($prompt);

// 2. Modeli çağırın (örnek AI yanıtı):
$aiResponse = "Özet: [KISI_1], [TCKN_1] kimlik numaralı olup [TELEFON_1] ile aranmıştır.";

// 3. Yanıtı orijinal haline geri çözün (De-anonymize / Unmask):
$originalResponse = Redakte::unmask($aiResponse, $map);
// Çıktı: "Özet: Ahmet Yılmaz, 43650391326 kimlik numaralı olup 0532 123 45 67 ile aranmıştır."
```

### 2. Çok Sayfalı Belgeler için Oturum Desteği (`RedactionSession`)

Birden fazla sayfa veya parçada aynı kişiye/numaraya aynı tokenın verilmesini sağlamak için oturum kullanabilirsiniz:

```php
use Redakte\Redakte;

$session = Redakte::session();

$page1 = Redakte::redact("Davacı Ahmet Yılmaz arandı.", [
    'session' => $session,
    'token_format' => 'namespaced',
]);

$page2 = Redakte::redact("Ahmet Yılmaz ile tekrar görüşüldü.", [
    'session' => $session,
    'token_format' => 'namespaced',
]);

// Her iki sayfada da Ahmet Yılmaz aynı tokenı (örn: ⟦RDT:7k3m2a:KISI:1⟧) alır.
echo $session->getMap()->unmask($page2->redactedText);
```

---

### 3. Kısmi (Okunabilir) Maskeleme (`partial`)

Metnin akışını ve okunabilirliğini bozmadan güvenlik sağlamak için:

```php
use Redakte\Redakte;

$metin = "Sayın Ahmet Yılmaz, TCKN: 43650391326, IBAN: TR33 0006 1005 1978 6457 8413 26, E-posta: ahmet@example.com";

echo Redakte::partial($metin);
// Çıktı: "Sayın A**** Y*****, TCKN: 436*****326, IBAN: TR33 **** **** **** **** **** 26, E-posta: a***t@example.com"
```

---

### 4. Güvenlik ve Doğrulama Politikaları (`RedactionPolicy`)

Doğrulanamayan (checksum hatası alan) ancak hassas görünen adayların nasıl işleneceğini seçebilirsiniz:

```php
use Redakte\Policy\RedactionPolicy;
use Redakte\RedactionOptions;
use Redakte\Redakte;

// 1. Strict (Varsayılan): Checksum geçersiz olsa bile biçime uyan veya etiketli adayları maskeler ve uyarı üretir
$result = Redakte::redact($text, new RedactionOptions(policy: RedactionPolicy::strict()));

// 2. Balanced: Güçlü etiket varsa (örn: "TCKN: 12345678901") geçersiz adayı da maskeler, etiketsizse korur
$result = Redakte::redact($text, new RedactionOptions(policy: RedactionPolicy::balanced()));

// 3. Validated Only: Yalnızca checksum kontrolünden geçenleri maskeler
$result = Redakte::redact($text, new RedactionOptions(policy: RedactionPolicy::validatedOnly()));
```

---

### 5. Laravel Eloquent Modellerinde Kullanım (`HasRedaction`)

Modellerinizdeki hassas alanları sıkı bir allowlist ile redakte edilmiş olarak sunabilirsiniz:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Redakte\Laravel\Traits\HasRedaction;

class Decision extends Model
{
    use HasRedaction;

    // Yalnızca bu dizide yer alan alanların redacted_* getter'ına izin verilir (P1-21)
    protected array $redactable = ['reasoning', 'internal_notes'];
}

// Kullanım:
$decision = Decision::find(1);

// İzin verilen alan:
echo $decision->redacted_reasoning;

// Allowlist dışındaki alan null döner (yetkisiz sızıntı engellenir):
echo $decision->redacted_password; // null

// API yanıtı için topluca redakte edilmiş dizi:
return response()->json($decision->toRedactedArray());
```

---

### 6. Laravel Log Redaksiyonu (Monolog Processor)

Log dosyalarınıza kazara TCKN, IBAN, kredi kartı, telefon veya exception sızmasını engellemek için:

```php
// config/logging.php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'processors' => [\Redakte\Laravel\Logging\RedakteLogProcessor::class],
    ],
],
```

---

### 7. Laravel Blade Direktifleri

Blade şablonlarında otomatik HTML escaping (XSS koruması) ile redaksiyon yapmak için:

```blade
{{-- Tam redaksiyon ([TCKN_1] vb.) - Otomatik escaped --}}
@redakte($user->bio)

{{-- Kısmi maskeleme (123*****890 vb.) - Otomatik escaped --}}
@redaktePartial($user->comment)
```

---

## 🔍 Sonuç Nesnesi (`RedactionResult`)

| Özellik / Metot | Tip | Açıklama |
|---|---|---|
| `$result->redactedText` | `string` | Maskelenmiş nihai metin |
| `$result->reportSummary` | `string` | Türkçe kullanıcı dostu özet metin |
| `$result->replacementsByType` | `array<string, int>` | Entity türü başına değiştirilme adedi |
| `$result->totalReplacements()` | `int` | Toplam redakte edilen veri sayısı |
| `$result->status` | `string` | İşlem durumu (`complete`, `review_required`, `failed`) |
| `$result->riskLevel` | `string` | Risk seviyesi (`low`, `medium`, `high`) |
| `$result->warnings` | `list<RedactionWarning>` | Makine tarafından okunabilir kodlu denetim uyarıları |
| `$result->unmask($targetText)` | `string` | Metindeki token'ları orijinal değerlerle geri takas eder (Yalnızca TAG) |
| `$result->toArray()` | `array` | **Güvenli** dizi çıktısı (`token_map` ve `original_value` içermez) |
| `$result->toSafeArray()` | `array` | Açık güvenli dizi çıktısı |
| `$result->toSensitiveArray()` | `array` | `token_map` ve span orijinal değerlerini içeren **açık** çıktı |

---

## 🧪 Testleri Çalıştırma

Tüm birim, güvenlik ve entegrasyon testlerini PHPUnit ile çalıştırabilirsiniz:

```bash
composer test
# veya
vendor/bin/phpunit
```

---

## 📄 Lisans

Bu paket MIT lisansı altında açık kaynak olarak lisanslanmıştır.
