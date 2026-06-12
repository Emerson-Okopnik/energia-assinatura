<?php

namespace Tests\Feature;

use App\Domain\Faturamento\Contracts\LedgerRepository;
use App\Models\CreditoLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Garante que as migrations da Fase 2 (ledger + precisão decimal) sobem em
 * sqlite in-memory e que o EloquentLedgerRepository consolida o saldo via FIFO.
 */
class CreditoLedgerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_criam_a_tabela_credito_ledger(): void
    {
        $this->assertTrue(Schema::hasTable('credito_ledger'));
        $this->assertTrue(Schema::hasColumns('credito_ledger', [
            'cl_id', 'usi_id', 'competencia_origem', 'competencia_evento',
            'tipo', 'kwh', 'tarifa_kwh', 'valor_reais', 'vencimento',
            'ref_lancamento_id', 'idempotency_key', 'estornado_em', 'user_id',
        ]));
    }

    public function test_migration_decimal_converte_colunas_monetarias(): void
    {
        // Apenas confirma que as tabelas-alvo continuam existindo após o change().
        $this->assertTrue(Schema::hasColumn('valor_acumulado_reserva', 'total'));
        $this->assertTrue(Schema::hasColumn('comercializacao', 'valor_kwh'));
        $this->assertTrue(Schema::hasColumn('dados_geracao', 'menor_geracao'));
    }

    public function test_repositorio_consolida_saldo_em_aberto_por_origem_via_fifo(): void
    {
        $usiId = $this->criarUsina();

        // dez/2025: guardou 1192, consumiu 200 -> saldo 992 (em aberto)
        CreditoLedger::create($this->lancamento($usiId, '2025-12-01', '2025-12-01', CreditoLedger::TIPO_CREDITO, 1192));
        CreditoLedger::create($this->lancamento($usiId, '2025-12-01', '2026-04-01', CreditoLedger::TIPO_CONSUMO, -200));

        // jan/2026: guardou 500, consumiu 500 -> saldo 0 (descartado)
        CreditoLedger::create($this->lancamento($usiId, '2026-01-01', '2026-01-01', CreditoLedger::TIPO_CREDITO, 500));
        CreditoLedger::create($this->lancamento($usiId, '2026-01-01', '2026-05-01', CreditoLedger::TIPO_CONSUMO, -500));

        // fev/2026: guardou 300, mas o crédito foi estornado -> ignorado
        CreditoLedger::create($this->lancamento(
            $usiId, '2026-02-01', '2026-02-01', CreditoLedger::TIPO_CREDITO, 300, estornado: true
        ));

        // mar/2026: guardou 700 -> saldo 700 (em aberto)
        CreditoLedger::create($this->lancamento($usiId, '2026-03-01', '2026-03-01', CreditoLedger::TIPO_CREDITO, 700));

        /** @var LedgerRepository $repo */
        $repo = $this->app->make(LedgerRepository::class);
        $lotes = $repo->lotesEmAbertoDaUsina($usiId);

        $this->assertCount(2, $lotes, 'Só dez/2025 e mar/2026 têm saldo positivo.');

        // FIFO: dez/2025 primeiro (mais antigo).
        $this->assertSame('2025-12', (string) $lotes[0]->competenciaOrigem);
        $this->assertEqualsWithDelta(992.0, $lotes[0]->saldoKwh->valor(), 0.0001);

        $this->assertSame('2026-03', (string) $lotes[1]->competenciaOrigem);
        $this->assertEqualsWithDelta(700.0, $lotes[1]->saldoKwh->valor(), 0.0001);
    }

    public function test_marcar_estornado_remove_lote_do_saldo_em_aberto(): void
    {
        $usiId = $this->criarUsina();

        $ids = $this->app->make(LedgerRepository::class)->salvarLancamentos([
            $this->lancamento($usiId, '2026-03-01', '2026-03-01', CreditoLedger::TIPO_CREDITO, 700),
        ]);

        $repo = $this->app->make(LedgerRepository::class);
        $this->assertCount(1, $repo->lotesEmAbertoDaUsina($usiId));

        $afetados = $repo->marcarEstornado($ids, new \DateTimeImmutable('2026-06-11 12:00:00'));

        $this->assertSame(1, $afetados);
        $this->assertCount(0, $repo->lotesEmAbertoDaUsina($usiId));
    }

    /** Cria a cadeia mínima de FKs necessária para uma usina válida. */
    private function criarUsina(): int
    {
        $now = now();

        $endId = \DB::table('endereco')->insertGetId([
            'created_at' => $now, 'updated_at' => $now,
        ], 'end_id');

        $cliId = \DB::table('cliente')->insertGetId([
            'nome' => 'Cliente Teste',
            'cpf_cnpj' => '00000000000',
            'end_id' => $endId,
            'created_at' => $now, 'updated_at' => $now,
        ], 'cli_id');

        $meses = array_fill_keys([
            'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
        ], 0);

        $dgerId = \DB::table('dados_geracao')->insertGetId(array_merge($meses, [
            'media' => 0, 'menor_geracao' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]), 'dger_id');

        $comId = \DB::table('comercializacao')->insertGetId([
            'valor_kwh' => 0.51,
            'valor_fixo' => 0,
            'cia_energia' => 'TESTE',
            'valor_final_media' => 0,
            'previsao_conexao' => $now->toDateString(),
            'created_at' => $now, 'updated_at' => $now,
        ], 'com_id');

        $venId = \DB::table('vendedor')->insertGetId([
            'nome' => 'Vendedor Teste',
            'patente' => 'junior',
            'created_at' => $now, 'updated_at' => $now,
        ], 'ven_id');

        return \DB::table('usina')->insertGetId([
            'cli_id' => $cliId,
            'dger_id' => $dgerId,
            'com_id' => $comId,
            'ven_id' => $venId,
            'created_at' => $now, 'updated_at' => $now,
        ], 'usi_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function lancamento(
        int $usiId,
        string $origem,
        string $evento,
        string $tipo,
        float $kwh,
        bool $estornado = false,
    ): array {
        return [
            'usi_id' => $usiId,
            'competencia_origem' => $origem,
            'competencia_evento' => $evento,
            'tipo' => $tipo,
            'kwh' => $kwh,
            'tarifa_kwh' => 0.51,
            'valor_reais' => round($kwh * 0.51, 2),
            'vencimento' => date('Y-m-d', strtotime($origem . ' +180 days')),
            'estornado_em' => $estornado ? now() : null,
        ];
    }
}
