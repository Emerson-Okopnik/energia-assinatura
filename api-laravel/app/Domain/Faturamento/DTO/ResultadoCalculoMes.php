<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\DTO;

use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Reais;

/**
 * Resultado imutável do cálculo de um mês.
 *
 * valorFinal = valorFixo + valorVariavel + credito − cuo + receitaExpiracao (§2, §7).
 * guardadoKwh: excedente do mês que vai para a reserva (quando geração >= média).
 * consumosFifo: origens consumidas da reserva, para auditoria (§8).
 * receitaExpiracao: receita situacional de crédito expirado (§7), somada ao resultado
 *   e exibida em linha própria (não embutida no termo Crédito).
 * expiracoes: origens cujos saldos venceram sem uso, para auditoria (§7, §8).
 */
final readonly class ResultadoCalculoMes
{
    /** Receita situacional de crédito expirado (§7). Nunca nula: default R$ 0,00. */
    public Reais $receitaExpiracao;

    /**
     * @param array<int, array{origem: Competencia, kwh: Kwh}> $consumosFifo
     * @param array<int, array{origem: Competencia, kwh: Kwh}> $expiracoes
     */
    public function __construct(
        public Reais $valorFixo,
        public Reais $valorVariavel,
        public Reais $credito,
        public Reais $cuo,
        public Reais $valorFinal,
        public Kwh $guardadoKwh,
        public array $consumosFifo,
        ?Reais $receitaExpiracao = null,
        public array $expiracoes = [],
    ) {
        $this->receitaExpiracao = $receitaExpiracao ?? Reais::zero();
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
            'receita_expiracao' => $this->receitaExpiracao->emReais(),
            'expiracoes' => array_map(
                static fn (array $expiracao): array => [
                    'origem' => (string) $expiracao['origem'],
                    'kwh' => $expiracao['kwh']->valor(),
                ],
                $this->expiracoes,
            ),
        ];
    }
}
