<?php

declare(strict_types=1);

namespace Redakte\Facades;

use Illuminate\Support\Facades\Facade;
use Redakte\Redactor;

/**
 * @method static \Redakte\RedactionResult redact(string $text, \Redakte\RedactionOptions|array|null $options = null)
 * @method static string clean(string $text, \Redakte\RedactionOptions|array|null $options = null)
 * @method static string partial(string $text, \Redakte\RedactionOptions|array|null $options = null)
 * @method static array maskForLLM(string $text, \Redakte\RedactionOptions|array|null $options = null)
 * @method static string unmask(string $text, \Redakte\RedactionMap|array $map)
 * @method static array redactModelIdentity(string $text)
 *
 * @see \Redakte\Redactor
 */
class Redakte extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Redactor::class;
    }
}
