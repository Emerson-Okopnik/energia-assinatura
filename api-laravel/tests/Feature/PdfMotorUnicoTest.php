<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Faturamento\FaturamentoService;
use App\Http\ViewModels\UsinaPdfViewModel;
use App\Models\CreditoLedger;
use App\Models\DadosGeracaoReal;
use App\Models\DadosGeracaoRealUsina;
use App\Models\DemonstrativoCreditosPdf;
use App\Models\GeracaoFaturamentoPdf;
use App\Models\Usina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 — o PDF LÊ do motor único (FaturamentoService), sem recalcular.
 *
 * Cobre:
 *  1. Ao persistir, o FaturamentoService grava geracao_faturamento_pdf e
 *     demonstrativo_creditos_pdf coerentes com os termos do motor (idempotente).
 *  2. O ViewModel do PDF (UsinaPdfViewModel) usa EXATAMENTE os termos do motor
 *     (fixo/injetado/creditado/cuo/valor_final) — sem fórmula no controller/Blade.
 */
class PdfMotorUnicoTest extends TestCase
{
    use RefreshDatabase;

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

    private function service(): FaturamentoService
    {
        return $this->app->make(FaturamentoService::class);
    }

    public function test_persistir_grava_cache_pdf_coerente_com_o_motor(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $this->lote((int) $usina->usi_id, '2025-12-01', 1192, 0.50);
        $this->lote((int) $usina->usi_id, '2026-01-01', 2178, 0.50);

        // Geração líquida 8500 (bruta 8600, consumo 200, desconto trif. 100 -> -100).
        // Faltante = 1500 -> consome 1192 (dez) + 308 (jan) via FIFO.
        $resposta = $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 8600, 'consumo' => 200, 'fatura_energia' => 0],
            persistir: true, userId: $this->userId, idempotencyKey: 'pdf-1',
        );

        $r = $resposta->resultado;

        // 1) geracao_faturamento_pdf reflete os termos do motor.
        $gfp = GeracaoFaturamentoPdf::where('usi_id', $usina->usi_id)
            ->whereDate('competencia', '2026-05-01')->firstOrFail();

        $this->assertEqualsWithDelta(8600.0, (float) $gfp->geracao_kwh, 1e-6);
        $this->assertEqualsWithDelta($r->valorFixo->emReais(), (float) $gfp->valor_fixo, 0.01);
        $this->assertEqualsWithDelta($r->valorVariavel->emReais(), (float) $gfp->injetado, 0.01);
        $this->assertEqualsWithDelta($r->credito->emReais(), (float) $gfp->creditado, 0.01);
        $this->assertEqualsWithDelta($r->cuo->emReais(), (float) $gfp->cuo, 0.01);
        $this->assertEqualsWithDelta($r->valorFinal->emReais(), (float) $gfp->valor_final, 0.01);

        // 2) demonstrativo_creditos_pdf: creditado_kwh = soma do consumo FIFO (1500),
        //    e meses_utilizados lista as origens (dez/jan).
        $dcp = DemonstrativoCreditosPdf::where('usi_id', $usina->usi_id)
            ->whereDate('competencia', '2026-05-01')->firstOrFail();

        $this->assertEqualsWithDelta(1500.0, (float) $dcp->creditado_kwh, 1e-6);
        $this->assertEqualsWithDelta(0.0, (float) $dcp->guardado_kwh, 1e-6);
        $this->assertStringContainsString('Dezembro/25', (string) $dcp->meses_utilizados);
        $this->assertStringContainsString('Janeiro/26', (string) $dcp->meses_utilizados);
        $this->assertNotNull($dcp->vencimento);
    }

    public function test_persistir_cache_pdf_e_idempotente(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $input = ['geracao_bruta_kwh' => 12000, 'consumo' => 0, 'fatura_energia' => 0];

        $this->service()->calcularMes($usina, 2026, 5, $input, persistir: true, userId: $this->userId, idempotencyKey: 'idem-1');
        $this->service()->calcularMes($usina, 2026, 5, $input, persistir: true, userId: $this->userId, idempotencyKey: 'idem-2');

        $this->assertSame(1, GeracaoFaturamentoPdf::where('usi_id', $usina->usi_id)->whereDate('competencia', '2026-05-01')->count());
        $this->assertSame(1, DemonstrativoCreditosPdf::where('usi_id', $usina->usi_id)->whereDate('competencia', '2026-05-01')->count());

        // Excedente 2000 guardado -> cache reflete o guardado do motor.
        $dcp = DemonstrativoCreditosPdf::where('usi_id', $usina->usi_id)->whereDate('competencia', '2026-05-01')->firstOrFail();
        $this->assertEqualsWithDelta(2000.0, (float) $dcp->guardado_kwh, 1e-6);
    }

    public function test_viewmodel_usa_termos_do_motor_sem_recalcular(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');

        // Geração real de maio/2026 = 8600 (vai virar a entrada bruta do motor).
        $this->geracaoReal((int) $usina->usi_id, (int) $usina->cli_id, 2026, ['maio' => 8600]);

        $vm = (new UsinaPdfViewModel($this->service()))->montar($usina, 2026, 5);

        // O motor (sem reserva, fatura 0) para maio:
        //   fixo = 5000*0,5 = 2500; injetado = (8600-5000)*0,5 = 1800 (líquida=bruta,
        //   consumo 0); crédito 0; CUO = 8600*0,13275*0,60 = 684,99;
        //   valor_final = 2500 + 1800 + 0 − 684,99 = 3615,01.
        $resposta = $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 8600, 'fatura_energia' => 0],
            persistir: false,
        );
        $r = $resposta->resultado;

        $mes = $vm['dadosMensais']['Maio/26'];
        $this->assertEqualsWithDelta($r->valorFixo->emReais(), $mes['fixo'], 0.01);
        $this->assertEqualsWithDelta($r->valorVariavel->emReais(), $mes['injetado'], 0.01);
        $this->assertEqualsWithDelta($r->credito->emReais(), $mes['creditado'], 0.01);
        $this->assertEqualsWithDelta($r->cuo->emReais(), $mes['cuo'], 0.01);
        $this->assertEqualsWithDelta($r->valorFinal->emReais(), $mes['valor_final'], 0.01);

        // Cabeçalho "Valor a Receber" = valor_final do mês selecionado (do motor).
        $this->assertEqualsWithDelta($r->valorFinal->emReais(), $vm['valorReceber'], 0.01);
        $this->assertEqualsWithDelta(3615.01, $vm['valorReceber'], 0.01);

        // §3.1: auditoria e parâmetros NÃO existem mais no ViewModel.
        $this->assertArrayNotHasKey('auditoria', $vm);
        $this->assertArrayNotHasKey('parametros', $vm);

        // Demonstrativo: dadosCreditos é o slice (≤6 meses) pronto para o Blade.
        // Sem excedente (8600 < média) nem reserva: guardado 0, FIFO vazio.
        $this->assertArrayHasKey('dadosCreditos', $vm);
        $this->assertLessThanOrEqual(6, count($vm['dadosCreditos']));
        $linha = $vm['dadosCreditos']['Maio/26'];
        $this->assertEqualsWithDelta(0.0, $linha['guardado'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $linha['creditado_kwh'], 1e-9);
        $this->assertArrayHasKey('vencimento', $linha);
        $this->assertSame('-', $linha['meses_utilizados']);
        // Crédito vencido convertido em receita (R$) — 0 neste cenário.
        $this->assertEqualsWithDelta(0.0, $linha['convertido_receita'], 0.01);
        $this->assertFalse($vm['temConvertidoReceita']);

        // Totais com nomes honestos (motor): kWh guardado, CUO, valor final.
        $this->assertArrayHasKey('totalGuardadoKwh', $vm);
        $this->assertArrayHasKey('totalCuo', $vm);
        $this->assertArrayHasKey('totalValorFinal', $vm);

        // CO2/árvores vêm do controller/motor (não há @php no Blade).
        $this->assertEqualsWithDelta(round(8600 * 0.4, 2), $vm['co2Evitado'], 0.01);
        $this->assertEqualsWithDelta(round((8600 * 0.4) / 20, 2), $vm['arvores'], 0.01);
    }

    public function test_viewmodel_expoe_credito_vencido_convertido_em_receita(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');

        // Lote ago/2025 (vencimento 2026-01-28 < fim de maio/2026) -> expira.
        $this->lote((int) $usina->usi_id, '2025-08-01', 1000, 0.50);

        // Geração 12000 > média 10000: faltante 0, FIFO não consome o lote —
        // ele expira inteiro e o motor o PAGA como receita no Valor Final.
        $this->geracaoReal((int) $usina->usi_id, (int) $usina->cli_id, 2026, ['maio' => 12000]);

        $vm = (new UsinaPdfViewModel($this->service()))->montar($usina, 2026, 5);

        // Referência: mesma chamada direta ao motor (preview).
        $resposta = $this->service()->calcularMes(
            $usina, 2026, 5,
            ['geracao_bruta_kwh' => 12000, 'fatura_energia' => 0],
            persistir: false,
        );
        $receitaEsperada = $resposta->resultado->receitaExpiracao->emReais();

        // 1000 kWh × 0,50 = R$ 500,00 convertidos em receita (§3.1 — nunca "perda").
        $this->assertGreaterThan(0.0, $receitaEsperada);
        $this->assertEqualsWithDelta(500.0, $receitaEsperada, 0.01);

        $linha = $vm['dadosCreditos']['Maio/26'];
        $this->assertEqualsWithDelta($receitaEsperada, $linha['convertido_receita'], 0.01);
        $this->assertTrue($vm['temConvertidoReceita']);
    }

    public function test_viewmodel_deriva_fatura_do_cache_existente(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $this->geracaoReal((int) $usina->usi_id, (int) $usina->cli_id, 2026, ['maio' => 8600]);

        // Cache PRÉ-existente com um CUO que embute uma fatura de R$ 100,00:
        //   CUO_cache = 100 + 8600*0,13275*0,60 = 784,99.
        $fioBTotal = 8600 * 0.13275 * 0.60;
        GeracaoFaturamentoPdf::create([
            'usi_id' => $usina->usi_id,
            'competencia' => '2026-05-01',
            'geracao_kwh' => 8600,
            'valor_fixo' => 0, 'injetado' => 0, 'creditado' => 0,
            'cuo' => 100 + $fioBTotal,
            'valor_final' => 0,
        ]);

        $vm = (new UsinaPdfViewModel($this->service()))->montar($usina, 2026, 5);

        // O motor recebe fatura derivada (~100) -> CUO recalculado ~784,99.
        $this->assertEqualsWithDelta(100 + $fioBTotal, $vm['dadosMensais']['Maio/26']['cuo'], 0.02);
    }

    // ----------------------------------------------------------------------
    // Scaffolding (mesma cadeia de FKs do FaturamentoServiceTest).
    // ----------------------------------------------------------------------

    private function usina(float $media, float $menor, float $tarifa, ?string $rede): Usina
    {
        $now = now();

        $endId = \DB::table('endereco')->insertGetId(['created_at' => $now, 'updated_at' => $now], 'end_id');

        $cliId = \DB::table('cliente')->insertGetId([
            'nome' => 'Cliente Teste', 'cpf_cnpj' => '00000000000', 'end_id' => $endId,
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
            'valor_kwh' => $tarifa, 'valor_fixo' => $menor * $tarifa, 'cia_energia' => 'TESTE',
            'valor_final_media' => 0, 'previsao_conexao' => $now->toDateString(),
            'fio_b' => 0.13275, 'percentual_lei' => 60.0,
            'created_at' => $now, 'updated_at' => $now,
        ], 'com_id');

        $venId = \DB::table('vendedor')->insertGetId([
            'nome' => 'Vendedor Teste', 'patente' => 'junior',
            'created_at' => $now, 'updated_at' => $now,
        ], 'ven_id');

        $usiId = \DB::table('usina')->insertGetId([
            'cli_id' => $cliId, 'dger_id' => $dgerId, 'com_id' => $comId, 'ven_id' => $venId,
            'rede' => $rede, 'created_at' => $now, 'updated_at' => $now,
        ], 'usi_id');

        return Usina::with(['comercializacao', 'dadoGeracao'])->findOrFail($usiId);
    }

    /** @param array<string, float> $valores coluna => kWh */
    private function geracaoReal(int $usiId, int $cliId, int $ano, array $valores): void
    {
        $dgr = DadosGeracaoReal::create($valores);
        DadosGeracaoRealUsina::create([
            'usi_id' => $usiId, 'cli_id' => $cliId, 'dgr_id' => $dgr->dgr_id, 'ano' => $ano,
        ]);
    }

    private function lote(int $usiId, string $origem, float $kwh, float $tarifa): void
    {
        CreditoLedger::create([
            'usi_id' => $usiId, 'competencia_origem' => $origem, 'competencia_evento' => $origem,
            'tipo' => CreditoLedger::TIPO_CREDITO, 'kwh' => $kwh, 'tarifa_kwh' => $tarifa,
            'valor_reais' => round($kwh * $tarifa, 2),
            'vencimento' => date('Y-m-d', strtotime($origem . ' +180 days')),
        ]);
    }
}
