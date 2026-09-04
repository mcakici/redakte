<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Redakte\Laravel\Traits\HasRedaction;
use Redakte\RedakteServiceProvider;

class RestrictedDecisionModel extends Model
{
    use HasRedaction;

    protected $guarded = [];
    protected array $redactable = ['public_notes']; // 'secret_notes' allowlist içinde DEĞİL
}

class EloquentAllowlistSecurityTest extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            RedakteServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('redakte.token_format', 'legacy');
    }

    public function test_non_redactable_attribute_cannot_be_accessed_via_magic_getter(): void
    {
        $model = new RestrictedDecisionModel([
            'public_notes' => 'Müvekkil TCKN: 43650391326',
            'secret_notes' => 'Gizli TCKN: 43650391326',
        ]);

        // public_notes redactable olduğu için çalışmalı
        $this->assertNotNull($model->redacted_public_notes);
        $this->assertStringContainsString('[TCKN_1]', $model->redacted_public_notes);

        // secret_notes redactable olmadığı için null dönmeli (P1-21)
        $this->assertNull($model->redacted_secret_notes);
    }
}
