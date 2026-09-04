<?php

declare(strict_types=1);

namespace Redakte;

use Illuminate\Support\Facades\Config;
use Redakte\Exceptions\InvalidConfigurationException;

/**
 * Konfigürasyondan pattern'leri, negatif filtreleri ve ayarları yükleyip yöneten sınıf.
 * Kısmi konfigürasyon geçildiğinde varsayılan kuralları silmez, kontrollü deep-merge uygular.
 */
class PatternRegistry
{
    private array $mergedConfig = [];
    private ?array $sortedPatterns = null;

    /**
     * @param array<string, mixed>|null $customConfig Kullanıcı konfigürasyon override'ları
     * @param bool $strictMode Bilinmeyen anahtarlarda istisna fırlatılsın mı
     */
    public function __construct(?array $customConfig = null, bool $strictMode = false)
    {
        $defaultConfig = $this->loadBaseConfig();

        if ($customConfig === null) {
            $this->mergedConfig = $defaultConfig;
        } else {
            $this->mergedConfig = $this->mergeConfigs($defaultConfig, $customConfig, $strictMode);
        }
    }

    /**
     * @return list<array{id: string, entity_type: string, regex: string, validator: ?string, priority: int}>
     */
    public function getPatterns(): array
    {
        if ($this->sortedPatterns === null) {
            $patterns = $this->get('patterns', []);
            usort($patterns, fn (array $a, array $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
            $this->sortedPatterns = $patterns;
        }

        return $this->sortedPatterns;
    }

    /**
     * @return list<string>
     */
    public function getExcludePatterns(): array
    {
        return $this->get('exclude_patterns', []);
    }

    /**
     * @return array<string, array{label: string, label_plural: string}>
     */
    public function getEntityTypes(): array
    {
        return $this->get('entity_types', []);
    }

    public function getPolicyId(): string
    {
        return $this->get('policy_id', 'legal_tr_v1');
    }

    public function getPolicyVersion(): string
    {
        return $this->get('policy_version', '1.0.2');
    }

    /**
     * @return list<string>
     */
    public function getHumanReviewKeywords(): array
    {
        return $this->get('human_review_keywords', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getNameRedactionConfig(): array
    {
        $nameConfig = $this->get('name_redaction', []);

        // P0-04: Üst seviye custom_names geçilmişse geriye uyumlu olarak birleştir
        $topCustom = $this->get('custom_names', null);
        if (is_array($topCustom) && !empty($topCustom)) {
            $nested = $nameConfig['custom_names'] ?? [];
            $nameConfig['custom_names'] = array_values(array_unique(array_merge($nested, $topCustom)));
        }

        return $nameConfig;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->mergedConfig[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->mergedConfig;
    }

    /**
     * Paket varsayılan konfigürasyonunu yükler
     *
     * @return array<string, mixed>
     */
    private function loadBaseConfig(): array
    {
        try {
            if (class_exists(Config::class) && Config::has('redakte.policy_id')) {
                /** @var array<string, mixed> $laravelConfig */
                $laravelConfig = Config::get('redakte', []);
                if (!empty($laravelConfig)) {
                    return $laravelConfig;
                }
            }
        } catch (\Throwable) {
            // Laravel config container hazır değilse dosyadan oku
        }

        static $fileConfig = null;
        if ($fileConfig === null) {
            $path = __DIR__ . '/../config/redakte.php';
            $fileConfig = file_exists($path) ? require $path : [];
        }

        return $fileConfig ?? [];
    }

    /**
     * Varsayılan ve özel ayarları kontrollü şekilde birleştirir (P0-03).
     */
    private function mergeConfigs(array $base, array $custom, bool $strictMode): array
    {
        $validKeys = [
            'policy_id', 'policy_version', 'entity_types', 'default_strategy',
            'patterns', 'patterns_mode', 'name_redaction', 'exclude_patterns',
            'exclude_patterns_mode', 'human_review_keywords', 'custom_names',
            'token_format', 'limits', 'policy',
        ];

        if ($strictMode) {
            foreach (array_keys($custom) as $k) {
                if (!in_array($k, $validKeys, true)) {
                    throw new InvalidConfigurationException(sprintf('Bilinmeyen konfigürasyon anahtarı: %s', $k));
                }
            }
        }

        $result = $base;

        // 1. Skaler ve üst düzey değerler
        foreach ($custom as $key => $val) {
            if ($key === 'patterns' || $key === 'exclude_patterns' || $key === 'name_redaction') {
                continue;
            }
            $result[$key] = $val;
        }

        // 2. patterns birleştirme (append, prepend, replace)
        if (isset($custom['patterns']) && is_array($custom['patterns'])) {
            $mode = $custom['patterns_mode'] ?? 'append';
            $basePatterns = $base['patterns'] ?? [];
            $customPatterns = $custom['patterns'];

            $result['patterns'] = match ($mode) {
                'replace' => $customPatterns,
                'prepend' => array_merge($customPatterns, $basePatterns),
                default => array_merge($basePatterns, $customPatterns),
            };
        }

        // 3. exclude_patterns birleştirme
        if (isset($custom['exclude_patterns']) && is_array($custom['exclude_patterns'])) {
            $mode = $custom['exclude_patterns_mode'] ?? 'append';
            $baseExcludes = $base['exclude_patterns'] ?? [];
            $customExcludes = $custom['exclude_patterns'];

            $result['exclude_patterns'] = match ($mode) {
                'replace' => $customExcludes,
                default => array_values(array_unique(array_merge($baseExcludes, $customExcludes))),
            };
        }

        // 4. name_redaction alt anahtarlarının deep merge'i
        if (isset($custom['name_redaction']) && is_array($custom['name_redaction'])) {
            $baseName = $base['name_redaction'] ?? [];
            $result['name_redaction'] = array_merge($baseName, $custom['name_redaction']);
        }

        return $result;
    }
}
