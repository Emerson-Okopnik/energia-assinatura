<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditoriaBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_armazena_por_usina_competencia(): void
    {
        $this->criarUsina(24);

        AuditoriaBaseline::create([
            'usi_id' => 24,
            'competencia' => '2026-01',
            'valor_sistema_antes' => 1059.21,
            'valor_pago' => 1058.75,
        ]);

        $r = AuditoriaBaseline::where('usi_id', 24)->first();

        $this->assertNotNull($r);
        $this->assertSame('2026-01', $r->competencia->format('Y-m'));
        $this->assertEqualsWithDelta(1059.21, (float) $r->valor_sistema_antes, 0.001);
        $this->assertEqualsWithDelta(1058.75, (float) $r->valor_pago, 0.001);
        $this->assertNull($r->fatura_informada);
    }

    private function criarUsina(int $usiId): void
    {
        $now = now();

        $endId = DB::table('endereco')->insertGetId([
            'rua' => 'Rua Teste',
            'cidade' => 'Cidade Teste',
            'estado' => 'SP',
            'cep' => '12345-678',
            'numero' => 123,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'end_id');

        $cliId = DB::table('cliente')->insertGetId([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '12345678000190',
            'email' => 'teste@test.com',
            'telefone' => '1133334444',
            'end_id' => $endId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'cli_id');

        $dgerId = DB::table('dados_geracao')->insertGetId([
            'janeiro' => 0, 'fevereiro' => 0, 'marco' => 0, 'abril' => 0,
            'maio' => 0, 'junho' => 0, 'julho' => 0, 'agosto' => 0,
            'setembro' => 0, 'outubro' => 0, 'novembro' => 0, 'dezembro' => 0,
            'media' => 0, 'menor_geracao' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'dger_id');

        $comId = DB::table('comercializacao')->insertGetId([
            'valor_kwh' => 0.51,
            'valor_fixo' => 0,
            'cia_energia' => 'TESTE',
            'valor_final_media' => 0,
            'previsao_conexao' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ], 'com_id');

        $venId = DB::table('vendedor')->insertGetId([
            'nome' => 'Vendedor Teste',
            'patente' => 'junior',
            'created_at' => $now,
            'updated_at' => $now,
        ], 'ven_id');

        DB::table('usina')->insert([
            'usi_id' => $usiId,
            'cli_id' => $cliId,
            'dger_id' => $dgerId,
            'com_id' => $comId,
            'ven_id' => $venId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
