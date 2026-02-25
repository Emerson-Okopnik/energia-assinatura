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
    GeracaoFaturamentoPdf,
    DemonstrativoCreditosPdf,
    IdempotencyKey,
};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EstornoGeracaoService
{
    private const CAMPOS_EXCLUIDOS = ['var_id', 'created_at', 'updated_at'];

    public function estornar(Usina $usina, int $ano, int $mes, int $userId): void
    {
        DB::transaction(function () use ($usina, $ano, $mes, $userId) {
            $snapshot = HistoricoEstorno::where('usi_id', $usina->usi_id)
                ->whereNull('revertido_em')
                ->latest('he_id')
                ->lockForUpdate()
                ->first();

            if (!$snapshot) {
                throw new \InvalidArgumentException('Nenhum lançamento encontrado para reverter.');
            }

            if ($snapshot->mes !== $mes || $snapshot->ano !== $ano) {
                throw new \InvalidArgumentException(
                    'Apenas o último mês lançado pode ser revertido. ' .
                    'Reverta ' . ucfirst($snapshot->mes_nome) . '/' . $snapshot->ano . ' primeiro.'
                );
            }

            $vinculo = CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
                ->where('ano', $ano)
                ->lockForUpdate()
                ->firstOrFail();

            $reserva = ValorAcumuladoReserva::where('var_id', $vinculo->var_id)
                ->lockForUpdate()
                ->firstOrFail();

            $credito = CreditosDistribuidos::where('cd_id', $vinculo->cd_id)
                ->lockForUpdate()
                ->firstOrFail();

            $faturamento = FaturamentoUsina::where('fa_id', $vinculo->fa_id)
                ->lockForUpdate()
                ->firstOrFail();

            $dgrVinculo = DadosGeracaoRealUsina::where('usi_id', $usina->usi_id)
                ->where('ano', $ano)
                ->lockForUpdate()
                ->firstOrFail();

            $geracao = DadosGeracaoReal::where('dgr_id', $dgrVinculo->dgr_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->restaurarReserva($reserva, $snapshot->snapshot_reserva_atual);

            if ($snapshot->snapshot_reserva_anterior) {
                $vinculoAnterior = CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
                    ->where('ano', $ano - 1)
                    ->first();

                if ($vinculoAnterior) {
                    $reservaAnterior = ValorAcumuladoReserva::find($vinculoAnterior->var_id);
                    if ($reservaAnterior) {
                        $this->restaurarReserva($reservaAnterior, $snapshot->snapshot_reserva_anterior);
                    }
                }
            }

            $mesNome = $snapshot->mes_nome;

            $credito->$mesNome = $snapshot->snapshot_credito_mes;
            $credito->save();

            $faturamento->$mesNome = $snapshot->snapshot_faturamento_mes;
            $faturamento->save();

            $geracao->$mesNome = $snapshot->snapshot_geracao_mes;
            $geracao->save();

            $competencia = Carbon::createFromDate($ano, $mes, 1)->startOfMonth()->toDateString();

            GeracaoFaturamentoPdf::where('usi_id', $usina->usi_id)
                ->where('competencia', $competencia)
                ->delete();

            DemonstrativoCreditosPdf::where('usi_id', $usina->usi_id)
                ->where('competencia', $competencia)
                ->delete();

            if ($snapshot->idempotency_key) {
                IdempotencyKey::where('key', $snapshot->idempotency_key)->delete();
            }

            $snapshot->update([
                'user_id_estorno' => $userId,
                'revertido_em'    => now(),
            ]);
        });
    }

    public function ultimoRevertivel(int $usiId): ?HistoricoEstorno
    {
        return HistoricoEstorno::where('usi_id', $usiId)
            ->whereNull('revertido_em')
            ->latest('he_id')
            ->first();
    }

    private function restaurarReserva(ValorAcumuladoReserva $reserva, array $snapshot): void
    {
        foreach ($snapshot as $campo => $valor) {
            if (in_array($campo, self::CAMPOS_EXCLUIDOS, true)) {
                continue;
            }
            $reserva->$campo = $valor;
        }
        $reserva->save();
    }
}
