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

    public function test_mes_usa_reserva_no_inicio_do_mes_nao_o_saldo_atual(): void
    {
        // Regressão: calcular um mês deve usar a reserva COMO ESTAVA no início dele,
        // não o saldo total atual (que já reflete o consumo de meses posteriores).
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $usiId = (int) $usina->usi_id;

        // Abril: geração alta (12000) -> guarda excedente 2000 na reserva.
        $this->service()->calcularMes(
            $usina, 2026, 4,
            ['geracao_bruta_kwh' => 12000, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'abr'
        );

        // Maio: geração baixa (8000, faltante 2000) -> consome os 2000 de abril.
        $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 8000, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'mai'
        );

        // Saldo TOTAL atual agora é 0 (abril guardou 2000, maio consumiu 2000).
        $this->assertEqualsWithDelta(0.0, (float) CreditoLedger::where('usi_id', $usiId)->sum('kwh'), 0.001);

        // PREVIEW de maio de novo: deve usar a reserva NO INÍCIO de maio (2000 de abril),
        // creditando 2000 × 0,50 = R$ 1.000 — NÃO zero (que seria o saldo total atual).
        $preview = $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 8000, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: false
        );

        $this->assertEqualsWithDelta(1000.0, $preview->resultado->credito->emReais(), 0.001,
            'Maio deve creditar contra a reserva do início do mês (2000 de abril), não o saldo total (0).');
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

    /**
     * GARANTIA: reverter um lançamento restaura EXATAMENTE o estado anterior —
     * ledger, colunas materializadas, cache PDF — como se o mês nunca tivesse sido
     * lançado, e o histórico registra lançamento + reversão.
     */
    public function test_reverter_lancamento_restaura_estado_exato_anterior(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $usiId = (int) $usina->usi_id;

        // Constrói a reserva VIA O MOTOR (jan com excedente 3370) — assim colunas e
        // ledger nascem consistentes (como em produção), não via helper que burla.
        $this->service()->calcularMes(
            $usina, 2026, 1,
            ['geracao_bruta_kwh' => 13370, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'jan'
        );

        $vinculo = \App\Models\CreditosDistribuidosUsina::where('usi_id', $usiId)->where('ano', 2026)->firstOrFail();
        $colCredito = fn () => (float) \App\Models\CreditosDistribuidos::find($vinculo->cd_id)->maio;
        $colFatur   = fn () => (float) \App\Models\FaturamentoUsina::find($vinculo->fa_id)->maio;
        $colReserva = fn () => (float) (\App\Models\ValorAcumuladoReserva::find($vinculo->var_id)->total ?? 0);
        $ledgerVivo = fn () => (float) CreditoLedger::where('usi_id', $usiId)->whereNull('estornado_em')->sum('kwh');

        // ---- ESTADO ANTES DO LANÇAMENTO DE MAIO (pós-janeiro: reserva 3370) ----
        $antes = [
            'credito' => $colCredito(),
            'fatur' => $colFatur(),
            'reserva' => $colReserva(),
            'ledger' => $ledgerVivo(),
        ];
        $this->assertEqualsWithDelta(3370.0, $antes['ledger'], 0.001, 'Reserva de janeiro = 3370');
        $this->assertEqualsWithDelta(3370.0, $antes['reserva'], 0.001, 'Coluna reserva consistente com o ledger (P1)');

        // ---- LANÇA maio/2026 (com userId -> gera snapshot) ----
        $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 8600, 'consumo' => 200, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'lanc-mai'
        );
        // Mudou de fato? (crédito 750, ledger consumiu 1500 -> 1870, +1 CONSUMO do lote de jan)
        $this->assertEqualsWithDelta(750.0, $colCredito(), 0.01, 'Lançamento mudou o crédito');
        $this->assertEqualsWithDelta(1870.0, $ledgerVivo(), 0.001, 'Lançamento consumiu a reserva');
        $this->assertSame(2, \App\Models\HistoricoEstorno::where('usi_id', $usiId)->whereNull('revertido_em')->count(), 'jan + maio em aberto');

        // ---- REVERTE maio/2026 ----
        app(\App\Services\EstornoGeracaoService::class)->estornar($usina, 2026, 5, $this->userId);

        // ---- ESTADO DEPOIS DA REVERSÃO == ANTES DO LANÇAMENTO ----
        $this->assertEqualsWithDelta($antes['credito'], $colCredito(), 0.001, 'Crédito voltou ao anterior');
        $this->assertEqualsWithDelta($antes['fatur'], $colFatur(), 0.001, 'Faturamento voltou ao anterior');
        $this->assertEqualsWithDelta($antes['reserva'], $colReserva(), 0.001, 'Reserva total voltou ao anterior');
        $this->assertEqualsWithDelta($antes['ledger'], $ledgerVivo(), 0.001, 'Saldo do ledger voltou (3370): consumos estornados devolvem a energia');

        // Lançamentos do mês ficaram marcados estornados (não some, fica auditável §10).
        $estornados = CreditoLedger::where('usi_id', $usiId)
            ->whereDate('competencia_evento', '2026-05-01')->whereNotNull('estornado_em')->count();
        $this->assertSame(1, $estornados, 'O CONSUMO de maio ficou estornado');

        // Cache PDF do mês removido.
        $this->assertSame(0, \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->whereDate('competencia', '2026-05-01')->count());

        // Histórico: maio marcado como revertido; jan continua em aberto (auditoria lançamento+reversão).
        $this->assertSame(1, \App\Models\HistoricoEstorno::where('usi_id', $usiId)->whereNull('revertido_em')->count(), 'jan permanece em aberto');
        $maioRev = \App\Models\HistoricoEstorno::where('usi_id', $usiId)->where('mes', 5)->whereNotNull('revertido_em')->first();
        $this->assertNotNull($maioRev, 'maio registrado como revertido');
        $this->assertSame($this->userId, (int) $maioRev->user_id_estorno, 'reversão registra quem estornou');

        // ---- RE-LANÇAR após reverter funciona e reproduz o mesmo resultado ----
        $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 8600, 'consumo' => 200, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'relanc-mai'
        );
        $this->assertEqualsWithDelta(750.0, $colCredito(), 0.01, 'Re-lançar reproduz o crédito');
        $this->assertEqualsWithDelta(1870.0, $ledgerVivo(), 0.001, 'Re-lançar reproduz o saldo (sem duplicar)');
    }

    public function test_reverter_so_o_ultimo_mes_lancado(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $this->lote((int) $usina->usi_id, '2026-01-01', 5000, 0.50);

        // Lança abril e depois maio.
        $this->service()->calcularMes($usina, 2026, 4, ['geracao_bruta_kwh' => 8600, 'consumo' => 0, 'fatura_energia' => 0], persistir: true, userId: $this->userId, idempotencyKey: 'abr');
        $this->service()->calcularMes($usina, 2026, 5, ['geracao_bruta_kwh' => 8600, 'consumo' => 0, 'fatura_energia' => 0], persistir: true, userId: $this->userId, idempotencyKey: 'mai');

        // Reverter ABRIL (não é o último) deve falhar.
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\EstornoGeracaoService::class)->estornar($usina, 2026, 4, $this->userId);
    }

    /**
     * GARANTIA do caminho mais arriscado: reverter um mês que EXPIROU créditos
     * deve RESSUSCITAR o crédito expirado — o lote volta a ter saldo disponível,
     * como se a expiração nunca tivesse ocorrido.
     */
    public function test_reverter_mes_que_expirou_creditos_ressuscita_o_lote(): void
    {
        $usina = $this->usina(media: 16740, menor: 9000, tarifa: 0.51, rede: 'Trifásico');
        $usiId = (int) $usina->usi_id;

        // ago/2025 vence em 2026-01-28; ao lançar fev/2026 expira inteiro (11800 kWh).
        $this->lote($usiId, '2025-08-01', 11800, 0.51);

        $saldoVivo = fn () => (float) CreditoLedger::where('usi_id', $usiId)->whereNull('estornado_em')->sum('kwh');
        $this->assertEqualsWithDelta(11800.0, $saldoVivo(), 1e-6, 'Antes: lote inteiro disponível');

        // LANÇA fev/2026 -> expira o lote (saldo vivo cai a 0: +CREDITO 11800, -EXPIRACAO 11800).
        $this->service()->calcularMes(
            $usina, 2026, 2,
            ['geracao_bruta_kwh' => 16740, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'exp-lanc'
        );
        $this->assertEqualsWithDelta(0.0, $saldoVivo(), 1e-6, 'Após expirar: saldo vivo zerado');
        $this->assertSame(1, CreditoLedger::where('usi_id', $usiId)->where('tipo', CreditoLedger::TIPO_EXPIRACAO)->whereNull('estornado_em')->count());

        // REVERTE fev/2026 -> a EXPIRACAO é estornada e o lote ressuscita.
        app(\App\Services\EstornoGeracaoService::class)->estornar($usina, 2026, 2, $this->userId);

        $this->assertEqualsWithDelta(11800.0, $saldoVivo(), 1e-6, 'Reverter ressuscita o crédito expirado');
        $this->assertSame(0, CreditoLedger::where('usi_id', $usiId)->where('tipo', CreditoLedger::TIPO_EXPIRACAO)->whereNull('estornado_em')->count(), 'EXPIRACAO ficou estornada');

        // E o saldo do mês seguinte enxerga o crédito de volta (re-expira se relançado).
        $r2 = $this->service()->calcularMes(
            $usina, 2026, 2,
            ['geracao_bruta_kwh' => 16740, 'consumo' => 0, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'exp-relanc'
        )->resultado;
        $this->assertSame(601800, $r2->receitaExpiracao->emCentavos(), 'Re-lançar reproduz a expiração 6018,00 (sem duplicar)');
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
