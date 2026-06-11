<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Ledger;

use App\Domain\Faturamento\Calculo\CalculadoraGeracaoLinear;
use App\Domain\Faturamento\Calculo\DescontoRede;
use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\DTO\ResultadoCalculoMes;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;

/**
 * Reconstrói o ledger de reserva de UMA usina percorrendo a timeline mês a mês,
 * reusando o motor de cálculo único (CalculadoraGeracaoLinear). Não reimplementa
 * FIFO nem expiração: apenas espelha na reserva carregada os deltas que o
 * resultado reporta (consumos, expirações, guardado).
 *
 * Saldo inicial migrado (REGRAS_DE_CALCULO.md §12) entra como lotes iniciais
 * (a competência de origem deve ser anterior à timeline para ser consumida 1º).
 */
final class ReconstrutorLedger
{
    private const PRAZO_EXPIRACAO_DIAS = 180;

    public function __construct(
        private readonly CalculadoraGeracaoLinear $calculadora = new CalculadoraGeracaoLinear(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $mesesRaw Meses crus (ordem qualquer). Chaves:
     *        ano, mes, geracao_bruta_kwh, consumo_kwh, rede, media_kwh, menor_geracao_kwh,
     *        tarifa, fio_b, percentual_lei, fatura_energia, adicional_cuo.
     * @param LoteReserva[] $lotesIniciais Saldo inicial migrado (§12), opcional.
     *
     * @return array{
     *     meses: array<int, array{
     *         ano:int, mes:int,
     *         entrada: EntradaCalculoMes,
     *         resultado: ResultadoCalculoMes,
     *         reserva_antes_kwh: float,
     *         saldo_final_kwh: float
     *     }>,
     *     eventos: array<int, array{tipo:string, origem:string, evento:string, kwh:float}>
     * }
     */
    public function reconstruir(array $mesesRaw, array $lotesIniciais = []): array
    {
        usort(
            $mesesRaw,
            static fn (array $a, array $b): int
                => [$a['ano'], $a['mes']] <=> [$b['ano'], $b['mes']],
        );

        $reserva = array_values($lotesIniciais);
        $meses = [];
        $eventos = [];

        foreach ($lotesIniciais as $lote) {
            $eventos[] = [
                'tipo' => 'SALDO_INICIAL',
                'origem' => (string) $lote->competenciaOrigem,
                'evento' => (string) $lote->competenciaOrigem,
                'kwh' => $lote->saldoKwh->valor(),
            ];
        }

        foreach ($mesesRaw as $m) {
            $competencia = Competencia::de((int) $m['ano'], (int) $m['mes']);
            $bruta = Kwh::de((float) $m['geracao_bruta_kwh']);
            $liquida = DescontoRede::liquida($bruta, Kwh::de((float) ($m['consumo_kwh'] ?? 0)), $m['rede'] ?? null);

            $entrada = EntradaCalculoMes::deArray([
                'geracao_liquida_kwh' => $liquida->valor(),
                'media_kwh' => (float) $m['media_kwh'],
                'menor_geracao_kwh' => (float) $m['menor_geracao_kwh'],
                'geracao_bruta_kwh' => $bruta->valor(),
                'tarifa' => (float) $m['tarifa'],
                'fio_b' => (float) ($m['fio_b'] ?? 0),
                'percentual_lei' => (float) ($m['percentual_lei'] ?? 0),
                'fatura_energia' => (float) ($m['fatura_energia'] ?? 0),
                'adicional_cuo' => (float) ($m['adicional_cuo'] ?? 0),
                'competencia' => $competencia,
            ]);

            $reservaAntes = $this->saldoTotal($reserva);
            $resultado = $this->calculadora->calcular($entrada, $reserva);

            foreach ($resultado->consumosFifo as $c) {
                $eventos[] = [
                    'tipo' => 'CONSUMO', 'origem' => (string) $c['origem'],
                    'evento' => (string) $competencia, 'kwh' => $c['kwh']->valor(),
                ];
            }
            foreach ($resultado->expiracoes as $e) {
                $eventos[] = [
                    'tipo' => 'EXPIRACAO', 'origem' => (string) $e['origem'],
                    'evento' => (string) $competencia, 'kwh' => $e['kwh']->valor(),
                ];
            }
            if ($resultado->guardadoKwh->ehPositivo()) {
                $eventos[] = [
                    'tipo' => 'CREDITO', 'origem' => (string) $competencia,
                    'evento' => (string) $competencia, 'kwh' => $resultado->guardadoKwh->valor(),
                ];
            }

            $reserva = $this->aplicarDeltas($reserva, $resultado, $competencia);

            $meses[] = [
                'ano' => $competencia->ano, 'mes' => $competencia->mes,
                'entrada' => $entrada, 'resultado' => $resultado,
                'reserva_antes_kwh' => $reservaAntes,
                'saldo_final_kwh' => $this->saldoTotal($reserva),
            ];
        }

        return ['meses' => $meses, 'eventos' => $eventos];
    }

    /**
     * Espelha na reserva os deltas reportados pelo resultado: subtrai consumos por
     * origem, zera origens expiradas e adiciona o guardado do mês como novo lote.
     *
     * @param LoteReserva[] $reserva
     *
     * @return LoteReserva[]
     */
    private function aplicarDeltas(
        array $reserva,
        ResultadoCalculoMes $resultado,
        Competencia $competencia,
    ): array {
        $consumidoPorOrigem = [];
        foreach ($resultado->consumosFifo as $c) {
            $k = (string) $c['origem'];
            $consumidoPorOrigem[$k] = ($consumidoPorOrigem[$k] ?? 0.0) + $c['kwh']->valor();
        }
        $expiradoOrigens = [];
        foreach ($resultado->expiracoes as $e) {
            $expiradoOrigens[(string) $e['origem']] = true;
        }

        $nova = [];
        foreach ($reserva as $lote) {
            $k = (string) $lote->competenciaOrigem;
            $saldo = $lote->saldoKwh->valor() - ($consumidoPorOrigem[$k] ?? 0.0);
            if (isset($expiradoOrigens[$k])) {
                $saldo = 0.0;
            }
            if ($saldo > 0.0) {
                $nova[] = new LoteReserva($lote->competenciaOrigem, Kwh::de($saldo), $lote->vencimento);
            }
        }

        if ($resultado->guardadoKwh->ehPositivo()) {
            $nova[] = new LoteReserva(
                $competencia,
                $resultado->guardadoKwh,
                $competencia->vencimentoEmDias(self::PRAZO_EXPIRACAO_DIAS),
            );
        }

        return $nova;
    }

    /** @param LoteReserva[] $reserva */
    private function saldoTotal(array $reserva): float
    {
        $total = 0.0;
        foreach ($reserva as $lote) {
            $total += $lote->saldoKwh->valor();
        }

        return $total;
    }
}
