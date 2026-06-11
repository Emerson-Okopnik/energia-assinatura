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
    CreditoLedger,
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

            // A coluna `total` da reserva é o saldo corrente: o snapshot capturou-a
            // ANTES do lançamento (FaturamentoService:373), então restaurá-la devolve
            // o saldo exato pré-lançamento. A fonte de verdade do saldo segue sendo o
            // ledger (revertido abaixo); esta coluna é cache de leitura.
            $this->restaurarReserva($reserva, $snapshot->snapshot_reserva_atual);

            $mesNome = $snapshot->mes_nome;

            $credito->$mesNome = $snapshot->snapshot_credito_mes;
            $credito->save();

            $faturamento->$mesNome = $snapshot->snapshot_faturamento_mes;
            $faturamento->save();

            $geracao->$mesNome = $snapshot->snapshot_geracao_mes;
            $geracao->save();

            $competencia = Carbon::createFromDate($ano, $mes, 1)->startOfMonth()->toDateString();

            // Reverte o LEDGER (§10): marca como estornados TODOS os lançamentos cujo
            // EVENTO é este mês — o CREDITO guardado no mês, e os CONSUMO/EXPIRACAO que
            // ocorreram neste mês (que consumiram/expiraram lotes de origem anterior).
            // Marcar estornado devolve a energia aos lotes de origem (o saldo por origem
            // soma só os não-estornados), retornando a reserva ao estado pré-lançamento.
            // Seguro porque só o ÚLTIMO mês lançado é reversível: nenhum mês posterior
            // consumiu deste, então não há referência órfã.
            CreditoLedger::where('usi_id', $usina->usi_id)
                ->whereDate('competencia_evento', $competencia)
                ->whereNull('estornado_em')
                ->update(['estornado_em' => now()]);

            // whereDate: a coluna é cast 'date' e pode armazenar com hora; match exato
            // por string falharia e deixaria o cache órfão (mesma classe do bug do ledger).
            GeracaoFaturamentoPdf::where('usi_id', $usina->usi_id)
                ->whereDate('competencia', $competencia)
                ->delete();

            DemonstrativoCreditosPdf::where('usi_id', $usina->usi_id)
                ->whereDate('competencia', $competencia)
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
