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
        // Saldo por origem = soma dos kwh não-estornados (entradas e saídas)
        // agrupados pela competência de origem. Vencimento = o mais cedo gravado
        // entre as linhas da origem (CREDITO/SALDO_INICIAL definem o vencimento).
        $linhas = CreditoLedger::query()
            ->doUsina($usiId)
            ->naoEstornado()
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
