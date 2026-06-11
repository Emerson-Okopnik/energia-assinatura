<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\ValueObject;

/**
 * Tarifa em R$/kWh.
 *
 * Valor imutável com a precisão cadastrada (até 6 casas).
 */
final readonly class Tarifa
{
    private function __construct(private float $valor)
    {
    }

    public static function de(float $valor): self
    {
        return new self($valor);
    }

    public function valor(): float
    {
        return $this->valor;
    }
}
