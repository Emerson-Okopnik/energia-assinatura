<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\ValueObject;

/**
 * Valor monetário em Reais (R$).
 *
 * Internamente guardado como CENTAVOS inteiros para eliminar erro de ponto
 * flutuante (ver REGRAS_DE_CALCULO.md §11). Arredondamento para 2 casas
 * acontece apenas na borda de entrada (deReais) e de exibição (formatar).
 */
final readonly class Reais
{
    private function __construct(private int $centavos)
    {
    }

    public static function deCentavos(int $centavos): self
    {
        return new self($centavos);
    }

    /**
     * Cria a partir de um valor em reais (float), arredondando para o
     * centavo mais próximo. Esta é a única borda onde o float vira inteiro.
     */
    public static function deReais(float $reais): self
    {
        return new self((int) round($reais * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function emReais(): float
    {
        return $this->centavos / 100;
    }

    public function emCentavos(): int
    {
        return $this->centavos;
    }

    public function mais(self $outro): self
    {
        return new self($this->centavos + $outro->centavos);
    }

    public function menos(self $outro): self
    {
        return new self($this->centavos - $outro->centavos);
    }

    public function ehNegativo(): bool
    {
        return $this->centavos < 0;
    }

    /**
     * Formata em pt-BR: 156111 centavos -> "R$ 1.561,11".
     */
    public function formatar(): string
    {
        return 'R$ ' . number_format($this->emReais(), 2, ',', '.');
    }
}
