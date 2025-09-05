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

    public function process(Usina $usina, int $ano, int $mes, array $payload): array
    {
        return DB::transaction(function () use ($usina, $ano, $mes, $payload) {
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
                [$vinculo, $dgrVinculo] = $this->criarPacoteAnual($usina, $ano, $mesNome, $payload['mesGeracao_kwh']);
            }

            $credito = CreditosDistribuidos::findOrFail($vinculo->cd_id);
            $reserva = ValorAcumuladoReserva::findOrFail($vinculo->var_id);
            $faturamento = FaturamentoUsina::findOrFail($vinculo->fa_id);
            $geracao = DadosGeracaoReal::findOrFail($dgrVinculo->dgr_id);

            $tarifa = (float) $payload['tarifa_kwh'];
            $geracaoMes = (float) $payload['mesGeracao_kwh'];
            $media = (float) $payload['mediaGeracao_kwh'];
            $reservaAnterior = (float) $payload['reservaTotalAnterior_kwh'];
            $valorPago = (float) $payload['valorPago_mes'];

            $reservasExpiradas = [];
            $extraCredito = 0.0;
            $referencia = Carbon::create($ano, $mes, 1)->endOfMonth();
            foreach ($this->meses as $num => $nome) {
                $valor = (float) ($reserva->$nome ?? 0);
                if ($valor <= 0) {
                    continue;
                }
                $dataMes = Carbon::create($ano, $num, 1)->endOfMonth();
                if ($referencia->diffInDays($dataMes, false) > 160) {
                    $reserva->$nome = 0;
                    $reserva->total = max(0, ($reserva->total ?? 0) - $valor);
                    $extraCredito += $valor * $tarifa;
                    $reservasExpiradas[] = [
                        'ano' => $ano,
                        'mes' => $num,
                        'expirado_kwh' => $valor,
                        'convertido_em_credito_reais' => $valor * $tarifa,
                    ];
                }
            }

            $valorPago -= $extraCredito;

            $valorGuardado = 0.0;
            $energiaCompensada = 0.0;
            if ($geracaoMes >= $media) {
                $valorGuardado = $geracaoMes - $media;
            } elseif ($reservaAnterior > 0) {
                $faltante = $media - $geracaoMes;
                $energiaCompensada = min($faltante, $reservaAnterior);
            }

            $creditoGerado = ($energiaCompensada * $tarifa) + $extraCredito;

            $reserva->$mesNome = $valorGuardado;
            $reserva->total = ($reserva->total ?? 0) + $valorGuardado - $energiaCompensada;
            $reserva->save();

            $credito->$mesNome = $creditoGerado;
            $credito->save();

            $faturamento->$mesNome = $valorPago;
            $faturamento->save();

            $geracao->$mesNome = $geracaoMes;
            $geracao->save();

            $co2Evitado = $geracaoMes * 0.4; // kg CO2 evitado por kWh
            $arvores = $co2Evitado / 20;

            return [
                'ano' => $ano,
                'mes' => $mes,
                'credito_gerado_reais' => round($creditoGerado, 2),
                'valor_guardado_kwh' => round($valorGuardado, 2),
                'reserva_total_atual_kwh' => round($reserva->total, 2),
                'faturamento_mes_reais' => round($valorPago, 2),
                'geracao_real_kwh' => round($geracaoMes, 2),
                'co2_evitado_kg' => round($co2Evitado, 2),
                'arvores_equivalentes' => round($arvores, 2),
                'reservas_expiradas' => $reservasExpiradas,
            ];
        });
    }

    private function criarPacoteAnual(Usina $usina, int $ano, string $mesNome, float $geracao): array
    {
        $cd = CreditosDistribuidos::create();
        $var = ValorAcumuladoReserva::create(['total' => 0]);
        $fa = FaturamentoUsina::create();
        $dgr = DadosGeracaoReal::create([$mesNome => $geracao]);

        $vinculo = CreditosDistribuidosUsina::create([
            'usi_id' => $usina->usi_id,
            'cli_id' => $usina->cli_id,
            'cd_id' => $cd->cd_id,
            'fa_id' => $fa->fa_id,
            'var_id' => $var->var_id,
            'ano' => $ano,
        ]);

        $dgrVinculo = DadosGeracaoRealUsina::create([
            'usi_id' => $usina->usi_id,
            'cli_id' => $usina->cli_id,
            'dgr_id' => $dgr->dgr_id,
            'ano' => $ano,
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
}