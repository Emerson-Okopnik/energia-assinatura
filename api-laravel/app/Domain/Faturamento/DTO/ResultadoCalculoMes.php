<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\DTO;

use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Reais;

/**
 * Resultado imutável do cálculo de um mês.
 *
 * valorFinal = valorFixo + valorVariavel + credito − cuo (§2).
 * guardadoKwh: excedente do mês que vai para a reserva (quando geração >= média).
 * consumosFifo: origens consumidas da reserva, para auditoria (§8).
 */
final readonly class ResultadoCalculoMes
{
    /**
     * @param array<int, array{origem: Competencia, kwh: Kwh}> $consumosFifo
     */
    public function __construct(
        public Reais $valorFixo,
        public Reais $valorVariavel,
        public Reais $credito,
        public Reais $cuo,
        public Reais $valorFinal,
        public Kwh $guardadoKwh,
        public array $consumosFifo,
    ) {
    }

    /**
     * Serialização plana para persistência/exibição.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valor_fixo' => $this->valorFixo->emReais(),
            'valor_variavel' => $this->valorVariavel->emReais(),
            'credito' => $this->credito->emReais(),
            'cuo' => $this->cuo->emReais(),
            'valor_final' => $this->valorFinal->emReais(),
            'guardado_kwh' => $this->guardadoKwh->valor(),
            'consumos_fifo' => array_map(
                static fn (array $consumo): array => [
                    'origem' => (string) $consumo['origem'],
                    'kwh' => $consumo['kwh']->valor(),
                ],
                $this->consumosFifo,
            ),
        ];
    }
}
