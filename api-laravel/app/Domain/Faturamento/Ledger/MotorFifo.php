<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Ledger;

use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;

/**
 * Motor de consumo FIFO cross-ano da reserva (REGRAS_DE_CALCULO.md §6).
 *
 * Consome SEMPRE o lote mais antigo primeiro, atravessando anos (o saldo de
 * dezembro/2025 é consumido antes do de janeiro/2026), pois o mais antigo é o
 * mais próximo do vencimento. Nunca consome mais que o saldo de cada lote nem
 * mais que o faltante. Função pura: não muta os lotes de entrada.
 *
 * Ordem (§7): o consumo FIFO acontece PRIMEIRO; a expiração do que sobrou é
 * aplicada DEPOIS pelo {@see ServicoExpiracao} (só expira o crédito não usado).
 * O parâmetro `$evento` é mantido para auditoria do mês do consumo.
 */
final class MotorFifo
{
    /**
     * @param LoteReserva[] $lotes      Lotes de reserva disponíveis (qualquer ordem).
     * @param Kwh           $faltante   Energia a compensar (déficit do mês).
     * @param Competencia   $evento     Competência em que o consumo ocorre (auditoria).
     *
     * @return array{
     *     consumidoKwh: Kwh,
     *     consumos: array<int, array{origem: Competencia, evento: Competencia, kwh: Kwh}>,
     *     naoAtendidoKwh: Kwh
     * }
     */
    public function consumir(array $lotes, Kwh $faltante, Competencia $evento): array
    {
        $consumos = [];
        $restante = max($faltante->valor(), 0.0);
        $consumidoTotal = 0.0;

        foreach ($this->ordenarPorOrigemAsc($lotes) as $lote) {
            if ($restante <= 0.0) {
                break;
            }

            $disponivel = $lote->saldoKwh->valor();
            if ($disponivel <= 0.0) {
                continue;
            }

            $consumir = min($disponivel, $restante);

            $consumos[] = [
                'origem' => $lote->competenciaOrigem,
                'evento' => $evento,
                'kwh' => Kwh::de($consumir),
            ];

            $consumidoTotal += $consumir;
            $restante -= $consumir;
        }

        return [
            'consumidoKwh' => Kwh::de($consumidoTotal),
            'consumos' => $consumos,
            'naoAtendidoKwh' => Kwh::de(max($restante, 0.0)),
        ];
    }

    /**
     * @param LoteReserva[] $lotes
     *
     * @return LoteReserva[] cópia ordenada por competência de origem ASC
     */
    private function ordenarPorOrigemAsc(array $lotes): array
    {
        $ordenados = array_values($lotes);

        usort(
            $ordenados,
            static fn (LoteReserva $a, LoteReserva $b): int
                => $a->competenciaOrigem->comparar($b->competenciaOrigem),
        );

        return $ordenados;
    }
}
