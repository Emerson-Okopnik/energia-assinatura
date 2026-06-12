<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Calculo\CalculadoraGeracaoLinear;
use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Reais;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Testes GOLDEN do cálculo de faturamento de geração.
 *
 * Codificam os casos de validação canônicos de docs/calculo/REGRAS_DE_CALCULO.md §13.
 * Critério de aceite: estes testes devem ficar verdes SEM ajustar os valores esperados.
 *
 * Tolerância de R$ 0,05 por termo absorve arredondamento de menor_geracao/tarifa documentado.
 */
#[Group('golden')]
class CalculadoraGeracaoGoldenTest extends TestCase
{
    private const TOL = 0.05;

    private function calculadora(): CalculadoraGeracaoLinear
    {
        return new CalculadoraGeracaoLinear();
    }

    /**
     * Constrói um lote de reserva (a competência de origem define a ordem FIFO).
     */
    private function lote(int $ano, int $mes, float $kwh): LoteReserva
    {
        $origem = Competencia::de($ano, $mes);

        return new LoteReserva(
            competenciaOrigem: $origem,
            saldoKwh: Kwh::de($kwh),
            vencimento: $origem->vencimentoEmDias(180),
        );
    }

    /**
     * Eder Alcione Stalter — UC 562606800 — Maio/2026.
     * Fixo 3894,36 + Variável 1129,14 + Crédito 1561,11 − CUO 883,96 = 5700,65.
     *
     * Geração líquida = 9850 (consumo final reconciliado com a planilha do cliente).
     * Reserva com saldo suficiente (lotes anteriores totalizando >= 3061 kWh).
     */
    #[Test]
    public function eder_maio_2026_total_5700_65(): void
    {
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 9850,
            'media_kwh' => 12911,
            'menor_geracao_kwh' => 7636,
            'geracao_bruta_kwh' => 9858,
            'tarifa' => 0.51,
            'fio_b' => 0.13275,
            'percentual_lei' => 60.0,
            'fatura_energia' => 98.77,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2026, 5),
        ]);

        // Reserva reconstruída (FIFO cross-ano), saldo total 6295 >= faltante 3061.
        $reserva = [
            $this->lote(2025, 11, 124),
            $this->lote(2025, 12, 1192),
            $this->lote(2026, 1, 2178),
            $this->lote(2026, 2, 1040),
            $this->lote(2026, 3, 1761),
        ];

        $r = $this->calculadora()->calcular($entrada, $reserva);

        // Oráculo exato em centavos (detecta qualquer regressão de arredondamento).
        $this->assertSame(389436, $r->valorFixo->emCentavos(), 'Valor Fixo');
        $this->assertSame(112914, $r->valorVariavel->emCentavos(), 'Valor Variável');
        $this->assertSame(156111, $r->credito->emCentavos(), 'Crédito');
        $this->assertSame(88396, $r->cuo->emCentavos(), 'CUO');
        $this->assertSame(570065, $r->valorFinal->emCentavos(), 'Valor Final');
    }

    /**
     * Luci Vilce Penkaç — UC 19771547 — Set/2025.
     * Fixo 3342,18 + Variável 2154,45 − CUO 695,13 = 4801,50 (sem crédito de reserva).
     *
     * tarifa 0,53; menor 6306 (3342,18/0,53); media 10371 (projetada do CSV);
     * geração líquida 10993 (compensável de setembro) >= media -> variável = (media-menor)*tarifa.
     * CUO 695,13 informado como fatura de energia (fio_b zerado para o caso isolado).
     */
    #[Test]
    public function luci_setembro_2025_total_4801_50(): void
    {
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 10993,
            'media_kwh' => 10371,
            'menor_geracao_kwh' => 6306,
            'geracao_bruta_kwh' => 10993,
            'tarifa' => 0.53,
            'fio_b' => 0.0,
            'percentual_lei' => 0.0,
            'fatura_energia' => 695.13,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2025, 9),
        ]);

        $r = $this->calculadora()->calcular($entrada, []);

        $this->assertSame(334218, $r->valorFixo->emCentavos(), 'Valor Fixo');
        $this->assertSame(215445, $r->valorVariavel->emCentavos(), 'Valor Variável');
        $this->assertSame(0, $r->credito->emCentavos(), 'Crédito deve ser zero (sem reserva)');
        $this->assertSame(69513, $r->cuo->emCentavos(), 'CUO');
        $this->assertSame(480150, $r->valorFinal->emCentavos(), 'Valor Final');
    }

    /**
     * Colina Eco Solar — UC 3085733401 — Fev/2026.
     * Geração (16740) == média (16740) -> faltante 0 -> Crédito = R$ 0,00.
     * Regressão do bug "crédito sem déficit" (sistema antigo creditava R$ 7.080).
     */
    #[Test]
    public function colina_fevereiro_2026_credito_zero_sem_deficit(): void
    {
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 16740,
            'media_kwh' => 16740,
            'menor_geracao_kwh' => 9000,
            'geracao_bruta_kwh' => 16740,
            'tarifa' => 0.51,
            'fio_b' => 0.13275,
            'percentual_lei' => 60.0,
            'fatura_energia' => 0,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2026, 2),
        ]);

        // Reserva farta (11800 kWh) que o sistema antigo despejava como crédito.
        $reserva = [
            $this->lote(2025, 8, 11800),
        ];

        $r = $this->calculadora()->calcular($entrada, $reserva);

        $this->assertSame(0.0, $r->credito->emReais(), 'Crédito deve ser 0,00 (NÃO 7080) sem déficit');
        $this->assertSame([], $r->consumosFifo, 'Nenhum lote consumido sem faltante');

        // §7 — O lote ago/2025 (vence ago/2025 + 180d = 2026-01-28) já passou do fim de fev/2026
        // e NÃO foi consumido (faltante 0) -> expira inteiro e vira receita: 11800 × 0,51 = 6018,00.
        $this->assertSame(601800, $r->receitaExpiracao->emCentavos(), 'Receita de expiração (11800 × 0,51)');
        $this->assertNotEmpty($r->expiracoes, 'Deve haver expiração do lote ago/2025');
        $this->assertCount(1, $r->expiracoes);
        $this->assertTrue($r->expiracoes[0]['origem']->ehIgualA(Competencia::de(2025, 8)));
        $this->assertEqualsWithDelta(11800.0, $r->expiracoes[0]['kwh']->valor(), 1e-9);

        // A receita de expiração é somada ao resultado (§2): Fixo+Variável+Crédito−CUO+Receita.
        $this->assertSame(
            $r->valorFixo->emCentavos()
                + $r->valorVariavel->emCentavos()
                + $r->credito->emCentavos()
                - $r->cuo->emCentavos()
                + $r->receitaExpiracao->emCentavos(),
            $r->valorFinal->emCentavos(),
            'Receita de expiração entra no valor final',
        );
    }

    /**
     * §7 — Eder, Maio/2026: os lotes nov/25..mar/26 são consumidos pelos mais antigos
     * ANTES de vencerem, então NADA sobra para expirar -> receita_expiracao = 0 e o
     * total permanece 5700,65 (golden preservado mesmo com a expiração implementada).
     */
    #[Test]
    public function eder_maio_2026_sem_expiracao_total_preservado(): void
    {
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 9850,
            'media_kwh' => 12911,
            'menor_geracao_kwh' => 7636,
            'geracao_bruta_kwh' => 9858,
            'tarifa' => 0.51,
            'fio_b' => 0.13275,
            'percentual_lei' => 60.0,
            'fatura_energia' => 98.77,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2026, 5),
        ]);

        $reserva = [
            $this->lote(2025, 11, 124),
            $this->lote(2025, 12, 1192),
            $this->lote(2026, 1, 2178),
            $this->lote(2026, 2, 1040),
            $this->lote(2026, 3, 1761),
        ];

        $r = $this->calculadora()->calcular($entrada, $reserva);

        $this->assertSame(0, $r->receitaExpiracao->emCentavos(), 'Receita de expiração deve ser 0');
        $this->assertSame([], $r->expiracoes, 'Nada expira (consumido antes de vencer)');
        $this->assertSame(570065, $r->valorFinal->emCentavos(), 'Total preservado em 5700,65');
    }

    /**
     * FIFO cross-ano: havendo reserva de ano anterior, ela é consumida ANTES da do ano corrente.
     * Lotes [2025-12: 1192kWh] e [2026-01: 2178kWh], faltante 1500 ->
     * consome 1192 de 2025-12 e 308 de 2026-01.
     */
    #[Test]
    public function fifo_consome_credito_mais_antigo_de_ano_anterior_primeiro(): void
    {
        // media - geracao = 1500 de faltante.
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 8500,
            'media_kwh' => 10000,
            'menor_geracao_kwh' => 5000,
            'geracao_bruta_kwh' => 8500,
            'tarifa' => 0.50,
            'fio_b' => 0.0,
            'percentual_lei' => 0.0,
            'fatura_energia' => 0,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2026, 5),
        ]);

        // Inserido fora de ordem de propósito: o motor deve ordenar por origem ASC.
        $reserva = [
            $this->lote(2026, 1, 2178),
            $this->lote(2025, 12, 1192),
        ];

        $r = $this->calculadora()->calcular($entrada, $reserva);

        $this->assertCount(2, $r->consumosFifo);

        $primeiro = $r->consumosFifo[0];
        $segundo = $r->consumosFifo[1];

        $this->assertTrue($primeiro['origem']->ehIgualA(Competencia::de(2025, 12)), 'Mais antigo primeiro');
        $this->assertEqualsWithDelta(1192.0, $primeiro['kwh']->valor(), 1e-9);

        $this->assertTrue($segundo['origem']->ehIgualA(Competencia::de(2026, 1)));
        $this->assertEqualsWithDelta(308.0, $segundo['kwh']->valor(), 1e-9);

        // Crédito = 1500 * 0,50 = 750,00 (faltante totalmente atendido).
        $this->assertEqualsWithDelta(750.00, $r->credito->emReais(), self::TOL);
    }

    /**
     * Crédito nunca excede o saldo da reserva.
     * Faltante 3000, reserva total 1000 -> crédito usa no máximo 1000 kWh.
     * Regressão do Problema 3b (creditadoTabela creditava (media-valor)*kwh sem checar saldo).
     */
    #[Test]
    public function credito_nunca_excede_saldo_da_reserva(): void
    {
        // media - geracao = 3000 de faltante.
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 7000,
            'media_kwh' => 10000,
            'menor_geracao_kwh' => 5000,
            'geracao_bruta_kwh' => 7000,
            'tarifa' => 0.50,
            'fio_b' => 0.0,
            'percentual_lei' => 0.0,
            'fatura_energia' => 0,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2026, 5),
        ]);

        $reserva = [
            $this->lote(2026, 1, 600),
            $this->lote(2026, 2, 400),
        ];

        $r = $this->calculadora()->calcular($entrada, $reserva);

        // Consome no máximo 1000 kWh -> crédito 1000 * 0,50 = 500,00 (NÃO 3000 * 0,50 = 1500,00).
        $this->assertEqualsWithDelta(500.00, $r->credito->emReais(), self::TOL);
        $totalConsumido = array_sum(array_map(
            static fn (array $c): float => $c['kwh']->valor(),
            $r->consumosFifo,
        ));
        $this->assertEqualsWithDelta(1000.0, $totalConsumido, 1e-9);
    }

    /**
     * Reais opera em centavos inteiros: 0,1 + 0,2 deve dar EXATAMENTE 0,30 (sem erro de float).
     */
    #[Test]
    public function reais_em_centavos_nao_acumula_erro_de_float(): void
    {
        $soma = Reais::deReais(0.1)->mais(Reais::deReais(0.2));

        $this->assertSame(30, $soma->emCentavos());
        $this->assertSame(0.30, $soma->emReais());
        $this->assertSame('R$ 0,30', $soma->formatar());
    }

    /**
     * Valor Variável nunca é negativo (piso em zero).
     * Quando menor_geracao > geracao_liquida, (geracao_liquida − menor) seria negativo;
     * o injetado deve ser R$ 0,00, não um valor negativo subtraindo do faturamento.
     */
    #[Test]
    public function valor_variavel_nunca_e_negativo(): void
    {
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 4000,   // abaixo do menor_geracao
            'media_kwh' => 10000,
            'menor_geracao_kwh' => 5000,
            'geracao_bruta_kwh' => 4000,
            'tarifa' => 0.50,
            'fio_b' => 0.0,
            'percentual_lei' => 0.0,
            'fatura_energia' => 0,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2026, 5),
        ]);

        $r = $this->calculadora()->calcular($entrada, []);

        $this->assertSame(0, $r->valorVariavel->emCentavos(), 'Variável não pode ser negativo');
        $this->assertFalse($r->valorVariavel->ehNegativo());
    }

    /**
     * deArray exige as chaves obrigatórias — um consumo/geração ausente não pode
     * virar 0 silenciosamente (mascararia o bug "consumo=0 congela", §9).
     */
    #[Test]
    public function entrada_exige_chaves_obrigatorias(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EntradaCalculoMes::deArray([
            // 'geracao_liquida_kwh' ausente de propósito
            'media_kwh' => 10000,
            'menor_geracao_kwh' => 5000,
            'geracao_bruta_kwh' => 4000,
            'tarifa' => 0.50,
            'competencia' => Competencia::de(2026, 5),
        ]);
    }
}
