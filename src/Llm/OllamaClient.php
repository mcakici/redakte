<?php

declare(strict_types=1);

namespace Redakte\Llm;

use Redakte\Contracts\LlmTransportInterface;
use Redakte\Exceptions\InvalidConfigurationException;
use Redakte\Exceptions\LlmDetectionException;
use Redakte\Exceptions\LlmContextWindowException;

final class OllamaClient
{
    public function __construct(private readonly LlmTransportInterface $transport = new NativeHttpTransport()) {}

    /** Modelin /api/show metadata bilgilerini, desteklediği düşünme değerleriyle birlikte getirir. */
    public function inspectModel(LlmOptions $options, ?float $timeoutSeconds = null): array
    {
        $endpoint = $options->showEndpoint;
        if ($endpoint === null) {
            $chatEndpoint = $options->endpoint ?? '';
            $queryPosition = strpos($chatEndpoint, '?');
            $path = $queryPosition === false ? $chatEndpoint : substr($chatEndpoint, 0, $queryPosition);
            $query = $queryPosition === false ? '' : substr($chatEndpoint, $queryPosition);
            if (!str_ends_with($path, '/chat')) {
                throw new InvalidConfigurationException('Özel chat URL için llm.show_endpoint verilmelidir.');
            }
            $endpoint = substr($path, 0, -5) . '/show' . $query;
        }
        $response = $this->transport->post(['model' => $options->model], $options, $endpoint, $timeoutSeconds);
        if (isset($response['error'])) {
            throw new LlmDetectionException('Ollama model bilgileri alınamadı.');
        }
        return $response;
    }

    /** Desteklenmeyen seviyelerin Ollama tarafından sessizce varsayılana çevrilmesini önler. */
    public function resolveThinking(LlmOptions $options, ?float $timeoutSeconds = null): bool|string|null
    {
        if ($options->think === null) {
            return null;
        }
        $info = $this->inspectModel($options, $timeoutSeconds);
        $thinking = $info['thinking'] ?? null;
        if ($thinking === null) {
            // Eski Ollama sürümleri metadata döndürmez. Boolean kontrolü sunucuya bırakılır;
            // isimli seviye desteği metadata olmadan güvenilir biçimde doğrulanamaz.
            if (is_bool($options->think)) {
                return $options->think;
            }
            throw new LlmDetectionException('Ollama düşünme seviye metadata bilgisi döndürmedi; sunucuyu güncelleyin veya boolean think kullanın.');
        }
        $values = is_array($thinking) ? ($thinking['values'] ?? null) : null;
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new LlmDetectionException('Ollama düşünme metadata biçimi geçersiz.');
        }
        if (in_array($options->think, $values, true)) {
            return $options->think;
        }
        // İsimli seviyeleri olan modellerde true, modelin ilan ettiği varsayılan seviyedir.
        $default = $thinking['default'] ?? null;
        if ($options->think === true && is_string($default) && in_array($default, $values, true)) {
            return $default;
        }
        throw new LlmDetectionException('Seçilen düşünme ayarı bu model tarafından desteklenmiyor. inspectModel() ile thinking.values bilgisini kontrol edin.');
    }

    public function chat(array $payload, LlmOptions $options, ?float $timeoutSeconds = null): array
    {
        $response = $this->transport->post($payload, $options, null, $timeoutSeconds);
        if (LlmContextWindowException::matches($response['error'] ?? null)) {
            throw new LlmContextWindowException('LLM context sınırı aşıldı.');
        }
        return $response;
    }
}
