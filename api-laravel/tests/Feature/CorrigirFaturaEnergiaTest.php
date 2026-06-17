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

    public function test_importa_fatura_fonte_de_csv_idempotente(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'ff') . '.csv';
        file_put_contents($csv, "uc,competencia,fatura_energia\n521206860,2026-01,1663.71\n521206860,2026-02,1657.39\n");

        $this->artisan('faturamento:importar-fatura-fonte', ['arquivo' => $csv])->assertOk();
        $this->assertSame(2, \App\Models\FaturaFonte::count());

        // re-rodar não duplica
        $this->artisan('faturamento:importar-fatura-fonte', ['arquivo' => $csv])->assertOk();
        $this->assertSame(2, \App\Models\FaturaFonte::count());

        $jan = \App\Models\FaturaFonte::where('uc', '521206860')->where('competencia', '2026-01-01')->first();
        $this->assertEqualsWithDelta(1663.71, (float) $jan->fatura_energia, 0.001);

        @unlink($csv);
    }
}
