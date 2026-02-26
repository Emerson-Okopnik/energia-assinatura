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
    DadoConsumoUsina,
    DadoConsumo,
    HistoricoCalculoGeracao
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

            $pacoteAnualExistente = (bool) ($vinculo && $dgrVinculo);
            if (!$vinculo || !$dgrVinculo) {
                [$vinculo, $dgrVinculo] = $this->criarPacoteAnual($usina, $ano, $mesNome, $payload['mesGeracao_kwh']);
                $pacoteAnualExistente = false;
            }

            $credito = CreditosDistribuidos::findOrFail($vinculo->cd_id);
            $reserva = ValorAcumuladoReserva::findOrFail($vinculo->var_id);
            $faturamento = FaturamentoUsina::findOrFail($vinculo->fa_id);
            $geracao = DadosGeracaoReal::findOrFail($dgrVinculo->dgr_id);

            if (
                $pacoteAnualExistente
                && $this->mesJaFaturado($faturamento, $credito, $geracao, $reserva, $mesNome)
            ) {
                throw new \DomainException('O mes selecionado ja esta faturado e nao pode ser salvo novamente.');
            }

            $vinculoAnoAnterior = CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
                ->where('ano', $ano - 1)
                ->first();
            $reservaAnoAnterior = $vinculoAnoAnterior
                ? ValorAcumuladoReserva::find($vinculoAnoAnterior->var_id)
                : null;

            $snapshot = $this->montarSnapshot(
                $pacoteAnualExistente,
                $vinculo,
                $dgrVinculo,
                $credito,
                $reserva,
                $faturamento,
                $geracao,
                $reservaAnoAnterior
            );

            $this->registrarHistorico(
                $usina->usi_id,
                $ano,
                $mes,
                $snapshot,
                isset($payload['dcon_id']) ? (int) $payload['dcon_id'] : null,
                isset($payload['dcu_id']) ? (int) $payload['dcu_id'] : null
            );

            $tarifa = (float) $payload['tarifa_kwh'];
            $consumoUsina = DadoConsumoUsina::where('usi_id', $usina->usi_id)
                ->where('ano', $ano)
                ->with('dadoConsumo')
                ->first();

            $consumoMes = (float) ($consumoUsina?->dadoConsumo?->$mesNome ?? 0);
            $geracaoBrutaMes = (float) $payload['mesGeracao_kwh'];
            //$geracaoMes = max(0.0, $geracaoBrutaMes - $consumoMes);
            $geracaoMes = $geracaoBrutaMes;
            $media = (float) $payload['mediaGeracao_kwh'];
            $adicionalCuo = (float) ($payload['adicional_cuo'] ?? 0);
            $valorPago = (float) $payload['valorPago_mes'] + $adicionalCuo;

            $referencia = Carbon::create($ano, $mes, 1)->endOfMonth();
            $reservasExpiradas = [];
            $expiracaoAtual = $this->expirarReservas(
                $reserva,
                $ano,
                $tarifa,
                $referencia,
                $reservasExpiradas
            );

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

            $reservaAnterior = max(0.0, (float) ($reserva->total ?? 0));
            $valorGuardado = 0.0;
            $energiaCompensada = 0.0;
            $deficit = 0.0;
            if ($geracaoMes >= $media) {
                $valorGuardado = $geracaoMes - $media;
            } elseif ($reservaAnterior > 0) {
                $faltante = $media - $geracaoMes;
                $energiaCompensada = min($faltante, $reservaAnterior);
                $deficit = $faltante - $energiaCompensada;
                if ($deficit > 0) {
                    $valorPago += $deficit * $tarifa;
                }
            }

            $energiaParaDescontar = $energiaCompensada;
            if ($energiaParaDescontar > 0) {
                foreach ($this->meses as $num => $nome) {
                    $valor = (float) ($reserva->$nome ?? 0);
                    if ($valor <= 0) {
                        continue;
                    }
                    $retirar = min($valor, $energiaParaDescontar);
                    $reserva->$nome = max(0.0, $valor - $retirar);
                    $energiaParaDescontar -= $retirar;
                    if ($energiaParaDescontar <= 0) {
                        break;
                    }
                }
            }

            $creditoGerado = ($energiaCompensada * $tarifa) + $creditoExpirado;

            $saldoMesAtual = max(0.0, (float) ($reserva->$mesNome ?? 0));
            $reserva->$mesNome = $saldoMesAtual + $valorGuardado;
            $reserva->total = max(0.0, ($reserva->total ?? 0) + $valorGuardado - $energiaCompensada);
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
                'adicional_cuo_aplicado' => round($adicionalCuo, 2),
                'reservas_expiradas' => $reservasExpiradas,
            ];
        });
    }

    public function reverter(Usina $usina, int $ano, int $mes): array
    {
        return DB::transaction(function () use ($usina, $ano, $mes) {
            $mesNome = $this->meses[$mes] ?? null;
            if (!$mesNome) {
                throw new \InvalidArgumentException('Mes invalido');
            }

            $historico = HistoricoCalculoGeracao::where('usi_id', $usina->usi_id)
                ->where('ano', $ano)
                ->where('mes', $mes)
                ->first();

            if (!$historico) {
                throw new \DomainException('Nao existe historico para reverter este mes.');
            }

            if ($historico->reverted_at) {
                throw new \DomainException('A geracao deste mes ja foi revertida.');
            }

            $ultimoHistoricoAtivo = HistoricoCalculoGeracao::where('usi_id', $usina->usi_id)
                ->where('ano', $ano)
                ->whereNull('reverted_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('hcg_id')
                ->first();

            if (!$ultimoHistoricoAtivo || (int) $ultimoHistoricoAtivo->hcg_id !== (int) $historico->hcg_id) {
                throw new \DomainException('Para manter a consistencia, reverta primeiro o ultimo faturamento salvo.');
            }

            $snapshot = is_array($historico->snapshot) ? $historico->snapshot : [];
            if (empty($snapshot)) {
                throw new \DomainException('Snapshot de reversao nao encontrado.');
            }

            $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
            $vinculoMeta = is_array($meta['vinculo'] ?? null) ? $meta['vinculo'] : [];
            $pacoteCriado = (bool) ($meta['pacote_criado'] ?? false);

            $reservaAnoAnteriorSnapshot = is_array($snapshot['reserva_ano_anterior'] ?? null)
                ? $snapshot['reserva_ano_anterior']
                : null;

            if (
                $reservaAnoAnteriorSnapshot
                && isset($reservaAnoAnteriorSnapshot['var_id'], $reservaAnoAnteriorSnapshot['estado'])
            ) {
                $reservaAnoAnterior = ValorAcumuladoReserva::find((int) $reservaAnoAnteriorSnapshot['var_id']);
                if ($reservaAnoAnterior) {
                    $this->restaurarEstadoReserva($reservaAnoAnterior, $reservaAnoAnteriorSnapshot['estado']);
                    $reservaAnoAnterior->save();
                }
            }

            if ($pacoteCriado) {
                $this->reverterPacoteAnualCriado($vinculoMeta, $usina->usi_id, $ano);
            } else {
                $creditoId = (int) ($vinculoMeta['cd_id'] ?? 0);
                $varId = (int) ($vinculoMeta['var_id'] ?? 0);
                $faId = (int) ($vinculoMeta['fa_id'] ?? 0);
                $dgrId = (int) ($vinculoMeta['dgr_id'] ?? 0);

                $credito = $creditoId ? CreditosDistribuidos::find($creditoId) : null;
                $reserva = $varId ? ValorAcumuladoReserva::find($varId) : null;
                $faturamento = $faId ? FaturamentoUsina::find($faId) : null;
                $geracao = $dgrId ? DadosGeracaoReal::find($dgrId) : null;

                if (!$credito || !$reserva || !$faturamento || !$geracao) {
                    throw new \DomainException('Nao foi possivel localizar os registros para reversao.');
                }

                $this->restaurarEstadoMensal($credito, is_array($snapshot['credito'] ?? null) ? $snapshot['credito'] : []);
                $credito->save();

                $this->restaurarEstadoReserva($reserva, is_array($snapshot['reserva'] ?? null) ? $snapshot['reserva'] : []);
                $reserva->save();

                $this->restaurarEstadoMensal($faturamento, is_array($snapshot['faturamento'] ?? null) ? $snapshot['faturamento'] : []);
                $faturamento->save();

                $this->restaurarEstadoMensal($geracao, is_array($snapshot['geracao'] ?? null) ? $snapshot['geracao'] : []);
                $geracao->save();
            }

            $this->reverterConsumoAssociado(
                $historico->dcu_id ? (int) $historico->dcu_id : null,
                $historico->dcon_id ? (int) $historico->dcon_id : null,
                $usina->usi_id,
                $ano
            );

            $historico->reverted_at = now();
            $historico->save();

            return [
                'ano' => $ano,
                'mes' => $mes,
                'mes_nome' => $mesNome,
                'revertido' => true,
            ];
        });
    }

    private function mesJaFaturado(
        FaturamentoUsina $faturamento,
        CreditosDistribuidos $credito,
        DadosGeracaoReal $geracao,
        ValorAcumuladoReserva $reserva,
        string $mesNome
    ): bool {
        $epsilon = 0.000001;
        $valoresMes = [
            (float) ($faturamento->{$mesNome} ?? 0),
            (float) ($credito->{$mesNome} ?? 0),
            (float) ($geracao->{$mesNome} ?? 0),
            (float) ($reserva->{$mesNome} ?? 0),
        ];

        foreach ($valoresMes as $valor) {
            if (abs($valor) > $epsilon) {
                return true;
            }
        }

        return false;
    }

    private function registrarHistorico(
        int $usiId,
        int $ano,
        int $mes,
        array $snapshot,
        ?int $dconId,
        ?int $dcuId
    ): void {
        HistoricoCalculoGeracao::updateOrCreate(
            [
                'usi_id' => $usiId,
                'ano' => $ano,
                'mes' => $mes,
            ],
            [
                'snapshot' => $snapshot,
                'dcon_id' => $dconId,
                'dcu_id' => $dcuId,
                'reverted_at' => null,
            ]
        );
    }

    private function montarSnapshot(
        bool $pacoteAnualExistente,
        CreditosDistribuidosUsina $vinculo,
        DadosGeracaoRealUsina $dgrVinculo,
        CreditosDistribuidos $credito,
        ValorAcumuladoReserva $reserva,
        FaturamentoUsina $faturamento,
        DadosGeracaoReal $geracao,
        ?ValorAcumuladoReserva $reservaAnoAnterior
    ): array {
        return [
            'meta' => [
                'pacote_criado' => !$pacoteAnualExistente,
                'vinculo' => [
                    'cdu_id' => (int) $vinculo->cdu_id,
                    'cd_id' => (int) $vinculo->cd_id,
                    'fa_id' => (int) $vinculo->fa_id,
                    'var_id' => (int) $vinculo->var_id,
                    'dgru_id' => (int) $dgrVinculo->dgru_id,
                    'dgr_id' => (int) $dgrVinculo->dgr_id,
                ],
            ],
            'credito' => $this->extrairEstadoMensal($credito),
            'reserva' => $this->extrairEstadoReserva($reserva),
            'faturamento' => $this->extrairEstadoMensal($faturamento),
            'geracao' => $this->extrairEstadoMensal($geracao),
            'reserva_ano_anterior' => $reservaAnoAnterior
                ? [
                    'var_id' => (int) $reservaAnoAnterior->var_id,
                    'estado' => $this->extrairEstadoReserva($reservaAnoAnterior),
                ]
                : null,
        ];
    }

    private function extrairEstadoMensal(object $model): array
    {
        $estado = [];
        foreach ($this->meses as $mesNome) {
            $estado[$mesNome] = (float) ($model->{$mesNome} ?? 0);
        }
        return $estado;
    }

    private function extrairEstadoReserva(ValorAcumuladoReserva $reserva): array
    {
        return [
            'meses' => $this->extrairEstadoMensal($reserva),
            'total' => (float) ($reserva->total ?? 0),
        ];
    }

    private function restaurarEstadoMensal(object $model, array $estado): void
    {
        foreach ($this->meses as $mesNome) {
            $model->{$mesNome} = (float) ($estado[$mesNome] ?? 0);
        }
    }

    private function restaurarEstadoReserva(ValorAcumuladoReserva $reserva, array $estado): void
    {
        $mesesEstado = is_array($estado['meses'] ?? null) ? $estado['meses'] : [];
        $this->restaurarEstadoMensal($reserva, $mesesEstado);
        $reserva->total = (float) ($estado['total'] ?? 0);
    }

    private function reverterPacoteAnualCriado(array $vinculoMeta, int $usiId, int $ano): void
    {
        $dgruId = (int) ($vinculoMeta['dgru_id'] ?? 0);
        $cduId = (int) ($vinculoMeta['cdu_id'] ?? 0);
        $dgrId = (int) ($vinculoMeta['dgr_id'] ?? 0);
        $cdId = (int) ($vinculoMeta['cd_id'] ?? 0);
        $faId = (int) ($vinculoMeta['fa_id'] ?? 0);
        $varId = (int) ($vinculoMeta['var_id'] ?? 0);

        if ($dgruId > 0) {
            DadosGeracaoRealUsina::where('dgru_id', $dgruId)->delete();
        } else {
            DadosGeracaoRealUsina::where('usi_id', $usiId)->where('ano', $ano)->delete();
        }

        if ($cduId > 0) {
            CreditosDistribuidosUsina::where('cdu_id', $cduId)->delete();
        } else {
            CreditosDistribuidosUsina::where('usi_id', $usiId)->where('ano', $ano)->delete();
        }

        if ($dgrId > 0) {
            DadosGeracaoReal::where('dgr_id', $dgrId)->delete();
        }
        if ($cdId > 0) {
            CreditosDistribuidos::where('cd_id', $cdId)->delete();
        }
        if ($faId > 0) {
            FaturamentoUsina::where('fa_id', $faId)->delete();
        }
        if ($varId > 0) {
            ValorAcumuladoReserva::where('var_id', $varId)->delete();
        }
    }

    private function reverterConsumoAssociado(?int $dcuId, ?int $dconId, int $usiId, int $ano): void
    {
        if ($dcuId) {
            $consumoUsina = DadoConsumoUsina::where('dcu_id', $dcuId)
                ->where('usi_id', $usiId)
                ->where('ano', $ano)
                ->first();

            if ($consumoUsina) {
                $dconId = $dconId ?: (int) $consumoUsina->dcon_id;
                $consumoUsina->delete();
            }
        }

        if ($dconId) {
            $emUso = DadoConsumoUsina::where('dcon_id', $dconId)->exists();
            if (!$emUso) {
                DadoConsumo::where('dcon_id', $dconId)->delete();
            }
        }
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
                $expiradoKwh += $valor;
                $reservasExpiradas[] = [
                    'ano' => $ano,
                    'mes' => $num,
                    'expirado_kwh' => $valor,
                    'creditado_na_fatura_reais' => $valor * $tarifa,
                ];
            }
        }

        if ($expiradoKwh > 0) {
            $reserva->total = max(0, ($reserva->total ?? 0) - $expiradoKwh);
        }

        return [
            'expirado_kwh' => $expiradoKwh,
            'expirado_valor' => $expiradoKwh * $tarifa,
        ];
    }
}
