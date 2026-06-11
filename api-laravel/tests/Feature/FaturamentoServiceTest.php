<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Faturamento\FaturamentoService;
use App\Models\CreditoLedger;
use App\Models\Usina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 — camada de aplicação (FaturamentoService) sobre o NÚCLEO ÚNICO.
 *
 * Cobre: preview não grava; cálculo+persistência grava ledger e colunas
 * materializadas; caso golden Eder (5700,65); expiração indo pra frente PAGA;
 * idempotência (2x mesma chave não duplica). sqlite + RefreshDatabase.
 */
class FaturamentoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FaturamentoService
    {
        return $this->app->make(FaturamentoService::class);
    }

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = (int) \DB::table('users')->insertGetId([
            'name' => 'Operador',
            'email' => 'op@teste.local',
            'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ], 'id');
    }

    public function test_preview_nao_grava_no_ledger(): void
    {
        $usina = $this->usina(media: 12911, menor: 7636, tarifa: 0.51, rede: 'Trifásico');
        $this->lote((int) $usina->usi_id, '2026-03-01', 1761, 0.51); // saldo em aberto

        $antes = CreditoLedger::count();

        $resposta = $this->service()->calcularMes(
            $usina,
            2026,
            5,
            ['geracao_bruta_kwh' => 9858, 'consumo' => 8, 'fatura_energia' => 98.77],
            persistir: false,
        );

        $this->assertFalse($resposta->persistido);
        $this->assertSame($antes, CreditoLedger::count(), 'Preview NÃO pode gravar lançamentos.');
        $this->assertSame(0, \App\Models\HistoricoEstorno::count(), 'Preview NÃO cria snapshot.');

        // Geração líquida = 9858 − max(8 − 100, 0) = 9858 (consumo < desconto rede).
        $this->assertEqualsWithDelta(9858.0, $resposta->entrada->geracaoLiquidaKwh->valor(), 1e-9);
    }

    public function test_persistir_grava_ledger_e_colunas_materializadas(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        // Reserva: dez/2025 com 1192 e jan/2026 com 2178 (em aberto).
        $this->lote((int) $usina->usi_id, '2025-12-01', 1192, 0.50);
        $this->lote((int) $usina->usi_id, '2026-01-01', 2178, 0.50);

        // Geração líquida 8500 (bruta 8600, consumo 200, desconto trif. 100 -> -100).
        // Faltante = 10000 − 8500 = 1500 -> consome 1192 (dez) + 308 (jan) via FIFO.
        $resposta = $this->service()->calcularMes(
            $usina,
            2026,
            5,
            ['geracao_bruta_kwh' => 8600, 'consumo' => 200, 'fatura_energia' => 0],
            persistir: true,
            userId: $this->userId,
            idempotencyKey: 'k-persist-1',
        );

        $this->assertTrue($resposta->persistido);
        $this->assertEqualsWithDelta(8500.0, $resposta->entrada->geracaoLiquidaKwh->valor(), 1e-9);

        // Ledger: 2 lançamentos de CONSUMO (dez + jan), nenhum CREDITO (déficit).
        $consumos = CreditoLedger::where('usi_id', $usina->usi_id)
            ->where('tipo', CreditoLedger::TIPO_CONSUMO)->get();
        $this->assertCount(2, $consumos);
        $this->assertEqualsWithDelta(-1500.0, (float) $consumos->sum('kwh'), 1e-6);

        // CONSUMO aponta para o CREDITO de origem (rastreabilidade FIFO §8).
        foreach ($consumos as $c) {
            $this->assertNotNull($c->ref_lancamento_id, 'CONSUMO deve referenciar o CREDITO de origem.');
        }

        // Colunas materializadas (cache de leitura).
        $vinculo = \App\Models\CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
            ->where('ano', 2026)->firstOrFail();

        $credito = \App\Models\CreditosDistribuidos::findOrFail($vinculo->cd_id);
        $faturamento = \App\Models\FaturamentoUsina::findOrFail($vinculo->fa_id);
        $geracao = \App\Models\DadosGeracaoReal::findOrFail(
            \App\Models\DadosGeracaoRealUsina::where('usi_id', $usina->usi_id)->where('ano', 2026)->value('dgr_id')
        );
        $reserva = \App\Models\ValorAcumuladoReserva::findOrFail($vinculo->var_id);

        // Crédito = 1500 * 0,50 = 750,00.
        $this->assertEqualsWithDelta(750.00, (float) $credito->maio, 0.01);
        // Valor final = fixo (5000*0,5=2500) + variável ((8500-5000)*0,5=1750) + crédito 750
        //   − CUO (8600 × 0,13275 × 0,60 = 684,99) = 4315,01.
        $this->assertEqualsWithDelta(4315.01, (float) $faturamento->maio, 0.01);
        $this->assertEqualsWithDelta(8600.0, (float) $geracao->maio, 1e-6, 'Geração BRUTA materializada.');

        // Saldo coerente: 1192 + 2178 − 1500 = 1870.
        $this->assertEqualsWithDelta(1870.0, (float) $reserva->total, 1e-6);
        $this->assertEqualsWithDelta(1870.0, $resposta->saldoReservaDepoisKwh, 1e-6);

        // Snapshot de estorno criado.
        $this->assertSame(1, \App\Models\HistoricoEstorno::count());
    }

    public function test_caso_eder_valor_final_5700_65(): void
    {
        // Geração líquida 9850 = bruta 9858 − consumo_descontavel.
        // consumo_descontavel = 8 -> consumo = 108 (108 − 100 desconto trifásico = 8).
        $usina = $this->usina(media: 12911, menor: 7636, tarifa: 0.51, rede: 'Trifásico');

        // Reserva reconstruída (FIFO cross-ano), saldo total 6295 >= faltante 3061.
        $this->lote((int) $usina->usi_id, '2025-11-01', 124, 0.51);
        $this->lote((int) $usina->usi_id, '2025-12-01', 1192, 0.51);
        $this->lote((int) $usina->usi_id, '2026-01-01', 2178, 0.51);
        $this->lote((int) $usina->usi_id, '2026-02-01', 1040, 0.51);
        $this->lote((int) $usina->usi_id, '2026-03-01', 1761, 0.51);

        $resposta = $this->service()->calcularMes(
            $usina,
            2026,
            5,
            [
                'geracao_bruta_kwh' => 9858,
                'consumo' => 108,
                'fatura_energia' => 98.77,
                'adicional_cuo' => 0,
            ],
            persistir: true,
            userId: $this->userId,
            idempotencyKey: 'k-eder',
        );

        $r = $resposta->resultado;

        $this->assertSame(389436, $r->valorFixo->emCentavos(), 'Valor Fixo');
        $this->assertSame(112914, $r->valorVariavel->emCentavos(), 'Valor Variável');
        $this->assertSame(156111, $r->credito->emCentavos(), 'Crédito');
        $this->assertSame(88396, $r->cuo->emCentavos(), 'CUO');
        $this->assertSame(570065, $r->valorFinal->emCentavos(), 'Valor Final 5700,65');

        // Faturamento materializado bate com o valor final.
        $vinculo = \App\Models\CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
            ->where('ano', 2026)->firstOrFail();
        $faturamento = \App\Models\FaturamentoUsina::findOrFail($vinculo->fa_id);
        $this->assertEqualsWithDelta(5700.65, (float) $faturamento->maio, 0.01);
    }

    public function test_expiracao_indo_pra_frente_paga_receita_no_valor_final(): void
    {
        // Geração == média -> faltante 0, nada consumido. Um lote ANTIGO vence dentro
        // do mês calculado e NÃO foi consumido -> expira inteiro e vira receita (§7).
        $usina = $this->usina(media: 16740, menor: 9000, tarifa: 0.51, rede: 'Trifásico');

        // ago/2025 vence ago/2025 + 180d = 2026-01-28, já passou do fim de fev/2026.
        $this->lote((int) $usina->usi_id, '2025-08-01', 11800, 0.51);

        $resposta = $this->service()->calcularMes(
            $usina,
            2026,
            2,
            ['geracao_bruta_kwh' => 16740, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true,
            userId: $this->userId,
            idempotencyKey: 'k-expira',
        );

        $r = $resposta->resultado;

        // Crédito 0 (sem déficit); receita de expiração = 11800 * 0,51 = 6018,00.
        $this->assertSame(0, $r->credito->emCentavos(), 'Crédito 0 (sem déficit)');
        $this->assertSame(601800, $r->receitaExpiracao->emCentavos(), 'Receita expiração 6018,00');
        $this->assertCount(1, $r->expiracoes);

        // A receita compõe o valor final (§2): fixo + variável + crédito − cuo + receita.
        $this->assertSame(
            $r->valorFixo->emCentavos() + $r->valorVariavel->emCentavos()
                + $r->credito->emCentavos() - $r->cuo->emCentavos()
                + $r->receitaExpiracao->emCentavos(),
            $r->valorFinal->emCentavos(),
            'Receita de expiração entra no valor final (PAGA indo pra frente).',
        );

        // Lançamento EXPIRACAO no ledger (mesmo movimento de saída).
        $expiracao = CreditoLedger::where('usi_id', $usina->usi_id)
            ->where('tipo', CreditoLedger::TIPO_EXPIRACAO)->get();
        $this->assertCount(1, $expiracao);
        $this->assertEqualsWithDelta(-11800.0, (float) $expiracao->first()->kwh, 1e-6);

        // Saldo zera após expirar.
        $this->assertEqualsWithDelta(0.0, $resposta->saldoReservaDepoisKwh, 1e-6);
    }

    public function test_idempotencia_duas_chamadas_nao_duplicam_no_ledger(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $this->lote((int) $usina->usi_id, '2025-12-01', 1192, 0.50);

        $input = ['geracao_bruta_kwh' => 8600, 'consumo' => 200, 'fatura_energia' => 0];

        $this->service()->calcularMes($usina, 2026, 5, $input, persistir: true, userId: $this->userId, idempotencyKey: 'dup');
        $apos1 = CreditoLedger::count();

        // Mesma chave determinística por (usi, tipo, origem, evento) -> updateOrCreate.
        $this->service()->calcularMes($usina, 2026, 5, $input, persistir: true, userId: $this->userId, idempotencyKey: 'dup');
        $apos2 = CreditoLedger::count();

        $this->assertSame($apos1, $apos2, '2x o mesmo mês não duplica lançamentos no ledger.');
    }

    public function test_recalculo_que_reduz_lancamentos_nao_deixa_orfaos(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $usiId = (int) $usina->usi_id;

        // 1ª apuração de maio: geração ALTA (12000 > média) -> gera CREDITO (excedente 2000).
        $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 12000, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'mai-v1'
        );
        $this->assertSame(1, CreditoLedger::where('usi_id', $usiId)
            ->porTipo(CreditoLedger::TIPO_CREDITO)->count(), 'Deve haver 1 CREDITO após a 1ª apuração');

        // Recálculo de maio com geração BAIXA (8000 < média) -> NÃO há excedente; o
        // CREDITO anterior deve sumir (não virar órfão). Sem reserva, déficit é pago.
        $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 8000, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'mai-v2'
        );

        $this->assertSame(0, CreditoLedger::where('usi_id', $usiId)
            ->where('competencia_evento', '2026-05-01')
            ->porTipo(CreditoLedger::TIPO_CREDITO)->count(),
            'O CREDITO da apuração anterior não pode permanecer órfão após o recálculo');

        // Saldo do ledger coerente com a coluna materializada (sem divergência).
        $saldoLedger = (float) CreditoLedger::where('usi_id', $usiId)->sum('kwh');
        $this->assertEqualsWithDelta(0.0, $saldoLedger, 0.001, 'Sem excedente nem reserva, saldo é zero');
    }

    // ----------------------------------------------------------------------
    // Scaffolding de banco (mesma cadeia de FKs do CreditoLedgerPersistenceTest).
    // ----------------------------------------------------------------------

    private function usina(float $media, float $menor, float $tarifa, ?string $rede): Usina
    {
        $now = now();

        $endId = \DB::table('endereco')->insertGetId(
            ['created_at' => $now, 'updated_at' => $now], 'end_id'
        );

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
            'media' => $media, 'menor_geracao' => $menor,
            'created_at' => $now, 'updated_at' => $now,
        ]), 'dger_id');

        $comId = \DB::table('comercializacao')->insertGetId([
            'valor_kwh' => $tarifa,
            'valor_fixo' => 0,
            'cia_energia' => 'TESTE',
            'valor_final_media' => 0,
            'previsao_conexao' => $now->toDateString(),
            'fio_b' => 0.13275,
            'percentual_lei' => 60.0,
            'created_at' => $now, 'updated_at' => $now,
        ], 'com_id');

        $venId = \DB::table('vendedor')->insertGetId([
            'nome' => 'Vendedor Teste',
            'patente' => 'junior',
            'created_at' => $now, 'updated_at' => $now,
        ], 'ven_id');

        $usiId = \DB::table('usina')->insertGetId([
            'cli_id' => $cliId,
            'dger_id' => $dgerId,
            'com_id' => $comId,
            'ven_id' => $venId,
            'rede' => $rede,
            'created_at' => $now, 'updated_at' => $now,
        ], 'usi_id');

        return Usina::with(['comercializacao', 'dadoGeracao'])->findOrFail($usiId);
    }

    /** Lança um CREDITO em aberto (entrada positiva) no ledger. */
    private function lote(int $usiId, string $origem, float $kwh, float $tarifa): void
    {
        CreditoLedger::create([
            'usi_id' => $usiId,
            'competencia_origem' => $origem,
            'competencia_evento' => $origem,
            'tipo' => CreditoLedger::TIPO_CREDITO,
            'kwh' => $kwh,
            'tarifa_kwh' => $tarifa,
            'valor_reais' => round($kwh * $tarifa, 2),
            'vencimento' => date('Y-m-d', strtotime($origem . ' +180 days')),
        ]);
    }
}
