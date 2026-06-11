<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\ValueObject;

/**
 * Energia em quilowatt-hora (kWh).
 *
 * Imutável. Valor float internamente (kWh tolera precisão de ponto flutuante
 * pois o dinheiro só nasce no cruzamento com a Tarifa, que vira Reais/centavos).
 */
final readonly class Kwh
{
    private function __construct(private float $valor)
    {
    }

    public static function de(float $valor): self
    {
        return new self($valor);
    }

    public static function zero(): self
    {
        return new self(0.0);
    }

    public function valor(): float
    {
        return $this->valor;
    }

    public function mais(self $outro): self
    {
        return new self($this->valor + $outro->valor);
    }

    public function menos(self $outro): self
    {
        return new self($this->valor - $outro->valor);
    }

    public function vezesTarifa(Tarifa $tarifa): Reais
    {
        return Reais::deReais($this->valor * $tarifa->valor());
    }

    public function ehPositivo(): bool
    {
        return $this->valor > 0;
    }

    public function ehNegativo(): bool
    {
        return $this->valor < 0;
    }

    public function ehMaiorOuIgualA(self $outro): bool
    {
        return $this->valor >= $outro->valor;
    }

    public function min(self $outro): self
    {
        return $this->valor <= $outro->valor ? $this : $outro;
    }

    public function max(self $outro): self
    {
        return $this->valor >= $outro->valor ? $this : $outro;
    }
}
