<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\DTO;

use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Reais;
use App\Domain\Faturamento\ValueObject\Tarifa;

/**
 * Entrada imutável do cálculo de um mês (REGRAS_DE_CALCULO.md §2-§9).
 *
 * Todas as grandezas já vêm tipadas em Value Objects. A geração líquida já
 * deve ter sido derivada (geracao_bruta − consumo_descontavel, §9) antes de
 * montar este DTO.
 */
final readonly class EntradaCalculoMes
{
    public function __construct(
        public Kwh $geracaoLiquidaKwh,
        public Kwh $mediaKwh,
        public Kwh $menorGeracaoKwh,
        public Kwh $geracaoBrutaKwh,
        public Tarifa $tarifa,
        public float $fioB,
        public float $percentualLei,
        public Reais $faturaEnergia,
        public Reais $adicionalCuo,
        public Competencia $competencia,
    ) {
    }

    /**
     * Factory a partir de array bruto (camada de aplicação/persistência).
     *
     * Aceita chaves snake_case. Energia em float kWh, dinheiro em float R$.
     *
     * @param array<string, mixed> $dados
     */
    public static function deArray(array $dados): self
    {
        // Chaves obrigatórias devem existir explicitamente: um consumo ausente
        // virando 0 silenciosamente mascararia o bug "consumo=0 congela" (§9).
        $obrigatorias = [
            'geracao_liquida_kwh', 'media_kwh', 'menor_geracao_kwh',
            'geracao_bruta_kwh', 'tarifa',
        ];
        foreach ($obrigatorias as $chave) {
            if (!array_key_exists($chave, $dados) || $dados[$chave] === null) {
                throw new \InvalidArgumentException("EntradaCalculoMes: chave obrigatória ausente: {$chave}");
            }
        }

        return new self(
            geracaoLiquidaKwh: Kwh::de((float) $dados['geracao_liquida_kwh']),
            mediaKwh: Kwh::de((float) $dados['media_kwh']),
            menorGeracaoKwh: Kwh::de((float) $dados['menor_geracao_kwh']),
            geracaoBrutaKwh: Kwh::de((float) $dados['geracao_bruta_kwh']),
            tarifa: Tarifa::de((float) $dados['tarifa']),
            fioB: (float) ($dados['fio_b'] ?? 0),
            percentualLei: (float) ($dados['percentual_lei'] ?? 0),
            faturaEnergia: Reais::deReais((float) ($dados['fatura_energia'] ?? 0)),
            adicionalCuo: Reais::deReais((float) ($dados['adicional_cuo'] ?? 0)),
            competencia: ($dados['competencia'] ?? null) instanceof Competencia
                ? $dados['competencia']
                : Competencia::de((int) $dados['ano'], (int) $dados['mes']),
        );
    }
}
