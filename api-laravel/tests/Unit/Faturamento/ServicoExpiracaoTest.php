<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Calculo\CalculadoraGeracaoLinear;
use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\ServicoExpiracao;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Tarifa;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Testes da expiração de crédito (REGRAS_DE_CALCULO.md §7).
 *
 * ORDEM CRÍTICA: o consumo FIFO acontece ANTES; só expira o saldo que SOBROU
 * cujo vencimento (origem + 180 dias) é anterior ao fim do mês do evento.
 * O crédito expirado vira receita UMA vez (não soma ao termo Crédito).
 */
class ServicoExpiracaoTest extends TestCase
{
    private const TARIFA = 0.51;

    private function lote(int $ano, int $mes, float $kwh): LoteReserva
    {
        $origem = Competencia::de($ano, $mes);

        return new LoteReserva($origem, Kwh::de($kwh), $origem->vencimentoEmDias(180));
    }

    /**
     * Lote antigo (>180d) NÃO consumido expira e vira receita (kwh × tarifa).
     */
    #[Test]
    public function lote_antigo_nao_consumido_expira_e_vira_receita(): void
    {
        $servico = new ServicoExpiracao();

        // ago/2025 vence 2026-01-28 < fim de fev/2026 (2026-03-01) -> expira.
        $resultado = $servico->aplicar(
            [$this->lote(2025, 8, 11800)],
            Competencia::de(2026, 2),
            Tarifa::de(self::TARIFA),
        );

        $this->assertCount(1, $resultado['expirados']);
        $this->assertTrue($resultado['expirados'][0]['origem']->ehIgualA(Competencia::de(2025, 8)));
        $this->assertEqualsWithDelta(11800.0, $resultado['expirados'][0]['kwh']->valor(), 1e-9);
        // 11800 × 0,51 = 6018,00.
        $this->assertSame(601800, $resultado['receita']->emCentavos());
    }

    /**
     * Lote dentro do prazo (<180d) NÃO expira, mesmo sem ser consumido.
     */
    #[Test]
    public function lote_dentro_do_prazo_nao_expira(): void
    {
        $servico = new ServicoExpiracao();

        // mar/2026 vence 2026-08-28 >= fim de mai/2026 (2026-06-01) -> NÃO expira.
        $resultado = $servico->aplicar(
            [$this->lote(2026, 3, 1761)],
            Competencia::de(2026, 5),
            Tarifa::de(self::TARIFA),
        );

        $this->assertSame([], $resultado['expirados']);
        $this->assertSame(0, $resultado['receita']->emCentavos());
    }

    /**
     * Consumo ANTES da expiração: um lote totalmente consumido (saldo 0) NÃO expira;
     * o lote antigo que sobrou (não consumido e vencido) expira. Não há dupla contagem.
     */
    #[Test]
    public function lote_consumido_nao_expira_apenas_o_que_sobrou(): void
    {
        $servico = new ServicoExpiracao();

        // Ambos vencidos relativos ao fim de mai/2026, mas o de nov/2025 já foi
        // consumido (saldo reduzido a 0) -> só dez/2025 (saldo restante) expira.
        $resultado = $servico->aplicar(
            [
                $this->lote(2025, 11, 0.0),   // consumido: saldo zerado
                $this->lote(2025, 12, 500.0), // sobrou e venceu (2026-05-30 < 2026-06-01)
            ],
            Competencia::de(2026, 5),
            Tarifa::de(self::TARIFA),
        );

        $this->assertCount(1, $resultado['expirados']);
        $this->assertTrue($resultado['expirados'][0]['origem']->ehIgualA(Competencia::de(2025, 12)));
        $this->assertEqualsWithDelta(500.0, $resultado['expirados'][0]['kwh']->valor(), 1e-9);
        // 500 × 0,51 = 255,00.
        $this->assertSame(25500, $resultado['receita']->emCentavos());
    }

    /**
     * Integração com a calculadora: o consumo acontece ANTES da expiração.
     * Faltante consome o lote antigo vencido (FIFO), então ele NÃO expira; só o
     * remanescente não consumido (ainda vencido) vira receita.
     */
    #[Test]
    public function consumo_fifo_protege_lote_da_expiracao_na_calculadora(): void
    {
        // faltante = media - geracao = 800.
        $entrada = EntradaCalculoMes::deArray([
            'geracao_liquida_kwh' => 9200,
            'media_kwh' => 10000,
            'menor_geracao_kwh' => 5000,
            'geracao_bruta_kwh' => 9200,
            'tarifa' => self::TARIFA,
            'fio_b' => 0.0,
            'percentual_lei' => 0.0,
            'fatura_energia' => 0,
            'adicional_cuo' => 0,
            'competencia' => Competencia::de(2026, 5),
        ]);

        // dez/2025: 1000 kWh, vence 2026-05-30 < fim de mai/2026. Consome 800 (FIFO).
        // Sobram 200 vencidos -> expiram: 200 × 0,51 = 102,00.
        $reserva = [$this->lote(2025, 12, 1000)];

        $r = (new CalculadoraGeracaoLinear())->calcular($entrada, $reserva);

        // Crédito = 800 × 0,51 = 408,00 (consumo do mês).
        $this->assertSame(40800, $r->credito->emCentavos(), 'Crédito = consumo FIFO');
        // Só o que sobrou expira (não há dupla contagem do que foi consumido).
        $this->assertCount(1, $r->expiracoes);
        $this->assertEqualsWithDelta(200.0, $r->expiracoes[0]['kwh']->valor(), 1e-9);
        $this->assertSame(10200, $r->receitaExpiracao->emCentavos(), 'Receita = saldo sobrado × tarifa');
    }

    /**
     * Função pura: não muta os lotes recebidos.
     */
    #[Test]
    public function funcao_pura_nao_muta_lotes(): void
    {
        $servico = new ServicoExpiracao();
        $lotes = [$this->lote(2025, 8, 11800)];

        $servico->aplicar($lotes, Competencia::de(2026, 2), Tarifa::de(self::TARIFA));

        $this->assertEqualsWithDelta(11800.0, $lotes[0]->saldoKwh->valor(), 1e-9);
    }
}
