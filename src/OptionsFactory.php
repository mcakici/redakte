<?php

declare(strict_types=1);

namespace Redakte;

use InvalidArgumentException;
use Redakte\Policy\RedactionPolicy;
use Redakte\Token\RedactionSession;

/**
 * Redaksiyon seçeneklerini hiyerarşik öncelikle çözen ve doğrulayan fabrika sınıfı.
 *
 * Öncelik Sırası:
 * 1. Çağrı parametreleri ($options array)
 * 2. Konfigürasyon ($registry / config('redakte.*'))
 * 3. Sabit varsayılanlar (Balanced, Namespaced, Tag)
 */
final class OptionsFactory
{
    private const VALID_KEYS = [
        'mode',
        'only',
        'entity_types',
        'validator',
        'run_validator',
        'names',
        'redact_names',
        'human_review',
        'check_human_review',
        'strategy',
        'default_strategy',
        'policy',
        'token_format',
        'session',
        'strict',
        'strict_mode',
    ];

    /**
     * Seçenekleri hiyerarşik olarak çözümler ve doğrulanmış RedactionOptions nesnesi üretir.
     *
     * @param RedactionOptions|array<string, mixed>|null $options
     * @param PatternRegistry|null $registry
     * @throws InvalidArgumentException
     */
    public static function create(
        RedactionOptions|array|null $options = null,
        ?PatternRegistry $registry = null,
    ): RedactionOptions {
        if ($options instanceof RedactionOptions) {
            if ($options->tokenFormat === null) {
                $configTokenFormat = (string) ($registry?->get('token_format', 'namespaced') ?? 'namespaced');
                return new RedactionOptions(
                    mode: $options->mode,
                    entityTypes: $options->entityTypes,
                    runValidator: $options->runValidator,
                    redactNames: $options->redactNames,
                    checkHumanReview: $options->checkHumanReview,
                    strategy: $options->strategy,
                    policy: $options->policy,
                    tokenFormat: $configTokenFormat,
                    session: $options->session,
                    strictMode: $options->strictMode,
                );
            }
            return $options;
        }

        $options ??= [];

        $strictMode = (bool) ($options['strict'] ?? $options['strict_mode'] ?? false);

        if ($strictMode) {
            foreach (array_keys($options) as $k) {
                if (!in_array($k, self::VALID_KEYS, true)) {
                    throw new InvalidArgumentException(sprintf('Bilinmeyen redaksiyon seçeneği: "%s".', $k));
                }
            }
        }

        // 1. Mode kontrolü ('redacted' | 'mask')
        $mode = (string) ($options['mode'] ?? 'redacted');
        if (!in_array($mode, ['redacted', 'mask'], true)) {
            throw new InvalidArgumentException(sprintf('Geçersiz redaksiyon modu: "%s". İzin verilenler: redacted, mask.', $mode));
        }

        // 2. Entity types filtresi
        /** @var list<string>|null $entityTypes */
        $entityTypes = $options['only'] ?? $options['entity_types'] ?? null;
        if ($entityTypes !== null && !is_array($entityTypes)) {
            throw new InvalidArgumentException('entity_types / only seçeneği bir dizi olmalıdır.');
        }

        // 3. İsim redaksiyonu aktiflik durumu
        $nameConfig = $registry?->getNameRedactionConfig() ?? [];
        $defaultRedactNames = $nameConfig['enabled'] ?? true;
        $redactNames = (bool) ($options['names'] ?? $options['redact_names'] ?? $defaultRedactNames);

        // 4. Doğrulayıcı ikinci geçiş
        $runValidator = (bool) ($options['validator'] ?? $options['run_validator'] ?? true);

        // 5. İnsan incelemesi kontrolü
        $checkHumanReview = (bool) ($options['human_review'] ?? $options['check_human_review'] ?? true);

        // 6. Strateji: Çağrı seçeneği > Config varsayılanı > TAG
        $configStrategy = $registry?->get('default_strategy', MaskStrategy::TAG);
        $strategy = $options['strategy'] ?? $options['default_strategy'] ?? $configStrategy ?? MaskStrategy::TAG;

        // 7. Politika: Çağrı seçeneği > Config varsayılanı > BALANCED
        $configPolicy = $registry?->get('policy', RedactionPolicy::BALANCED);
        $policy = $options['policy'] ?? $configPolicy ?? RedactionPolicy::BALANCED;

        // 8. Token formatı: Çağrı seçeneği > Config varsayılanı > NAMESPACED
        $configTokenFormat = $registry?->get('token_format', 'namespaced');
        $tokenFormat = (string) ($options['token_format'] ?? $configTokenFormat ?? 'namespaced');

        // 9. Session
        $session = $options['session'] ?? null;
        if ($session !== null && !$session instanceof RedactionSession) {
            throw new InvalidArgumentException('session parametresi RedactionSession örneği olmalıdır.');
        }

        return new RedactionOptions(
            mode: $mode,
            entityTypes: $entityTypes,
            runValidator: $runValidator,
            redactNames: $redactNames,
            checkHumanReview: $checkHumanReview,
            strategy: $strategy,
            policy: $policy,
            tokenFormat: $tokenFormat,
            session: $session,
            strictMode: $strictMode,
        );
    }
}
