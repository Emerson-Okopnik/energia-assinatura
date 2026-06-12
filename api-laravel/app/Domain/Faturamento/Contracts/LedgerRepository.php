<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Contracts;

use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\ValueObject\Competencia;

/**
 * Porta de persistência do ledger de reserva (DIP — REGRAS_DE_CALCULO.md §8).
 *
 * O domínio (MotorFifo, Calculadora) depende desta abstração, nunca do Eloquent.
 * A implementação concreta (EloquentLedgerRepository) vive em Infrastructure.
 */
interface LedgerRepository
{
    /**
     * Persiste lançamentos imutáveis no ledger.
     *
     * Cada lançamento é um array associativo com as colunas de `credito_ledger`
     * (usi_id, competencia_origem, competencia_evento, tipo, kwh, tarifa_kwh,
     * valor_reais, vencimento, ref_lancamento_id, idempotency_key, user_id).
     *
     * @param array<int, array<string, mixed>> $lancamentos
     *
     * @return array<int, int> IDs (cl_id) dos lançamentos criados, na ordem de entrada
     */
    public function salvarLancamentos(array $lancamentos): array;

    /**
     * Lotes de reserva com saldo em aberto (saldo > 0), prontos para o FIFO.
     *
     * Agrupa por competencia_origem, soma os `kwh` não-estornados e descarta
     * origens com saldo <= 0. Cada origem vira um LoteReserva.
     *
     * @return LoteReserva[]
     */
    public function lotesEmAbertoDaUsina(int $usiId): array;

    /**
     * Lotes de reserva em aberto NO INÍCIO de uma competência (ponto no tempo).
     *
     * Diferente de {@see lotesEmAbertoDaUsina} (saldo total atual), considera
     * apenas o estado da reserva ANTES do mês informado: créditos de origem
     * anterior, menos consumos/expirações ocorridos antes do mês. Essencial para
     * calcular/recalcular um mês específico de forma consistente, independente da
     * ordem em que os meses são processados (REGRAS_DE_CALCULO.md §6).
     *
     * @return LoteReserva[]
     */
    public function lotesEmAbertoNoInicioDe(int $usiId, Competencia $competencia): array;

    /**
     * Marca lançamentos como estornados (não destrutivo — REGRAS_DE_CALCULO.md §10).
     *
     * @param int[] $lancamentoIds cl_id dos lançamentos a estornar
     *
     * @return int quantidade de lançamentos efetivamente estornados
     */
    public function marcarEstornado(array $lancamentoIds, \DateTimeImmutable $em): int;
}
