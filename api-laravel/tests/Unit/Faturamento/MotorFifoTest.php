<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\MotorFifo;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Testes do MotorFifo (REGRAS_DE_CALCULO.md §6) — consumo FIFO cross-ano.
 */
class MotorFifoTest extends TestCase
{
    private function lote(int $ano, int $mes, float $kwh): LoteReserva
    {
        $origem = Competencia::de($ano, $mes);

        return new LoteReserva($origem, Kwh::de($kwh), $origem->vencimentoEmDias(180));
    }

    #[Test]
    public function consome_mais_antigo_primeiro_atravessando_anos(): void
    {
        $motor = new MotorFifo();

        $resultado = $motor->consumir(
            [$this->lote(2026, 1, 2178), $this->lote(2025, 12, 1192)],
            Kwh::de(1500),
            Competencia::de(2026, 5),
        );

        $this->assertEqualsWithDelta(1500.0, $resultado['consumidoKwh']->valor(), 1e-9);
        $this->assertEqualsWithDelta(0.0, $resultado['naoAtendidoKwh']->valor(), 1e-9);
        $this->assertCount(2, $resultado['consumos']);

        $this->assertTrue($resultado['consumos'][0]['origem']->ehIgualA(Competencia::de(2025, 12)));
        $this->assertEqualsWithDelta(1192.0, $resultado['consumos'][0]['kwh']->valor(), 1e-9);
        $this->assertTrue($resultado['consumos'][1]['origem']->ehIgualA(Competencia::de(2026, 1)));
        $this->assertEqualsWithDelta(308.0, $resultado['consumos'][1]['kwh']->valor(), 1e-9);
    }

    #[Test]
    public function nunca_consome_mais_que_o_saldo_total(): void
    {
        $motor = new MotorFifo();

        $resultado = $motor->consumir(
            [$this->lote(2026, 1, 600), $this->lote(2026, 2, 400)],
            Kwh::de(3000),
            Competencia::de(2026, 5),
        );

        $this->assertEqualsWithDelta(1000.0, $resultado['consumidoKwh']->valor(), 1e-9);
        $this->assertEqualsWithDelta(2000.0, $resultado['naoAtendidoKwh']->valor(), 1e-9);
    }

    #[Test]
    public function faltante_zero_nao_consome_nada(): void
    {
        $motor = new MotorFifo();

        $resultado = $motor->consumir(
            [$this->lote(2025, 8, 11800)],
            Kwh::zero(),
            Competencia::de(2026, 2),
        );

        $this->assertEqualsWithDelta(0.0, $resultado['consumidoKwh']->valor(), 1e-9);
        $this->assertSame([], $resultado['consumos']);
    }

    #[Test]
    public function nunca_consome_mais_que_o_saldo_de_cada_lote(): void
    {
        $motor = new MotorFifo();

        // Faltante cabe inteiro no primeiro lote -> segundo não é tocado.
        $resultado = $motor->consumir(
            [$this->lote(2025, 12, 1000), $this->lote(2026, 1, 1000)],
            Kwh::de(700),
            Competencia::de(2026, 3),
        );

        $this->assertCount(1, $resultado['consumos']);
        $this->assertEqualsWithDelta(700.0, $resultado['consumos'][0]['kwh']->valor(), 1e-9);
        $this->assertTrue($resultado['consumos'][0]['origem']->ehIgualA(Competencia::de(2025, 12)));
    }

    #[Test]
    public function funcao_pura_nao_muta_lotes_de_entrada(): void
    {
        $motor = new MotorFifo();
        $lotes = [$this->lote(2025, 12, 1192), $this->lote(2026, 1, 2178)];

        $motor->consumir($lotes, Kwh::de(1500), Competencia::de(2026, 5));

        // Saldos originais permanecem intactos (objetos imutáveis, sem efeito colateral).
        $this->assertEqualsWithDelta(1192.0, $lotes[0]->saldoKwh->valor(), 1e-9);
        $this->assertEqualsWithDelta(2178.0, $lotes[1]->saldoKwh->valor(), 1e-9);
    }
}
