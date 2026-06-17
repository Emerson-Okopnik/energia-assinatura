<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FaturaFonte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrigirFaturaEnergiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fatura_fonte_armazena_por_uc_e_competencia(): void
    {
        FaturaFonte::create([
            'uc' => '521206860',
            'competencia' => '2026-01-01',
            'fatura_energia' => 1663.71,
        ]);

        $registro = FaturaFonte::where('uc', '521206860')->first();

        $this->assertNotNull($registro);
        $this->assertEqualsWithDelta(1663.71, (float) $registro->fatura_energia, 0.001);
        $this->assertSame('2026-01', $registro->competencia->format('Y-m'));
    }
}
