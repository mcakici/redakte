<?php

declare(strict_types=1);

namespace Redakte;

use InvalidArgumentException;
use Redakte\Policy\RedactionPolicy;
use Redakte\Token\RedactionSession;

/**
 * Redaksiyon çalışma seçenekleri
 */
final readonly class RedactionOptions
{
    public MaskStrategy $strategy;
    public RedactionPolicy $policy;

    /**
     * @param string $mode 'redacted' veya 'mask' (varsayılan 'redacted')
     * @param list<string>|null $entityTypes Null ise tüm türler taranır, dizi verilirse sadece o türler taranır
     * @param bool $runValidator İkinci geçiş ve sızıntı kontrolü çalıştırılsın mı
     * @param bool $redactNames İsim-soyisim / rol / unvan tespiti aktif olsun mu
     * @param bool $checkHumanReview İnsan incelemesi gerektiren kelimeler kontrol edilsin mi
     * @param MaskStrategy|string $strategy Maskeleme stratejisi ('tag', 'partial', 'asterisk', 'label')
     * @param RedactionPolicy|string $policy Redaksiyon güvenlik politikası ('strict', 'balanced', 'validated_only', 'logs')
     * @param string $tokenFormat 'namespaced' (⟦RDT:xxxx:ENTITY:idx⟧) veya 'legacy' ([ENTITY_idx])
     * @param RedactionSession|null $session Çok sayfalı/oturum bazlı token eşleme oturumu
     * @param bool $strictMode Bilinmeyen tür veya seçeneklerde hata fırlatılsın mı
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $mode = 'redacted',
        public ?array $entityTypes = null,
        public bool $runValidator = true,
        public bool $redactNames = true,
        public bool $checkHumanReview = true,
        MaskStrategy|string $strategy = MaskStrategy::TAG,
        RedactionPolicy|string $policy = RedactionPolicy::BALANCED,
        public string $tokenFormat = 'legacy',
        public ?RedactionSession $session = null,
        public bool $strictMode = false,
    ) {
        if (is_string($strategy)) {
            $parsedStrategy = MaskStrategy::tryFrom(strtolower($strategy));
            if ($parsedStrategy === null) {
                throw new InvalidArgumentException(sprintf('Geçersiz maskeleme stratejisi: "%s". İzin verilenler: tag, partial, asterisk, label.', $strategy));
            }
            $this->strategy = $parsedStrategy;
        } else {
            $this->strategy = $strategy;
        }

        $this->policy = is_string($policy) ? RedactionPolicy::fromString($policy) : $policy;
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Sadece belirli türleri redakte etmek için seçenek nesnesi
     *
     * @param list<string> $types
     */
    public static function only(array $types): self
    {
        return new self(entityTypes: $types);
    }

    /**
     * Kısmi (okunabilir) maskeleme seçeneği (Örn: 123*****890, TR33 **** 12)
     *
     * @param list<string>|null $types
     */
    public static function partial(?array $types = null): self
    {
        return new self(entityTypes: $types, strategy: MaskStrategy::PARTIAL);
    }

    /**
     * Yıldız (tam) maskeleme seçeneği (Örn: ***********)
     *
     * @param list<string>|null $types
     */
    public static function asterisk(?array $types = null): self
    {
        return new self(entityTypes: $types, strategy: MaskStrategy::ASTERISK);
    }

    /**
     * Sayı indeksi içermeyen genel etiket seçeneği (Örn: [TCKN], [IBAN])
     *
     * @param list<string>|null $types
     */
    public static function label(?array $types = null): self
    {
        return new self(entityTypes: $types, strategy: MaskStrategy::LABEL);
    }
}
