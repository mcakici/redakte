<?php

declare(strict_types=1);

namespace Redakte\Laravel\Traits;

use Redakte\RedactionOptions;
use Redakte\Redakte;

/**
 * Eloquent modellerinde hassas alanların dinamik olarak veya topluca
 * redakte edilmiş versiyonlarını elde etmeyi sağlayan Trait.
 *
 * Model örneği:
 * class Decision extends Model {
 *     use HasRedaction;
 *     protected array $redactable = ['reasoning', 'content'];
 * }
 *
 * Kullanım:
 * $decision->redacted_reasoning;
 * $decision->getRedactedAttribute('reasoning');
 * $decision->toRedactedArray();
 */
trait HasRedaction
{
    /**
     * Belirtilen alanın redakte edilmiş halini döndürür
     */
    public function getRedactedAttribute(string $key, RedactionOptions|array|null $options = null): ?string
    {
        $value = $this->getAttribute($key);

        if ($value === null || !is_string($value)) {
            return $value;
        }

        return Redakte::clean($value, $options);
    }

    /**
     * Redakte edilebilir alan listesi
     *
     * @return list<string>
     */
    public function getRedactableAttributes(): array
    {
        return property_exists($this, 'redactable') && is_array($this->redactable)
            ? $this->redactable
            : [];
    }

    /**
     * Alanın redakte edilebilir olup olmadığını denetler
     */
    public function isRedactable(string $key): bool
    {
        return in_array($key, $this->getRedactableAttributes(), true);
    }

    /**
     * Modeli diziye çevirirken redactable listesindeki alanları otomatik redakte eder
     *
     * @return array<string, mixed>
     */
    public function toRedactedArray(RedactionOptions|array|null $options = null): array
    {
        $attributes = method_exists($this, 'toArray') ? $this->toArray() : [];

        foreach ($this->getRedactableAttributes() as $attribute) {
            if (isset($attributes[$attribute]) && is_string($attributes[$attribute])) {
                $attributes[$attribute] = Redakte::clean($attributes[$attribute], $options);
            }
        }

        return $attributes;
    }

    /**
     * Sihirli getter: $model->redacted_content çağrıldığında
     * otomatik olarak getRedactedAttribute('content') sonucunu döner.
     */
    public function __get($key)
    {
        if (str_starts_with($key, 'redacted_')) {
            $realKey = substr($key, 9);
            if ($this->isRedactable($realKey) || array_key_exists($realKey, $this->attributes ?? [])) {
                return $this->getRedactedAttribute($realKey);
            }
        }

        return parent::__get($key);
    }
}
