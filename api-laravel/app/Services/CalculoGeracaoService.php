<?php

namespace App\Services;

use App\Models\{
    Usina,
    CreditosDistribuidosUsina,
    CreditosDistribuidos,
    ValorAcumuladoReserva,
    FaturamentoUsina,
    DadosGeracaoReal,
    DadosGeracaoRealUsina,
    HistoricoEstorno,
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CalculoGeracaoService
{
    private array $meses = [
        1 => 'janeiro',
        2 => 'fevereiro',
        3 => 'marco',
        4 => 'abril',
        5 => 'maio',
        6 => 'junho',
        7 => 'julho',
        8 => 'agosto',
        9 => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    ];

    public function process(Usina $usina, int $ano, int $mes, array $payload, int $userId, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($usina, $ano, $mes, $payload, $userId, $idempotencyKey) {
            $mesNome = $this->meses[$mes] ?? null;
            if (!$mesNome) {
                throw new \InvalidArgumentException('Mês inválido');
            }

            $vinculo = CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
                ->where('ano', $ano)
                ->first();

            $dgrVinculo = DadosGeracaoRealUsina::where('usi_id', $usina->usi_id)
                ->where('ano', $ano)
                ->first();

            if (!$vinculo || !$dgrVinculo) {
                [$vinculo, $dgrVinculo] = $this->criarPacoteAnual($usina, $ano);
            }

            $credito    = CreditosDistribuidos::findOrFail($vinculo->cd_id);
            $reserva    = ValorAcumuladoReserva::findOrFail($vinculo->var_id);
            $faturamento = FaturamentoUsina::findOrFail($vinculo->fa_id);
            $geracao    = DadosGeracaoReal::findOrFail($dgrVinculo->dgr_id);

            $vinculoAnoAnterior = CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
                ->where('ano', $ano - 1)
                ->first();

            $reservaAnoAnterior = $vinculoAnoAnterior
                ? ValorAcumuladoReserva::find($vinculoAnoAnterior->var_id)
                : null;

            // Snapshot do estado atual antes de qualquer modificação
            HistoricoEstorno::create([
                'usi_id'                    => $usina->usi_id,
                'ano'                       => $ano,
                'mes'                       => $mes,
                'mes_nome'                  => $mesNome,
                'user_id'                   => $userId,
                'idempotency_key'           => $idempotencyKey,
                'snapshot_reserva_atual'    => $reserva->attributesToArray(),
                'snapshot_reserva_anterior' => $reservaAnoAnterior?->attributesToArray(),
                'snapshot_credito_mes'      => (float) ($credito->$mesNome ?? 0),
                'snapshot_faturamento_mes'  => (float) ($faturamento->$mesNome ?? 0),
                'snapshot_geracao_mes'      => (float) ($geracao->$mesNome ?? 0),
            ]);

            $tarifa     = (float) $payload['tarifa_kwh'];
            $geracaoMes = (float) $payload['mesGeracao_kwh'];
            $media         = (float) $payload['mediaGeracao_kwh'];
            $adicionalCuo  = (float) ($payload['adicional_cuo'] ?? 0);
            $valorPago     = (float) $payload['valorPago_mes'] + $adicionalCuo;

            $referencia       = Carbon::create($ano, $mes, 1)->endOfMonth();
            $reservasExpiradas = [];

            $expiracaoAtual = $this->expirarReservas($reserva, $ano, $tarifa, $referencia, $reservasExpiradas);
            $creditoExpirado = $expiracaoAtual['expirado_valor'];

            if ($reservaAnoAnterior) {
                $expiracaoAnterior = $this->expirarReservas(
                    $reservaAnoAnterior,
                    $ano - 1,
                    $tarifa,
                    $referencia,
                    $reservasExpiradas
                );

                $creditoExpirado += $expiracaoAnterior['expirado_valor'];
                $reserva->total = max(0.0, ($reserva->total ?? 0) - $expiracaoAnterior['expirado_kwh']);
                $reservaAnoAnterior->save();
            }

            $valorPago += $creditoExpirado;

            $reservaAnterior   = max(0.0, (float) ($reserva->total ?? 0));
            $valorGuardado     = 0.0;
            $energiaCompensada = 0.0;
            $deficit           = 0.0;

            if ($geracaoMes >= $media) {
                $valorGuardado = $geracaoMes - $media;
            } elseif ($reservaAnterior > 0) {
                $faltante          = $media - $geracaoMes;
                $energiaCompensada = min($faltante, $reservaAnterior);
                $deficit           = $faltante - $energiaCompensada;
                if ($deficit > 0) {
                    $valorPago += $deficit * $tarifa;
                }
            }

            $energiaParaDescontar = $energiaCompensada;
            if ($energiaParaDescontar > 0) {
                foreach ($this->meses as $nome) {
                    $saldo = (float) ($reserva->$nome ?? 0);
                    if ($saldo <= 0) {
                        continue;
                    }
                    $retirar = min($saldo, $energiaParaDescontar);
                    $reserva->$nome = max(0.0, $saldo - $retirar);
                    $energiaParaDescontar -= $retirar;
                    if ($energiaParaDescontar <= 0) {
                        break;
                    }
                }
            }

            $creditoGerado = ($energiaCompensada * $tarifa) + $creditoExpirado;

            $saldoMesAtual  = max(0.0, (float) ($reserva->$mesNome ?? 0));
            $reserva->$mesNome = $saldoMesAtual + $valorGuardado;
            $reserva->total    = max(0.0, ($reserva->total ?? 0) + $valorGuardado - $energiaCompensada);
            $reserva->save();

            $credito->$mesNome = $creditoGerado;
            $credito->save();

            $faturamento->$mesNome = $valorPago;
            $faturamento->save();

            $geracao->$mesNome = $geracaoMes;
            $geracao->save();

            $co2Evitado = $geracaoMes * 0.4;
            $arvores    = $co2Evitado / 20;

            return [
                'ano'                      => $ano,
                'mes'                      => $mes,
                'credito_gerado_reais'     => round($creditoGerado, 2),
                'valor_guardado_kwh'       => round($valorGuardado, 2),
                'reserva_total_atual_kwh'  => round($reserva->total, 2),
                'faturamento_mes_reais'    => round($valorPago, 2),
                'geracao_real_kwh'         => round($geracaoMes, 2),
                'co2_evitado_kg'           => round($co2Evitado, 2),
                'arvores_equivalentes'     => round($arvores, 2),
                'adicional_cuo_aplicado'   => round($adicionalCuo, 2),
                'reservas_expiradas'       => $reservasExpiradas,
            ];
        });
    }

    private function criarPacoteAnual(Usina $usina, int $ano): array
    {
        $cd  = CreditosDistribuidos::create();
        $var = ValorAcumuladoReserva::create(['total' => 0]);
        $fa  = FaturamentoUsina::create();
        $dgr = DadosGeracaoReal::create();

        $vinculo = CreditosDistribuidosUsina::create([
            'usi_id' => $usina->usi_id,
            'cli_id' => $usina->cli_id,
            'cd_id'  => $cd->cd_id,
            'fa_id'  => $fa->fa_id,
            'var_id' => $var->var_id,
            'ano'    => $ano,
        ]);

        $dgrVinculo = DadosGeracaoRealUsina::create([
            'usi_id' => $usina->usi_id,
            'cli_id' => $usina->cli_id,
            'dgr_id' => $dgr->dgr_id,
            'ano'    => $ano,
        ]);

        $anterior = CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
            ->where('ano', $ano - 1)
            ->first();

        if ($anterior) {
            $varAnterior = ValorAcumuladoReserva::find($anterior->var_id);
            if ($varAnterior) {
                $var->total = $varAnterior->total;
                $var->save();
            }
        }

        return [$vinculo, $dgrVinculo];
    }

    private function expirarReservas(
        ValorAcumuladoReserva $reserva,
        int $ano,
        float $tarifa,
        Carbon $referencia,
        array &$reservasExpiradas
    ): array {
        $expiradoKwh = 0.0;

        foreach ($this->meses as $num => $nome) {
            $valor = (float) ($reserva->$nome ?? 0);
            if ($valor <= 0) {
                continue;
            }

            $dataMes = Carbon::create($ano, $num, 1)->endOfMonth();
            if ($dataMes->lessThan($referencia) && $dataMes->diffInDays($referencia) > 180) {
                $reserva->$nome = 0;
                $expiradoKwh   += $valor;
                $reservasExpiradas[] = [
                    'ano'                        => $ano,
                    'mes'                        => $num,
                    'expirado_kwh'               => $valor,
                    'creditado_na_fatura_reais'  => $valor * $tarifa,
                ];
            }
        }

        if ($expiradoKwh > 0) {
            $reserva->total = max(0, ($reserva->total ?? 0) - $expiradoKwh);
        }

        return [
            'expirado_kwh'   => $expiradoKwh,
            'expirado_valor' => $expiradoKwh * $tarifa,
        ];
    }
}
