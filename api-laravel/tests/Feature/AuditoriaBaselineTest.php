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

    private const TARIFA = 0.51;

    public function test_baseline_armazena_por_usina_competencia(): void
    {
        $uc = $this->criarUsina(media: 0, geracaoPorAno: []);
        $usiId = $this->usiId($uc);

        AuditoriaBaseline::create([
            'usi_id' => $usiId,
            'competencia' => '2026-01',
            'valor_sistema_antes' => 1059.21,
            'valor_pago' => 1058.75,
        ]);

        $r = AuditoriaBaseline::where('usi_id', $usiId)->first();

        $this->assertNotNull($r);
        $this->assertSame('2026-01', $r->competencia->format('Y-m'));
        $this->assertEqualsWithDelta(1059.21, (float) $r->valor_sistema_antes, 0.001);
        $this->assertEqualsWithDelta(1058.75, (float) $r->valor_pago, 0.001);
        $this->assertNull($r->fatura_informada);
    }

    public function test_importa_antes_e_pago_idempotente(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [2026 => ['janeiro' => 1200]]);
        $usiId = $this->usiId($uc);

        $antes = tempnam(sys_get_temp_dir(), 'a') . '.csv';
        file_put_contents($antes, "uc,competencia,valor\n{$uc},2026-01,1059.21\n");
        $pago = tempnam(sys_get_temp_dir(), 'p') . '.csv';
        file_put_contents($pago, "uc,competencia,valor\n{$uc},2026-01,1058.75\n");

        $this->artisan('auditoria:importar-baseline', ['--antes' => $antes, '--pago' => $pago])->assertOk();

        $r = \App\Models\AuditoriaBaseline::where('usi_id', $usiId)->where('competencia', '2026-01-01')->first();
        $this->assertEqualsWithDelta(1059.21, (float) $r->valor_sistema_antes, 0.001);
        $this->assertEqualsWithDelta(1058.75, (float) $r->valor_pago, 0.001);

        // re-rodar não duplica
        $this->artisan('auditoria:importar-baseline', ['--antes' => $antes, '--pago' => $pago])->assertOk();
        $this->assertSame(1, \App\Models\AuditoriaBaseline::where('usi_id', $usiId)->count());

        @unlink($antes); @unlink($pago);
    }

    public function test_uc_sem_usina_e_ignorada_sem_quebrar(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'x') . '.csv';
        file_put_contents($csv, "uc,competencia,valor\n00000000,2026-01,500.00\n");

        $this->artisan('auditoria:importar-baseline', ['--pago' => $csv])->assertOk();
        $this->assertSame(0, \App\Models\AuditoriaBaseline::count());

        @unlink($csv);
    }

    // ---------------------------------------------------------------- helpers

    private function usiId(string $uc): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('usina')->where('uc', $uc)->value('usi_id');
    }

    private function nomesMeses(): array
    {
        return [1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
                7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    }

    /**
     * Cria a cadeia mínima de FKs e a geração real informada.
     *
     * @param array<int, array<string, float>> $geracaoPorAno [ano => [mes => kwh]]
     */
    private function criarUsina(float $media, array $geracaoPorAno): string
    {
        $now = now();
        $uc = 'UC' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

        $endId = DB::table('endereco')->insertGetId([
            'created_at' => $now, 'updated_at' => $now,
        ], 'end_id');

        $cliId = DB::table('cliente')->insertGetId([
            'nome' => 'Cliente ' . $uc,
            'cpf_cnpj' => '00000000000',
            'end_id' => $endId,
            'created_at' => $now, 'updated_at' => $now,
        ], 'cli_id');

        $meses = array_fill_keys(array_values($this->nomesMeses()), 0);

        $dgerId = DB::table('dados_geracao')->insertGetId(array_merge($meses, [
            'media' => $media, 'menor_geracao' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]), 'dger_id');

        $comId = DB::table('comercializacao')->insertGetId([
            'valor_kwh' => self::TARIFA,
            'valor_fixo' => 0,
            'cia_energia' => 'TESTE',
            'valor_final_media' => 0,
            'previsao_conexao' => $now->toDateString(),
            'created_at' => $now, 'updated_at' => $now,
        ], 'com_id');

        $venId = DB::table('vendedor')->insertGetId([
            'nome' => 'Vendedor Teste',
            'patente' => 'junior',
            'created_at' => $now, 'updated_at' => $now,
        ], 'ven_id');

        $usiId = DB::table('usina')->insertGetId([
            'cli_id' => $cliId,
            'dger_id' => $dgerId,
            'com_id' => $comId,
            'ven_id' => $venId,
            'uc' => $uc,
            'created_at' => $now, 'updated_at' => $now,
        ], 'usi_id');

        foreach ($geracaoPorAno as $ano => $valores) {
            $linha = array_merge(array_fill_keys(array_values($this->nomesMeses()), 0), $valores);
            $dgrId = DB::table('dados_geracao_real')->insertGetId(array_merge($linha, [
                'created_at' => $now, 'updated_at' => $now,
            ]), 'dgr_id');

            DB::table('dados_geracao_real_usina')->insert([
                'usi_id' => $usiId,
                'cli_id' => $cliId,
                'dgr_id' => $dgrId,
                'ano' => $ano,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return $uc;
    }
}
