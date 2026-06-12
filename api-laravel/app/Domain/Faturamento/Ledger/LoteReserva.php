<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Ledger;

use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;

/**
 * Lote de reserva: visão de LEITURA do saldo disponível de uma origem,
 * consolidado a partir do ledger (REGRAS_DE_CALCULO.md §8).
 *
 * Objeto imutável consumido pelo MotorFifo. O saldo aqui já é o saldo
 * líquido não-estornado da origem; o motor nunca consome mais que ele.
 */
final readonly class LoteReserva
{
    public function __construct(
        public Competencia $competenciaOrigem,
        public Kwh $saldoKwh,
        public \DateTimeImmutable $vencimento,
    ) {
    }

    public static function de(
        Competencia $competenciaOrigem,
        Kwh $saldoKwh,
        \DateTimeImmutable $vencimento,
    ): self {
        return new self($competenciaOrigem, $saldoKwh, $vencimento);
    }
}
