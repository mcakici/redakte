<?php

declare(strict_types=1);

namespace Redakte\Tests;

use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Redakte\Facades\Redakte;
use Redakte\Laravel\Traits\HasRedaction;
use Redakte\RedakteServiceProvider;

class DummyDecisionModel extends Model
{
    use HasRedaction;

    protected $guarded = [];
    protected array $redactable = ['reasoning', 'notes'];
}

class EloquentRedactionTest extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            RedakteServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Redakte' => Redakte::class,
        ];
    }

    public function test_eloquent_dynamic_redacted_accessor(): void
    {
        $model = new DummyDecisionModel([
            'reasoning' => 'Davacı Ali Veli (TCKN: 43650391326) davasında.',
            'notes' => 'İletişim: 0532 123 45 67',
        ]);

        $this->assertStringContainsString('Davacı [KISI_1]', $model->redacted_reasoning);
        $this->assertStringContainsString('TCKN: [TCKN_1]', $model->redacted_reasoning);
        $this->assertStringContainsString('[TELEFON_1]', $model->redacted_notes);
        $this->assertStringNotContainsString('43650391326', $model->redacted_reasoning);
    }

    public function test_to_redacted_array_method(): void
    {
        $model = new DummyDecisionModel([
            'reasoning' => 'TCKN: 43650391326',
            'notes' => 'Tel: 0532 123 45 67',
        ]);

        $redactedArray = $model->toRedactedArray();

        $this->assertStringContainsString('[TCKN_1]', $redactedArray['reasoning']);
        $this->assertStringContainsString('[TELEFON_1]', $redactedArray['notes']);
        $this->assertStringNotContainsString('43650391326', $redactedArray['reasoning']);
    }
}
