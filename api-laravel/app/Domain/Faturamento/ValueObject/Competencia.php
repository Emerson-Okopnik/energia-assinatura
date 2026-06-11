<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\ValueObject;

/**
 * Competência (mês/ano) de um lançamento de faturamento.
 *
 * Imutável. Usada para ordenação cronológica do FIFO cross-ano e para
 * derivar vencimentos a partir do dia 1 do mês.
 */
final readonly class Competencia
{
    private function __construct(
        public int $ano,
        public int $mes,
    ) {
    }

    public static function de(int $ano, int $mes): self
    {
        if ($mes < 1 || $mes > 12) {
            throw new \InvalidArgumentException(
                "Mês inválido: {$mes}. Deve estar entre 1 e 12."
            );
        }

        return new self($ano, $mes);
    }

    /**
     * Vencimento = (dia 1 do mês da competência) + N dias.
     * Ex.: competência 2025-12 + 180 dias.
     */
    public function vencimentoEmDias(int $dias): \DateTimeImmutable
    {
        $base = new \DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', $this->ano, $this->mes)
        );

        return $base->modify("+{$dias} days");
    }

    public function ehAnteriorA(self $outro): bool
    {
        return $this->comparar($outro) < 0;
    }

    /**
     * Ordenação cronológica ASC: negativo se $this é mais antigo, 0 se igual,
     * positivo se mais recente. Adequado para usort.
     */
    public function comparar(self $outro): int
    {
        return [$this->ano, $this->mes] <=> [$outro->ano, $outro->mes];
    }

    public function ehIgualA(self $outro): bool
    {
        return $this->ano === $outro->ano && $this->mes === $outro->mes;
    }

    public function __toString(): string
    {
        return sprintf('%04d-%02d', $this->ano, $this->mes);
    }
}
