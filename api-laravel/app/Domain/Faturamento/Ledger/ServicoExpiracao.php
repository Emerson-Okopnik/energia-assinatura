<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Ledger;

use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Reais;
use App\Domain\Faturamento\ValueObject\Tarifa;

/**
 * Serviço de expiração de crédito (REGRAS_DE_CALCULO.md §7).
 *
 * ORDEM CRÍTICA: a expiração é avaliada SEMPRE depois do consumo FIFO do mês.
 * Por isso este serviço recebe os lotes JÁ com o saldo reduzido pelo consumo
 * (o que foi resgatado não pode expirar). Só expira o que SOBROU sem uso cujo
 * vencimento é anterior ao fim do mês do evento.
 *
 * Cada lote expirado vira RECEITA em dinheiro (saldo_restante × tarifa), no mês
 * do vencimento, e NÃO é contado em dobro: como o saldo já está reduzido pelo
 * consumo, não há sobreposição entre o termo Crédito (§6) e a receita de
 * expiração (§7).
 *
 * Função pura: não muta os lotes de entrada e não tem efeito colateral.
 */
final class ServicoExpiracao
{
    /**
     * @param LoteReserva[] $lotesAposConsumo Lotes com saldo já reduzido pelo consumo FIFO.
     * @param Competencia   $evento           Competência do evento (mês cujo fim define a expiração).
     * @param Tarifa        $tarifa           Tarifa vigente para converter kWh expirado em receita.
     *
     * @return array{
     *     expirados: array<int, array{origem: Competencia, kwh: \App\Domain\Faturamento\ValueObject\Kwh}>,
     *     receita: Reais
     * }
     */
    public function aplicar(array $lotesAposConsumo, Competencia $evento, Tarifa $tarifa): array
    {
        $fimDoMesEvento = $this->fimDoMes($evento);

        $expirados = [];
        $receita = Reais::zero();

        foreach ($lotesAposConsumo as $lote) {
            // Só expira o que sobrou (saldo positivo) e cujo vencimento já passou
            // relativo ao fim do mês do evento.
            if (!$lote->saldoKwh->ehPositivo()) {
                continue;
            }

            if ($lote->vencimento >= $fimDoMesEvento) {
                continue;
            }

            $expirados[] = [
                'origem' => $lote->competenciaOrigem,
                'kwh' => $lote->saldoKwh,
            ];

            $receita = $receita->mais($lote->saldoKwh->vezesTarifa($tarifa));
        }

        return [
            'expirados' => $expirados,
            'receita' => $receita,
        ];
    }

    /**
     * Primeiro instante do mês seguinte ao do evento — a fronteira "fim do mês".
     * Um vencimento estritamente anterior a esse marco já expirou dentro do mês.
     */
    private function fimDoMes(Competencia $evento): \DateTimeImmutable
    {
        return (new \DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', $evento->ano, $evento->mes)
        ))->modify('first day of next month');
    }
}
