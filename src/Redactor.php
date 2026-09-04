<?php

declare(strict_types=1);

namespace Redakte;

use Redakte\Policy\RedactionPolicy;
use Redakte\Redactors\ModelIdentityRedactor;
use Redakte\Redactors\NameRedactor;
use Redakte\Token\RedactionSession;

/**
 * Redakte paketinin ana orkestrasyon ve giriş kapısı (Gateway / Manager) sınıfı.
 *
 * Tüm redaksiyon akışını koordine eder ve nihai RedactionResult nesnesini üretir.
 */
class Redactor
{
    public function __construct(
        private RedactionService $service,
        private ?NameRedactor $nameRedactor = null,
        private ?RedactionValidator $validator = null,
        private ?ReportSummaryBuilder $reportBuilder = null,
        private ?ModelIdentityRedactor $modelIdentityRedactor = null,
        private ?PatternRegistry $registry = null,
    ) {
        $this->modelIdentityRedactor ??= new ModelIdentityRedactor();
        $this->registry ??= $this->service->getRegistry();
    }

    /**
     * Metni tarayarak hassas kişisel verileri tek bir koordinat sisteminde redakte eder (P0-02).
     *
     * @param string $text Redakte edilecek ham metin
     * @param RedactionOptions|array<string, mixed>|null $options Ayarlar
     */
    public function redact(string $text, RedactionOptions|array|null $options = null): RedactionResult
    {
        $resolvedOptions = $this->resolveOptions($options);

        $result = $this->service->redact($text, $resolvedOptions);

        // İkinci geçiş ve sızıntı denetimi (aktifse)
        if ($resolvedOptions->runValidator && $this->validator !== null) {
            $result = $this->validator->validate($text, $result, $resolvedOptions);
        }

        return $result;
    }

    /**
     * Yeni bir çok parçalı/oturum bazlı redaksiyon oturumu başlatır (P2-04).
     */
    public function session(?string $sessionId = null): RedactionSession
    {
        return RedactionSession::create($sessionId);
    }

    /**
     * Sadece redakte edilmiş temiz metni döndürür.
     */
    public function clean(string $text, RedactionOptions|array|null $options = null): string
    {
        return $this->redact($text, $options)->redactedText;
    }

    /**
     * Kısmi (okunabilir) maskelenmiş temiz metni döndürür.
     * Örn: 123*****890, TR33 **** 12, A**** Y*****
     */
    public function partial(string $text, RedactionOptions|array|null $options = null): string
    {
        $resolved = $this->resolveOptions($options);
        $partialOptions = new RedactionOptions(
            mode: $resolved->mode,
            entityTypes: $resolved->entityTypes,
            runValidator: $resolved->runValidator,
            redactNames: $resolved->redactNames,
            checkHumanReview: $resolved->checkHumanReview,
            strategy: MaskStrategy::PARTIAL,
            policy: $resolved->policy,
            tokenFormat: $resolved->tokenFormat,
            session: $resolved->session,
            strictMode: $resolved->strictMode,
        );

        return $this->redact($text, $partialOptions)->redactedText;
    }

    /**
     * LLM / Yapay Zeka promptları için güvenli çift yönlü (reversible) maskeleme yapar.
     *
     * @return array{0: string, 1: RedactionMap, text: string, map: RedactionMap}
     */
    public function maskForLLM(string $text, RedactionOptions|array|null $options = null): array
    {
        $resolved = $this->resolveOptions($options);
        $tagOptions = new RedactionOptions(
            mode: $resolved->mode,
            entityTypes: $resolved->entityTypes,
            runValidator: $resolved->runValidator,
            redactNames: $resolved->redactNames,
            checkHumanReview: $resolved->checkHumanReview,
            strategy: MaskStrategy::TAG,
            policy: $resolved->policy,
            tokenFormat: $resolved->tokenFormat,
            session: $resolved->session,
            strictMode: $resolved->strictMode,
        );

        $result = $this->redact($text, $tagOptions);
        $map = $result->map ?? new RedactionMap();

        return [
            0 => $result->redactedText,
            1 => $map,
            'text' => $result->redactedText,
            'map' => $map,
        ];
    }

    /**
     * Token içeren bir metni haritadaki orijinal verileriyle takas ederek çözer.
     */
    public function unmask(string $text, RedactionMap|array $map): string
    {
        if (!$map instanceof RedactionMap) {
            $map = RedactionMap::fromArray($map);
        }

        return $map->unmask($text);
    }

    /**
     * Yapay zeka model kimliği sızıntılarını temizler (ayrık yardımcı katman)
     *
     * @return array{text: string, replaced: int}
     */
    public function redactModelIdentity(string $text): array
    {
        return $this->modelIdentityRedactor->redact($text);
    }

    /**
     * Seçenekleri normalize ve hiyerarşik olarak valide eder
     */
    private function resolveOptions(RedactionOptions|array|null $options): RedactionOptions
    {
        return OptionsFactory::create($options, $this->registry ?? $this->service->getRegistry());
    }
}
