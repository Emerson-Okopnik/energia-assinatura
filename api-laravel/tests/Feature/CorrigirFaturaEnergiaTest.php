<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FaturaFonte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorrigirFaturaEnergiaTest extends TestCase
{
    use RefreshDatabase;

    private const TARIFA = 0.51;

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

    public function test_corrige_usa_fatura_fonte_quando_prod_zero(): void
    {
        // media 1000, jan gera 1500 (excedente, guarda 500), fev gera 800 (deficit 200, usa reserva)
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500, 'fevereiro' => 800],
        ]);
        // estado "pos-backfill": fatura 0 em ambos os meses
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        // fatura-fonte: jan e fev com fatura real
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-02-01', 'fatura_energia' => 40]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $pdf = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->get()
            ->keyBy(fn ($r) => \Illuminate\Support\Carbon::parse($r->competencia)->format('Y-m'));

        $this->assertEqualsWithDelta(30.0, (float) $pdf['2026-01']->fatura_energia, 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $pdf['2026-02']->fatura_energia, 0.001);
    }

    public function test_corrige_preserva_fatura_manual_de_prod(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500],
        ]);
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        // simula lançamento manual em prod: jan com fatura 99 (re-fatura com fatura informada)
        $usina = \App\Models\Usina::with(['comercializacao', 'dadoGeracao'])->where('uc', $uc)->first();
        app(\App\Application\Faturamento\FaturamentoService::class)->calcularMes(
            $usina, 2026, 1, ['geracao_bruta_kwh' => 1500, 'fatura_energia' => 99], persistir: true,
        );

        // fatura-fonte traz outro valor (deve ser IGNORADO, pois prod>0)
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $jan = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->first();
        $this->assertEqualsWithDelta(99.0, (float) $jan->fatura_energia, 0.001); // manual preservado
    }

    public function test_dry_run_nao_grava(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [2026 => ['janeiro' => 1500]]);
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc, '--dry-run' => true])->assertOk();

        $usiId = $this->usiId($uc);
        $jan = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->first();
        $this->assertEqualsWithDelta(0.0, (float) $jan->fatura_energia, 0.001); // dry-run: continua 0
    }

    public function test_corrigir_e_idempotente(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500, 'fevereiro' => 800],
        ]);
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $ledgerAntes = \App\Models\CreditoLedger::doUsina($usiId)->count();
        $this->assertGreaterThan(0, $ledgerAntes, 'a 1ª rodada deve gerar lançamentos no ledger');
        $pdf1 = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->orderBy('competencia')
            ->pluck('valor_final')->map(fn ($v) => round((float) $v, 2))->all();

        // re-rodar
        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $ledgerDepois = \App\Models\CreditoLedger::doUsina($usiId)->count();
        $pdf2 = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->orderBy('competencia')
            ->pluck('valor_final')->map(fn ($v) => round((float) $v, 2))->all();

        $this->assertSame($ledgerAntes, $ledgerDepois, 'ledger não pode duplicar ao re-rodar');
        $this->assertSame($pdf1, $pdf2, 'valores devem ser idênticos ao re-rodar');
    }

    public function test_guard_futuro_nao_materializa_competencia_futura(): void
    {
        // Usina cuja geração está num ano claramente futuro (2099).
        // O comando corrigir-fatura não deve criar nenhuma linha em geracao_faturamento_pdf
        // para essa competência — o guard de mês futuro deve bloqueá-la.
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2099 => ['janeiro' => 1200],
        ]);

        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();
        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $count = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)
            ->where('competencia', '>=', '2099-01-01')
            ->count();

        $this->assertSame(0, $count, 'competência futura (2099-01) não deve ser materializada pelo guard');
    }

    // ---------------------------------------------------------------- helpers

    private function usiId(string $uc): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('usina')->where('uc', $uc)->value('usi_id');
    }

    /** @return array<int, string> */
    private function nomesMeses(): array
    {
        return [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];
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
