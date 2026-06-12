<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\ReconstrutorLedger;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReconstrutorLedgerTest extends TestCase
{
    /** Mês cru padrão; sobrescreve só o necessário por caso. */
    private function mes(int $ano, int $mes, float $bruta, float $consumo = 0.0): array
    {
        return [
            'ano' => $ano, 'mes' => $mes,
            'geracao_bruta_kwh' => $bruta, 'consumo_kwh' => $consumo, 'rede' => 'Trifásico',
            'media_kwh' => 12911, 'menor_geracao_kwh' => 7636, 'tarifa' => 0.51,
            'fio_b' => 0.13275, 'percentual_lei' => 60.0, 'fatura_energia' => 98.77, 'adicional_cuo' => 0.0,
        ];
    }

    #[Test]
    public function excedente_vira_lote_consumido_no_deficit_seguinte(): void
    {
        // nov guarda excedente; dez tem déficit e consome o lote de nov.
        $meses = [
            $this->mes(2025, 11, 14911), // líquida 14911 (consumo 0) - média 12911 = guardou 2000
            $this->mes(2025, 12, 10911), // faltante 2000 -> consome 2000 de nov
        ];

        $r = (new ReconstrutorLedger())->reconstruir($meses);

        $nov = $r['meses'][0];
        $dez = $r['meses'][1];
        $this->assertSame(2000.0, $nov['resultado']->guardadoKwh->valor());
        $this->assertCount(1, $dez['resultado']->consumosFifo);
        $this->assertTrue($dez['resultado']->consumosFifo[0]['origem']->ehIgualA(Competencia::de(2025, 11)));
        $this->assertSame(2000.0, $dez['resultado']->consumosFifo[0]['kwh']->valor());
        // reserva esgotada após o consumo
        $this->assertSame(0.0, $dez['saldo_final_kwh']);
    }

    #[Test]
    public function saldo_inicial_e_consumido_primeiro_via_fifo(): void
    {
        // lote inicial migrado (origem antiga) deve ser consumido antes do excedente do próprio ano.
        $inicial = new LoteReserva(
            Competencia::de(2024, 1),
            Kwh::de(1000),
            Competencia::de(2024, 1)->vencimentoEmDias(180),
        );
        // único mês com déficit de 500 -> consome 500 do lote inicial (mais antigo).
        $meses = [$this->mes(2026, 5, 12411)]; // faltante 500

        $r = (new ReconstrutorLedger())->reconstruir($meses, [$inicial]);

        $mai = $r['meses'][0];
        $this->assertCount(1, $mai['resultado']->consumosFifo);
        $this->assertTrue($mai['resultado']->consumosFifo[0]['origem']->ehIgualA(Competencia::de(2024, 1)));
        $this->assertSame(500.0, $mai['resultado']->consumosFifo[0]['kwh']->valor());
    }

    #[Test]
    public function deriva_geracao_liquida_com_desconto_de_rede(): void
    {
        // bruta 9858, consumo 134, trifásico -> líquida 9824 entra no cálculo.
        $r = (new ReconstrutorLedger())->reconstruir([$this->mes(2026, 5, 9858, 134)]);
        $this->assertSame(9824.0, $r['meses'][0]['entrada']->geracaoLiquidaKwh->valor());
        $this->assertSame(9858.0, $r['meses'][0]['entrada']->geracaoBrutaKwh->valor());
    }

    #[Test]
    public function ordena_cronologicamente_meses_fora_de_ordem(): void
    {
        $r = (new ReconstrutorLedger())->reconstruir([
            $this->mes(2026, 1, 13000),
            $this->mes(2025, 12, 13000),
        ]);
        $this->assertTrue($r['meses'][0]['entrada']->competencia->ehIgualA(Competencia::de(2025, 12)));
        $this->assertTrue($r['meses'][1]['entrada']->competencia->ehIgualA(Competencia::de(2026, 1)));
    }
}
