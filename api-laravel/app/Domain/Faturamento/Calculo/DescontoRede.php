<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Calculo;

use App\Domain\Faturamento\ValueObject\Kwh;

/**
 * Geração líquida e desconto de rede (REGRAS_DE_CALCULO.md §9).
 *
 *   consumo_descontavel = max(consumo − desconto_rede, 0)
 *   geracao_liquida     = max(geracao_bruta − consumo_descontavel, 0)
 *
 * Desconto por tipo de conexão: Trifásico 100 / Bifásico 50 / Monofásico 30 kWh.
 * Tipo ausente/desconhecido => desconto 0 (e a usina deve ser sinalizada na
 * camada de qualidade de dados do relatório).
 */
final class DescontoRede
{
    private const POR_TIPO = [
        'Trifásico' => 100.0,
        'Bifásico' => 50.0,
        'Monofásico' => 30.0,
    ];

    public static function kwhPorTipo(?string $rede): float
    {
        return self::POR_TIPO[$rede] ?? 0.0;
    }

    public static function liquida(Kwh $bruta, Kwh $consumo, ?string $rede): Kwh
    {
        $descontavel = max($consumo->valor() - self::kwhPorTipo($rede), 0.0);
        $liquida = max($bruta->valor() - $descontavel, 0.0);

        return Kwh::de($liquida);
    }
}
