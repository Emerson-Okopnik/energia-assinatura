<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Calculo;

use App\Domain\Faturamento\DTO\EntradaCalculoMes;

/**
 * Valida a coerência da entrada antes do cálculo.
 *
 * Falha rápido com mensagem clara: regras de negócio do núcleo não devem rodar
 * sobre dados incoerentes (tarifa não-positiva, kWh negativo, percentual fora de faixa).
 */
final class ValidadorEntrada
{
    public function validar(EntradaCalculoMes $e): void
    {
        if ($e->tarifa->valor() <= 0) {
            throw new \InvalidArgumentException(
                "Tarifa deve ser positiva; recebido {$e->tarifa->valor()}."
            );
        }

        if ($e->mediaKwh->ehNegativo()) {
            throw new \InvalidArgumentException(
                "Média de geração não pode ser negativa; recebido {$e->mediaKwh->valor()} kWh."
            );
        }

        $this->garantirNaoNegativo('Geração líquida', $e->geracaoLiquidaKwh->valor());
        $this->garantirNaoNegativo('Menor geração', $e->menorGeracaoKwh->valor());
        $this->garantirNaoNegativo('Geração bruta', $e->geracaoBrutaKwh->valor());

        if ($e->percentualLei < 0 || $e->percentualLei > 100) {
            throw new \InvalidArgumentException(
                "Percentual da Lei 14.300 deve estar entre 0 e 100; recebido {$e->percentualLei}."
            );
        }

        if ($e->fioB < 0) {
            throw new \InvalidArgumentException(
                "Fio B não pode ser negativo; recebido {$e->fioB}."
            );
        }
    }

    private function garantirNaoNegativo(string $rotulo, float $valor): void
    {
        if ($valor < 0) {
            throw new \InvalidArgumentException(
                "{$rotulo} não pode ser negativa; recebido {$valor} kWh."
            );
        }
    }
}
