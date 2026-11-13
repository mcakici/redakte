# Redakte - PHP & Laravel Kişisel Veri Redaksiyon ve Anonimleştirme Paketi

[![Latest Version](https://img.shields.io/packagist/v/mcakici/redakte.svg?style=flat-square&label=version)](https://packagist.org/packages/mcakici/redakte)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Tests](https://github.com/mcakici/redakte/actions/workflows/tests.yml/badge.svg)](https://github.com/mcakici/redakte/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-777bb4.svg?style=flat-square)](composer.json)

**Redakte**, metinlerde, hukuki belgelerde, veri tabanı modellerinde ve kullanıcı girdilerinde bulunan hassas kişisel verileri (KVKK / GDPR teknik uyumluluk süreçleri için) deterministik ve yüksek doğruluklu algoritmalarla tespit edip güvenli yer tutucularla (`⟦RDT:xxxx:TCKN:1⟧` veya `[TCKN_1]`) ya da kısmi maskeleme (`123*****890`) ile redakte eden bağımsız bir PHP & Laravel kütüphanesidir.

> [!NOTE]
> **Önemli Bilgilendirme:** Bu dokümantasyonda ve test senaryolarında yer alan tüm kişi isimleri (*Ahmet Yılmaz, Ali Veli vb.*), TCKN, IBAN, telefon, kredi kartı ve e-posta adresleri, kütüphanenin algoritmik doğrulamalarını (Luhn, Mod97, TCKN checksum vb.) göstermek amacıyla üretilmiş **tamamen sentetik / sahte (dummy) test verileridir**; gerçek kişi veya kurumlarla herhangi bir ilgisi bulunmamaktadır.

---

## 🚀 Öne Çıkan Yetenekler ve Özellik Matrisi

| Entity Türü | Tespit Türü | Doğrulayıcı / Algoritma | Kısmi Maskeleme | Geri Açılabilir (Reversible) | Durum |
|---|---|---|:---:|:---:|:---:|
| **TCKN** | Regex + Bağlam | Mod 10/11 Checksum Algoritması (0 ile başlayan adaylar dahil) | Evet | Evet | Destekleniyor |
| **VKN** | Regex + Bağlam | Resmi GİB Mod 10 Checksum (5xx dahil tüm 10 haneli VKN'ler) | Evet | Evet | Destekleniyor |
| **IBAN** | Regex + Bağlam | ISO 7064 Mod97 (26 karakter TR IBAN) | Evet | Evet | Destekleniyor |
| **TELEFON** | Tokenize + Regex | 10 farklı biçim (+90, 0090, 05xx, parantezli, sabit, 13 hane dahil) | Evet | Evet | Destekleniyor |
| **KREDİ KARTI** | Regex + Bağlam | Luhn (Mod 10) Algoritması (13-19 hane) | Evet | Evet | Destekleniyor |
| **KİŞİ ADI** | Çok Katmanlı | NVİ/TÜİK Sözlüğü + Hukuki Roller & Unvanlar (`A. Yılmaz` dahil) | Evet | Evet | Destekleniyor |
| **ADRES** | Etiket + Bileşen | Adres etiketi (`Adres:`) ve bileşenler (`Mah.`, `Cad.`), sınır kontrollü | Evet | Evet | Destekleniyor |
| **MERSİS** | Regex + Bağlam | 16 haneli sicil formatı ve etiket bağlamı | Evet | Evet | Destekleniyor |
| **E-POSTA** | Regex + Doğrulama | RFC uyumlu formatlar ve `filter_var` e-posta doğrulaması | Evet | Evet | Destekleniyor |
| **PLAKA** | Regex + Doğrulama | 01-81 il kodu doğrulaması ve Türkiye plaka formatı | Evet | Evet | Destekleniyor |
| **HUKUKİ NO** | Regex + Bağlam | `ESAS_NO`, `KARAR_NO`, `DOSYA_NO` (Etiketler korunur, sadece numara redakte edilir) | Evet | Evet | Destekleniyor |
| **IP ADRESİ** | Regex + Doğrulama | IPv4 ve IPv6 adres kalıpları (`filter_var` doğrulama denetimli) | Evet | Evet | Destekleniyor |

---

## 🔒 Güvenlik Sözleşmesi (Security Contract)

1. **Hassas Veri İzolasyonu:**
   `$result->toArray()` ve `json_encode($result)` çıktıları varsayılan olarak **asla** `token_map` veya span'lardaki `original_value` değerlerini sızdırmaz. Benzer şekilde `RedactionMap::jsonSerialize()` yalnızca güvenli metadata döndürür. Orijinal hassas haritaya yalnızca açık ve bilinçli olarak `$result->toSensitiveArray()` veya `$map->toSensitiveJson()` çağrılarak erişilebilir.
2. **Tek Koordinat Alanı ve O(1) Haritalama:**
   Tüm dedektörler normalize edilmiş metin üzerinde çalışır, tespit edilen tüm span'lar $O(1)$ harita tablosuyla orijinal metin koordinatlarına geri çevrilir (`Detect -> Resolve -> Apply`). NBSP, zero-width ve akıllı tırnak altında hiçbir ofset kayması yaşanmaz (`substr($original, $start, $len) === $originalValue`).
3. **128-Bit Token & Oturum Güvenliği:**
   Varsayılan tokenlar en az 128-bit rastgele oturum anahtarlarıyla (`⟦RDT:xxxxxxxxxxxxxxxx:ENTITY:idx⟧`) üretilir. Orijinal metin içinde önceden bulunan sahte yer tutucularla çakışmalar deterministik olarak çözülür.
4. **Otomatik Çapraz Oturum (Cross-Session) Engeli:**
   `unmask()` işlemi sırasında namespaced tokenlardaki oturum kimliği ile harita oturumu eşleşmezse kütüphane otomatik olarak `UnsafeUnmaskException` fırlatır.
5. **Tek Yönlü Strateji İzolasyonu:**
   `PARTIAL`, `ASTERISK` ve `LABEL` stratejileri tek yönlü (irreversible) olduğu için haritada kişisel veriler biriktirilmez.
6. **Blade XSS Koruması:**
   `@redakte` ve `@redaktePartial` direktifleri çıktıyı otomatik olarak `e()` (HTML escaping) ile sarmalar.
7. **Log Güvenliği:**
   `RedakteLogProcessor`, Monolog `message`, `context` ve `extra` alanlarını kapsar; `Stringable`, `Throwable`, iç içe diziler ve döngüsel referansları güvenle temizler.

---

## 📦 Kurulum

### 1.0.7: Ortak plaka kuralları ve kaydedilmiş sonuçlar

Plaka tespiti, LLM adaylarının kontrolü ve varsayılan kalan-risk taraması aynı
`Redakte\Support\PlateContext` kurallarını kullanır. `24 ve 25` gibi sayı grupları
korunur; `Plaka: 24 VE 25` ve `24 ve 25 plakalı araç` bağlamlarında plaka maskelenir.
Belirsiz büyük harfli gruplar ek bir madde/tutar bağlamı olmadan maskeli kalır.

Arayüzler tespiti tekrar etmek yerine `RedactionResult` içindeki metin ve byte
aralıklarını kullanabilir. Önceden kaydedilen sonuçlar için aynı paketin düzeltmesi:

```php
$types = [];
foreach ($result->getTokenMap() as $token => $_) {
    $types[$token] = $result->map?->getType($token) ?? 'PII';
}
$snapshot = [
    'hash' => hash('sha256', $source),
    'text' => $result->redactedText,
    'map' => $result->getTokenMap(),
    'types' => $types,
    'spans' => array_map(static fn ($span) => [
        'start' => $span->startOffset,
        'end' => $span->endOffset,
        'token' => $span->replacement,
    ], $result->spans),
];
$snapshot = \Redakte\Support\RedactionSnapshotRepair::apply($snapshot, $source);
```

Bu yardımcı, geri açılabilir TAG sonuçlarının sunucuda tutulan güvenilir snapshot'ları
içindir. `map` ham kişisel veri içerir; istemciye gönderilmemeli ve güvenli saklanmalıdır.
Kaynak verilmezse haritadan yeniden oluşturulur ve hash doğrulanır. Eksik, çakışan veya
metinle uyuşmayan aralıklar düzeltilmez. İşlem ağ/LLM çağrısı yapmaz; aynı tokenın yalnız
hatalı kullanımını açar. Yayımlanmış eski `config/redakte.php` içindeki varsayılan
`PLAKA_CLASSIC` regex'i tutuluyorsa yeni paket varsayılanıyla güncellenmelidir.

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

### İsteğe Bağlı Ollama ile Tespit

LLM desteği varsayılan olarak kapalıdır. `endpoint` ve `model` birlikte verilirse mevcut kurallara LLM tespitleri eklenir. Çağrıda `llm => false` veya yapılandırmada `enabled => false` kullanarak kapatabilirsiniz.

```php
use Redakte\Redakte;

$result = Redakte::redact($text, [
    'llm' => [
        'endpoint' => 'http://127.0.0.1:11434/api/chat',
        'model' => 'qwen3:8b', // Kendi kurulu modelinizin adı
        'system_prompt' => 'Kişisel verileri ve kişiye atfedilen hassas bilgileri tespit et.',
        'think' => false,
        'options' => [
            'num_ctx' => 16384,
            'num_predict' => 4096,
            'num_gpu' => -1,
            'temperature' => 0,
            'top_p' => 0.9,
            'seed' => 42,
        ],
        'context_safety_margin' => 0.05,
    ],
]);

echo $result->redactedText;
$ruleOnly = Redakte::redact($text, ['llm' => false]);
```

Genel ayarlar saf PHP'de `Redakte::create(['llm' => [...]])`, Laravel'de `config/redakte.php` üzerinden verilir. Çağrı ayarları önceliklidir; `options` ve `headers` anahtar bazında birleşir. `clean()`, `partial()`, `maskForLLM()` ve `new RedactionOptions(llm: [...])` aynı ayarları destekler. `env()` ile alınan sayıları `(int)` / `(float)`, bayrakları boolean olarak aktarın.

| LLM ayarı | Varsayılan / kullanım |
|---|---|
| `enabled` | `null`: endpoint ve model varsa aç; `false`: kapat; `true`: bağlantı bilgilerini zorunlu tut |
| `endpoint`, `model` | Tam Ollama native chat URL ve model adı; varsayılan boş |
| `system_prompt` | Değiştirilebilir kişisel veri tespit talimatı |
| `options` | `num_ctx=8192`, `num_predict=2048`, `temperature=0`; ek model seçenekleri doğrudan aktarılır |
| `think` | `null`: model varsayılanı; boolean veya modelin desteklediği seviye |
| `keep_alive` | `5m`; modelin bellekte kalma süresi |
| `headers` | Ek HTTP başlıkları; örneğin `Authorization` |
| `timeout_seconds` | Her HTTP isteği için 120 saniye |
| `total_timeout_seconds` | Bir LLM tespit işlemi için toplam 600 saniye |
| `context_safety_margin` | Context bütçesinde %5 pay (`0.05`) |
| `chunk_size`, `chunk_overlap` | `null`: otomatik; sayı verilirse UTF-8 byte üst sınırı / örtüşme |
| `max_chunks` | 512; uyarlanan plan dahil parça sınırı |
| `max_response_bytes` | Her HTTP yanıtı için 1048576 byte |
| `entity_types` | `null`: bütün kayıtlı türler; örn. `['KISI', 'TCKN', 'SAGLIK']` ile daraltılabilir |
| `show_endpoint` | Düşünme kontrolü için otomatik `/api/show`; özel proxy adresi de verilebilir |
| `estimated_bytes_per_token` | Otomatik planlamada başlangıç tahmini: 2; kesin tokenizer sayımı değildir |
| `output_token_reserve` | `num_predict=-1` kullanıldığında planlama için 2048 token rezerv |

`options` içine `num_thread`, `top_k`, `min_p`, `repeat_penalty`, `stop` ve diğer özel model seçenekleri verilebilir. Bir seçeneğin etkili olması Ollama sürümünün ve modelin desteğine bağlıdır; bilinmeyen seçenekler sunucu tarafından yok sayılabilir. Adaptör `/api/chat` kullanır; `/v1/chat/completions` protokolü değildir.

**Uzun metinler:** Parçalar `num_ctx`, sistem talimatı, çıktı bütçesi ve güvenli paya göre otomatik planlanır; UTF-8 ve mümkün olduğunda kelime/satır sınırları korunur. Context veya çıktı sınırı bildirilirse ilgili parça örtüşmeli olarak küçültülüp en fazla dört seviye yeniden denenir. Sonuçlar orijinal metin konumlarında birleştirilir. Bütçe token tahminine dayanır; sınırsız uzunluk veya modelin gerçek context kapasitesinin üstüne çıkma garantisi vermez. Büyük işleri web isteği yerine kuyrukta çalıştırın; toplam süre ve parça sınırlarını ihtiyacınıza göre ayarlayın.

**Kaynak eşleştirme:** Prompt, farklı ve tekrar eden değerler için somut JSON örnekleri içerir. Modelin `occurrence` sayacına güvenilmez: kaynakta doğrulanan değerin kelime/sayı sınırlarına uyan tüm tekrarları maskelenir. Kaynakta bulunmayan adayda yalnız ilgili pencere örtüşmeli küçültülerek baştan taranır; kabul edilmiş pencereler tekrar gönderilmez. Bu kurtarma da toplam süre, `max_chunks` ve dört seviye sınırlarına tabidir. Doğrulanamayan aday sessizce atılmaz; kurtarma başarısızsa `LlmSourceMismatchException` (`LlmDetectionException` alt türü) fırlatılır ve kısmi sonuç dönmez.

**Düşünme ayarları:** `thinking`, `enable_thinking` ve `reasoning_effort` takma adları `think` alanına dönüştürülür; `options` içine konmaz. `reasoning_effort => 'none'` düşünmeyi kapatır. Açık tercihler `/api/show` içindeki `thinking.values` ile kontrol edilir; çelişen veya desteklenmeyen ayarlar hata verir. Metadata sunmayan eski sunucularda boolean desteklenir, isimli seviyeler reddedilir. Desteklenen değerler `(new \Redakte\Llm\OllamaClient())->inspectModel($llmOptions)` ile görülebilir. [Ollama thinking](https://docs.ollama.com/capabilities/thinking), [model seçenekleri](https://docs.ollama.com/modelfile).

**Tespit kapsamı:** Mevcut kişi, TCKN, iletişim ve adres türlerine ek olarak sağlık, kimlik belgesi, finans, konum ve diğer hassas kişisel bilgiler için anlamsal türler bulunur. Kuruma özel türler `entity_types` yapılandırmasına eklenebilir. `only`, `names` ve `exclude_patterns` filtreleri LLM için de geçerlidir. `validated_only` politikası, checksum gibi doğrulaması olmayan anlamsal adayları kabul etmez. LLM tarafından uygulanan tespitler `review_required` ve `LLM_SUGGESTION_APPLIED` ile işaretlenir. Tüm kişisel verilerin hatasız bulunacağı garanti edilmez; kritik çıktıları inceleyin.

HTTP hatası, zaman aşımı, geçersiz yanıt veya kaynak metinle eşleşmeyen tespitte `LlmDetectionException` fırlatılır; kısmi tarama başarı sayılmaz. Yerleşik istemci `allow_url_fopen` gerektirir. Laravel'de `LlmTransportInterface` farklı bir HTTP istemcisiyle değiştirilebilir.

LLM açıldığında normalize metin seçtiğiniz endpoint'e gönderilir. Bağlantı ayarlarını güvenilir uygulama yapılandırmasından alın. Tamamen yerel kullanım için yerel model seçin ve Ollama'yı `OLLAMA_NO_CLOUD=1` ile çalıştırın. [Ollama yerel kullanım](https://docs.ollama.com/faq).

**Hibrit kapsam:** Politikanın kabul ettiği bir LLM aralığının yalnız bir bölümünü kapatan daha kısa bir kural, kalan hassas metni açık bırakamaz. Gerektiğinde çakışan aralıklar tek yer tutucuda birleştirilir. Tam kapsamada özgül deterministik tür korunur. Bu güvence yalnız tespit edilmiş ve politika tarafından kabul edilmiş aralıklar içindir; modelin kaçırdığı verileri bulma garantisi değildir.

**Maskeleme ve anonimlik:** Geri döndürülebilir token haritası, anonimlik garantisi sağlamaz. Haritanın yalnız sizin sunucunuzda tutulması veya silinmesi de tek başına anonimliği kanıtlamaz; kalan olay ayrıntılarından yeniden tanımlama riski ayrıca değerlendirilmelidir. Bu paket hukuki uygunluk veya bütün kişisel verilerin bulunması garantisi vermez. Hukuki değerlendirme için gerekli genel sağlık/olay bilgilerini korumak isterseniz uygulamanızda izin verilen tür listesini açıkça belirleyin; bunu tam anonimleştirme olarak adlandırmayın.

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

    // Yalnızca bu dizide yer alan alanların redacted_* getter'ına izin verilir
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
