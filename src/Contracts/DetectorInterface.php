<?php

declare(strict_types=1);

namespace Redakte\Contracts;

use Redakte\Detection\Detection;
use Redakte\Detection\DetectionContext;

/**
 * Tüm dedektörlerin uygulaması gereken arayüz.
 * Dedektörler metni asla doğrudan değiştirmez; orijinal koordinatlara bağlı Detection nesneleri üretir.
 */
interface DetectorInterface
{
    /**
     * @param string $text Orijinal metin
     * @param DetectionContext $context Tespit bağlamı
     * @return list<Detection>
     */
    public function detect(string $text, DetectionContext $context): array;
}
