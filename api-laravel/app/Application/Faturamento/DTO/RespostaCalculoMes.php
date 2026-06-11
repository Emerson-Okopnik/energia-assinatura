<?php

declare(strict_types=1);

namespace App\Application\Faturamento\DTO;

use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\DTO\ResultadoCalculoMes;

/**
 * Resposta da camada de aplicação para o cálculo de UM mês.
 *
 * Embrulha o {@see ResultadoCalculoMes} do núcleo (puro) com os dados de entrada
 * derivados e os metadados de persistência (se houve gravação, quais lançamentos
 * foram criados no ledger). Serve tanto ao preview (persistido=false) quanto ao
 * cálculo definitivo. Toda a auditoria (§8) está em toArray().
 */
final readonly class RespostaCalculoMes
{
    /** Fatores ambientais (mesmos do sistema legado / Blade). */
    private const FATOR_CO2_KG_POR_KWH = 0.4;
    private const KG_CO2_POR_ARVORE = 20;

    /**
     * @param int[] $ledgerLancamentoIds cl_id dos lançamentos criados (vazio no preview)
     */
    public function __construct(
        public int $usiId,
        public int $ano,
        public int $mes,
        public string $mesNome,
        public EntradaCalculoMes $entrada,
        public ResultadoCalculoMes $resultado,
        public float $consumoKwh,
        public ?string $rede,
        public float $descontoRedeKwh,
        public bool $persistido,
        public array $ledgerLancamentoIds = [],
        public float $saldoReservaAntesKwh = 0.0,
        public float $saldoReservaDepoisKwh = 0.0,
    ) {
    }

    /**
     * Serialização completa para a API: TODOS os termos (R$ e kWh) + detalhe de
     * consumo FIFO + expiração, para auditoria fácil (REGRAS_DE_CALCULO.md §8).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $r = $this->resultado;
        $e = $this->entrada;

        return [
            'usi_id' => $this->usiId,
            'ano' => $this->ano,
            'mes' => $this->mes,
            'mes_nome' => $this->mesNome,
            'persistido' => $this->persistido,

            // §9 — Geração líquida e desconto de rede (auditável passo a passo).
            'geracao' => [
                'bruta_kwh' => $e->geracaoBrutaKwh->valor(),
                'consumo_kwh' => $this->consumoKwh,
                'rede' => $this->rede,
                'desconto_rede_kwh' => $this->descontoRedeKwh,
                'liquida_kwh' => $e->geracaoLiquidaKwh->valor(),
            ],

            // Parâmetros derivados da usina (transparência de cálculo).
            'parametros' => [
                'media_kwh' => $e->mediaKwh->valor(),
                'menor_geracao_kwh' => $e->menorGeracaoKwh->valor(),
                'tarifa_kwh' => $e->tarifa->valor(),
                'fio_b' => $e->fioB,
                'percentual_lei' => $e->percentualLei,
                'fatura_energia_reais' => $e->faturaEnergia->emReais(),
                'adicional_cuo_reais' => $e->adicionalCuo->emReais(),
                // Indicadores ambientais (fatores do sistema: 0,4 kg CO2/kWh; 20 kg/árvore).
                'co2_evitado_kg' => round($e->geracaoBrutaKwh->valor() * self::FATOR_CO2_KG_POR_KWH, 2),
                'arvores_equivalentes' => round(
                    ($e->geracaoBrutaKwh->valor() * self::FATOR_CO2_KG_POR_KWH) / self::KG_CO2_POR_ARVORE,
                    2
                ),
            ],

            // §2 — Fórmula de 4 termos (+ receita situacional de expiração).
            'termos' => [
                'valor_fixo_reais' => $r->valorFixo->emReais(),
                'valor_variavel_reais' => $r->valorVariavel->emReais(),
                'credito_reais' => $r->credito->emReais(),
                'cuo_reais' => $r->cuo->emReais(),
                'receita_expiracao_reais' => $r->receitaExpiracao->emReais(),
                'valor_final_reais' => $r->valorFinal->emReais(),
            ],

            // Reserva (kWh) — entrada da reserva neste mês e saldo resultante.
            'reserva' => [
                'guardado_kwh' => $r->guardadoKwh->valor(),
                'saldo_antes_kwh' => $this->saldoReservaAntesKwh,
                'saldo_depois_kwh' => $this->saldoReservaDepoisKwh,
            ],

            // §6, §8 — detalhe FIFO: de qual origem veio cada kWh de crédito.
            'consumo_fifo' => array_map(
                static fn (array $c): array => [
                    'origem' => (string) $c['origem'],
                    'kwh' => $c['kwh']->valor(),
                ],
                $r->consumosFifo,
            ),

            // §7, §8 — lotes que venceram sem uso (viraram receita).
            'expiracao' => array_map(
                static fn (array $ex): array => [
                    'origem' => (string) $ex['origem'],
                    'kwh' => $ex['kwh']->valor(),
                ],
                $r->expiracoes,
            ),

            'ledger_lancamento_ids' => $this->ledgerLancamentoIds,
        ];
    }
}
