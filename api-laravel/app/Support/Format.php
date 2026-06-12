<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatação padronizada de UNIDADES para exibição (REGRAS_DE_CALCULO.md §1).
 *
 * Fonte ÚNICA de formatação do PDF: energia sempre em kWh, dinheiro sempre em
 * R$, no padrão pt-BR (1.234,56). Substitui os number_format() espalhados no
 * Blade. NÃO contém regra de cálculo — apenas apresentação na borda.
 */
final class Format
{
    /** Energia em kWh: 1234.5 -> "1.234,50 kWh". */
    public static function kwh(float|int|null $valor, int $casas = 2): string
    {
        return number_format((float) ($valor ?? 0), $casas, ',', '.') . ' kWh';
    }

    /** Dinheiro em reais: 1561.11 -> "R$ 1.561,11". */
    public static function reais(float|int|null $valor, int $casas = 2): string
    {
        return 'R$ ' . number_format((float) ($valor ?? 0), $casas, ',', '.');
    }

    /** Tarifa em R$/kWh: 0.51 -> "R$ 0,51 /kWh" (precisão cadastrada). */
    public static function tarifa(float|int|null $valor, int $casas = 2): string
    {
        return 'R$ ' . number_format((float) ($valor ?? 0), $casas, ',', '.') . ' /kWh';
    }

    /** Percentual: 60 -> "60,00%". */
    public static function percentual(float|int|null $valor, int $casas = 2): string
    {
        return number_format((float) ($valor ?? 0), $casas, ',', '.') . '%';
    }

    /** Número puro pt-BR (contagens, kg): 3943.2 -> "3.943,20". */
    public static function numero(float|int|null $valor, int $casas = 2): string
    {
        return number_format((float) ($valor ?? 0), $casas, ',', '.');
    }
}
