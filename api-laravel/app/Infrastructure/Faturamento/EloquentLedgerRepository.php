<?php

declare(strict_types=1);

namespace App\Infrastructure\Faturamento;

use App\Domain\Faturamento\Contracts\LedgerRepository;
use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Models\CreditoLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Adaptador Eloquent do {@see LedgerRepository}.
 *
 * Traduz entre as linhas da tabela `credito_ledger` e os objetos de domínio
 * (LoteReserva). O domínio permanece puro: só conhece a interface e os VOs.
 */
final class EloquentLedgerRepository implements LedgerRepository
{
    /**
     * Prazo de validade do crédito em dias (REGRAS_DE_CALCULO.md §7),
     * usado quando o vencimento não está gravado na linha.
     */
    private const PRAZO_VENCIMENTO_DIAS = 180;

    public function salvarLancamentos(array $lancamentos): array
    {
        if ($lancamentos === []) {
            return [];
        }

        return DB::transaction(function () use ($lancamentos): array {
            $ids = [];

            foreach ($lancamentos as $lancamento) {
                $registro = CreditoLedger::create($lancamento);
                $ids[] = (int) $registro->cl_id;
            }

            return $ids;
        });
    }

    public function lotesEmAbertoDaUsina(int $usiId): array
    {
        // Saldo TOTAL atual por origem (todos os lançamentos não-estornados).
        $query = CreditoLedger::query()
            ->doUsina($usiId)
            ->naoEstornado();

        return $this->montarLotes($query);
    }

    public function lotesEmAbertoNoInicioDe(int $usiId, Competencia $competencia): array
    {
        $inicioMes = sprintf('%04d-%02d-01', $competencia->ano, $competencia->mes);

        // Reserva como estava ANTES do mês: créditos de origem anterior, menos
        // consumos/expirações que ocorreram (evento) antes do mês. Lançamentos do
        // próprio mês (ou futuros) não entram — assim cada mês é calculado contra o
        // estado correto da reserva, independente da ordem de processamento.
        $query = CreditoLedger::query()
            ->doUsina($usiId)
            ->naoEstornado()
            ->where('competencia_origem', '<', $inicioMes)
            ->where(function ($q) use ($inicioMes): void {
                $q->whereIn('tipo', [
                    CreditoLedger::TIPO_CREDITO,
                    CreditoLedger::TIPO_SALDO_INICIAL,
                ])->orWhere('competencia_evento', '<', $inicioMes);
            });

        return $this->montarLotes($query);
    }

    /**
     * Agrupa por origem, soma os kwh, descarta saldo <= 0 e monta os LoteReserva.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return LoteReserva[]
     */
    private function montarLotes($query): array
    {
        $linhas = $query
            ->selectRaw('competencia_origem')
            ->selectRaw('SUM(kwh) as saldo_kwh')
            ->selectRaw('MIN(vencimento) as vencimento')
            ->groupBy('competencia_origem')
            ->orderBy('competencia_origem')
            ->get();

        $lotes = [];

        foreach ($linhas as $linha) {
            $saldo = (float) $linha->saldo_kwh;

            // Descarta origens sem saldo positivo (invariante §8: saldo nunca < 0).
            if ($saldo <= 0.0) {
                continue;
            }

            $origem = CarbonImmutable::parse($linha->competencia_origem);
            $competencia = Competencia::de((int) $origem->year, (int) $origem->month);

            $vencimento = $linha->vencimento !== null
                ? CarbonImmutable::parse($linha->vencimento)->toDateTimeImmutable()
                : $competencia->vencimentoEmDias(self::PRAZO_VENCIMENTO_DIAS);

            $lotes[] = LoteReserva::de(
                $competencia,
                Kwh::de($saldo),
                $vencimento,
            );
        }

        return $lotes;
    }

    public function marcarEstornado(array $lancamentoIds, \DateTimeImmutable $em): int
    {
        if ($lancamentoIds === []) {
            return 0;
        }

        return CreditoLedger::query()
            ->whereIn('cl_id', $lancamentoIds)
            ->whereNull('estornado_em')
            ->update(['estornado_em' => CarbonImmutable::instance(
                $em instanceof \DateTime ? $em : \DateTime::createFromInterface($em)
            )]);
    }
}
