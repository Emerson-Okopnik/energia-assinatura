<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CreditoLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Testes do backfill do ledger (ledger:reconstruir) — REGRAS_DE_CALCULO.md §6, §7, §8, §12.
 */
class ReconstruirLedgerReservaTest extends TestCase
{
    use RefreshDatabase;

    private const TARIFA = 0.51;

    public function test_usina_so_excedentes_gera_um_credito_por_mes(): void
    {
        // media 1000; jan 1500 (+500), fev 1200 (+200) -> dois CREDITO, saldo 700.
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500, 'fevereiro' => 1200],
        ]);

        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $creditos = CreditoLedger::doUsina($usiId)->porTipo(CreditoLedger::TIPO_CREDITO)->get();

        $this->assertCount(2, $creditos);
        $porOrigem = $creditos->keyBy(fn ($c) => $c->competencia_origem->format('Y-m-d'));
        $this->assertEqualsWithDelta(500.0, (float) $porOrigem['2026-01-01']->kwh, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $porOrigem['2026-02-01']->kwh, 0.001);

        $this->assertEqualsWithDelta(700.0, $this->saldoLedger($usiId), 0.001);
        $this->assertCount(0, CreditoLedger::doUsina($usiId)->porTipo(CreditoLedger::TIPO_CONSUMO)->get());
    }

    public function test_consumo_cross_ano_referencia_o_lote_mais_antigo_primeiro(): void
    {
        // dez/2025 +1192, jan/2026 +2178; mar/2026 déficit 1500.
        // FIFO: consome 1192 de dez/2025 + 308 de jan/2026.
        $uc = $this->criarUsina(media: 5000, geracaoPorAno: [
            2025 => ['dezembro' => 6192],
            2026 => ['janeiro' => 7178, 'marco' => 3500],
        ]);

        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $consumos = CreditoLedger::doUsina($usiId)
            ->porTipo(CreditoLedger::TIPO_CONSUMO)
            ->orderBy('competencia_origem')
            ->get();

        $this->assertCount(2, $consumos);

        // mais antigo (dez/2025) primeiro, esgotado.
        $this->assertSame('2025-12-01', $consumos[0]->competencia_origem->format('Y-m-d'));
        $this->assertEqualsWithDelta(-1192.0, (float) $consumos[0]->kwh, 0.001);

        // depois jan/2026, parcial.
        $this->assertSame('2026-01-01', $consumos[1]->competencia_origem->format('Y-m-d'));
        $this->assertEqualsWithDelta(-308.0, (float) $consumos[1]->kwh, 0.001);

        // CONSUMO referencia o CREDITO de origem (rastreabilidade FIFO §8).
        $creditoDez = CreditoLedger::doUsina($usiId)
            ->porTipo(CreditoLedger::TIPO_CREDITO)
            ->get()
            ->first(fn ($c) => $c->competencia_origem->format('Y-m-d') === '2025-12-01');
        $this->assertSame((int) $creditoDez->cl_id, (int) $consumos[0]->ref_lancamento_id);

        // saldo final = (1192 + 2178) - 1500 = 1870.
        $this->assertEqualsWithDelta(1870.0, $this->saldoLedger($usiId), 0.001);
    }

    public function test_deficit_sem_reserva_e_pago_nao_gera_credito(): void
    {
        // A reserva sempre começa em ZERO (fiel ao cadastro). Nunca houve excedente;
        // jan/2025 déficit (geração 1200 < média 2000) -> o déficit é PAGO à
        // concessionária, NÃO compensado. NÃO existe SALDO_INICIAL/crédito migrado.
        $uc = $this->criarUsina(media: 2000, geracaoPorAno: [
            2025 => ['janeiro' => 1200],
        ]);

        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);

        // Nenhum lançamento de crédito de qualquer tipo: não há reserva e o déficit é pago.
        $this->assertCount(0, CreditoLedger::doUsina($usiId)->porTipo(CreditoLedger::TIPO_SALDO_INICIAL)->get());
        $this->assertCount(0, CreditoLedger::doUsina($usiId)->porTipo(CreditoLedger::TIPO_CONSUMO)->get());
        $this->assertCount(0, CreditoLedger::doUsina($usiId)->porTipo(CreditoLedger::TIPO_CREDITO)->get());

        // Saldo final zero: nunca houve crédito.
        $this->assertEqualsWithDelta(0.0, $this->saldoLedger($usiId), 0.001);
    }

    public function test_idempotencia_rodar_duas_vezes_nao_duplica(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500, 'fevereiro' => 800],
        ]);

        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();
        $usiId = $this->usiId($uc);
        $primeira = CreditoLedger::doUsina($usiId)->count();

        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();
        $segunda = CreditoLedger::doUsina($usiId)->count();

        $this->assertGreaterThan(0, $primeira);
        $this->assertSame($primeira, $segunda, 'Re-rodar não pode duplicar lançamentos.');
        $this->assertEqualsWithDelta(500.0 - 200.0, $this->saldoLedger($usiId), 0.001);
    }

    public function test_lote_nao_consumido_expira_apos_180_dias(): void
    {
        // jan/2025 +500 (vence ~jul/2025). ago/2025 sem déficit (geração = média),
        // mas é mês de evento: a expiração é avaliada e o lote de jan (vencido,
        // não consumido) vira EXPIRACAO de 500 (§7). Datas no passado (hoje 2026-06)
        // para não serem filtradas como competência futura.
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2025 => ['janeiro' => 1500, 'agosto' => 1000],
        ]);

        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $expiracoes = CreditoLedger::doUsina($usiId)->porTipo(CreditoLedger::TIPO_EXPIRACAO)->get();

        $this->assertCount(1, $expiracoes);
        $this->assertSame('2025-01-01', $expiracoes->first()->competencia_origem->format('Y-m-d'));
        $this->assertEqualsWithDelta(-500.0, (float) $expiracoes->first()->kwh, 0.001);

        // Nada foi consumido (sem déficit) e nada sobrou (tudo expirou).
        $this->assertCount(0, CreditoLedger::doUsina($usiId)->porTipo(CreditoLedger::TIPO_CONSUMO)->get());
        $this->assertEqualsWithDelta(0.0, $this->saldoLedger($usiId), 0.001);
    }

    public function test_dry_run_nao_grava(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500],
        ]);

        $this->artisan('ledger:reconstruir', ['--usina' => $uc, '--dry-run' => true])->assertOk();

        $this->assertSame(0, CreditoLedger::doUsina($this->usiId($uc))->count());
    }

    // ---------------------------------------------------------------- helpers

    private function saldoLedger(int $usiId): float
    {
        return (float) CreditoLedger::doUsina($usiId)->naoEstornado()->sum('kwh');
    }

    private function usiId(string $uc): int
    {
        return (int) DB::table('usina')->where('uc', $uc)->value('usi_id');
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

    /** @return array<int, string> */
    private function nomesMeses(): array
    {
        return [
            'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
        ];
    }
}
