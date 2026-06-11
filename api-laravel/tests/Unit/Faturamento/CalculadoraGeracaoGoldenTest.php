<?php

namespace Tests\Unit\Faturamento;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testes GOLDEN do cálculo de faturamento de geração.
 *
 * Codificam os casos de validação canônicos de docs/calculo/REGRAS_DE_CALCULO.md §13.
 * São TDD: nascem VERMELHOS porque a camada App\Domain\Faturamento ainda não existe (Fase 1).
 * Critério de aceite do redesenho: estes testes devem ficar verdes SEM ajustar os valores esperados.
 *
 * Tolerância de R$ 0,05 por termo absorve arredondamento de menor_geracao/tarifa documentado.
 *
 * @group golden
 */
class CalculadoraGeracaoGoldenTest extends TestCase
{
    private const TOL = 0.05;

    /**
     * Eder Alcione Stalter — UC 562606800 — Maio/2026.
     * Fixo 3894,36 + Variável 1129,14 + Crédito 1561,11 − CUO 883,96 = 5700,65.
     *
     * Reserva (reconstruída da geração real, FIFO cross-ano):
     *   nov/25 +1443, dez/25 +1192, jan/26 +2178, fev/26 +1040, mar/26 +1761
     *   abr/26 consome 1319 (de nov/25); mai/26 consome 3053 (FIFO cross-ano).
     */
    #[Test]
    public function eder_maio_2026_total_5700_65(): void
    {
        $this->markTestIncomplete(
            'Aguarda Fase 1: App\Domain\Faturamento\Calculadora\CalculadoraGeracaoLinear. '
            . 'Esperado: Fixo≈3894,36 Variável≈1129,14 Crédito≈1561,11 CUO≈883,96 Total≈5700,65 '
            . '(media=12911, menor_geracao=7644, tarifa=0,51, fio_b=0,13275, percentual_lei=60).'
        );

        // Esqueleto do teste-alvo (descomentar quando a classe existir na Fase 1):
        //
        // $entrada = EntradaCalculoMes::deArray([
        //     'geracao_liquida_kwh' => 9858, 'media_kwh' => 12911, 'menor_geracao_kwh' => 7644,
        //     'tarifa' => 0.51, 'fio_b' => 0.13275, 'percentual_lei' => 60.0,
        //     'fatura_energia' => /* confirmar */, 'adicional_cuo' => 0,
        //     'competencia' => Competencia::de(2026, 5),
        // ]);
        // $reserva = /* ledger reconstruído com lotes nov/25..mar/26 */;
        // $r = (new CalculadoraGeracaoLinear)->calcular($entrada, $reserva);
        //
        // $this->assertEqualsWithDelta(3894.36, $r->valorFixo->emReais(), self::TOL);
        // $this->assertEqualsWithDelta(1129.14, $r->valorVariavel->emReais(), self::TOL);
        // $this->assertEqualsWithDelta(1561.11, $r->credito->emReais(), self::TOL);
        // $this->assertEqualsWithDelta(883.96,  $r->cuo->emReais(), self::TOL);
        // $this->assertEqualsWithDelta(5700.65, $r->valorFinal->emReais(), self::TOL);
    }

    /**
     * Luci Vilce Penkaç — UC 19771547 — Set/2025.
     * Fixo 3342,18 + Variável 2154,45 − CUO 695,13 = 4801,50 (sem crédito de reserva).
     */
    #[Test]
    public function luci_setembro_2025_total_4801_50(): void
    {
        $this->markTestIncomplete(
            'Aguarda Fase 1. Esperado: Fixo≈3342,18 Variável≈2154,45 CUO≈695,13 Total≈4801,50.'
        );

        // $this->assertEqualsWithDelta(4801.50, $r->valorFinal->emReais(), self::TOL);
    }

    /**
     * Colina Eco Solar — UC 3085733401 — Fev/2026.
     * Geração (16740) == média (16740) → faltante 0 → Crédito = R$ 0,00.
     * Regressão do bug "crédito sem déficit" (sistema antigo creditava R$ 7.080).
     */
    #[Test]
    public function colina_fevereiro_2026_credito_zero_sem_deficit(): void
    {
        $this->markTestIncomplete(
            'Aguarda Fase 1. Esperado: faltante=0 → Crédito = R$ 0,00 (NÃO os R$ 7.080 do sistema antigo).'
        );

        // $this->assertSame(0.0, $r->credito->emReais());
    }

    /**
     * FIFO cross-ano: havendo reserva de ano anterior, ela é consumida ANTES da do ano corrente.
     * Regressão do Problema 1 (Eder: 1192 kWh de dez/2025 ignorados pelo sistema antigo).
     */
    #[Test]
    public function fifo_consome_credito_mais_antigo_de_ano_anterior_primeiro(): void
    {
        $this->markTestIncomplete(
            'Aguarda Fase 1: MotorFifo. Dado lotes [2025-12: 1192kWh] e [2026-01: 2178kWh] e faltante 1500kWh, '
            . 'deve consumir 1192 de 2025-12 e 308 de 2026-01 (mais antigo primeiro).'
        );
    }

    /**
     * Crédito nunca excede o saldo da reserva.
     * Regressão do Problema 3b (creditadoTabela creditava (media-valor)*kwh sem checar saldo).
     */
    #[Test]
    public function credito_nunca_excede_saldo_da_reserva(): void
    {
        $this->markTestIncomplete(
            'Aguarda Fase 1. Dado faltante 3000kWh mas reserva total 1000kWh, crédito deve usar no máximo 1000kWh.'
        );
    }

    /**
     * Crédito expirado não é contado em dobro.
     * Regressão do Problema 3a (somado em valorPago E em creditoGerado).
     */
    #[Test]
    public function credito_expirado_nao_e_contado_em_dobro(): void
    {
        $this->markTestIncomplete(
            'Aguarda Fase 1. Crédito expirado entra como receita UMA vez (mês do vencimento), '
            . 'não soma simultaneamente ao termo Crédito e ao faturamento.'
        );
    }
}
