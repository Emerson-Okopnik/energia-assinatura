<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Calculo;

use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\DTO\ResultadoCalculoMes;
use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\MotorFifo;
use App\Domain\Faturamento\Ledger\ServicoExpiracao;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Reais;

/**
 * Motor de cálculo ÚNICO do faturamento de geração (REGRAS_DE_CALCULO.md §2-§6).
 *
 * Implementa a fórmula canônica de 4 termos:
 *   Valor Final = Valor Fixo + Valor Variável (Injetado) + Crédito − CUO
 *
 * Sem efeito colateral, sem Eloquent, sem dependência de framework. O consumo
 * da reserva é delegado ao MotorFifo (FIFO cross-ano), e o crédito jamais
 * excede o faltante nem o saldo real da reserva.
 */
final class CalculadoraGeracaoLinear
{
    public function __construct(
        private readonly MotorFifo $motorFifo = new MotorFifo(),
        private readonly ValidadorEntrada $validador = new ValidadorEntrada(),
        private readonly ServicoExpiracao $servicoExpiracao = new ServicoExpiracao(),
    ) {
    }

    /**
     * @param \App\Domain\Faturamento\Ledger\LoteReserva[] $lotesReserva
     */
    public function calcular(EntradaCalculoMes $e, array $lotesReserva): ResultadoCalculoMes
    {
        $this->validador->validar($e);

        // Predicado único, reusado no Variável e no acúmulo (DRY).
        $atingiuMedia = $e->geracaoLiquidaKwh->ehMaiorOuIgualA($e->mediaKwh);

        // §3 — Valor Fixo
        $fixo = $e->menorGeracaoKwh->vezesTarifa($e->tarifa);

        // §4 — Valor Variável (Injetado): teto na média; senão proporcional à geração.
        // Piso em zero: se menor_geracao > base, o injetado não pode ser negativo (§4).
        $baseVariavel = $atingiuMedia ? $e->mediaKwh : $e->geracaoLiquidaKwh;
        $variavel = $baseVariavel->menos($e->menorGeracaoKwh)
            ->max(Kwh::zero())
            ->vezesTarifa($e->tarifa);

        // §6 — Crédito: compensa o faltante consumindo a reserva via FIFO cross-ano.
        $faltante = $e->mediaKwh->menos($e->geracaoLiquidaKwh)->max(Kwh::zero());
        $fifo = $this->motorFifo->consumir($lotesReserva, $faltante, $e->competencia);
        /** @var Kwh $consumido */
        $consumido = $fifo['consumidoKwh'];
        $credito = $consumido->vezesTarifa($e->tarifa);

        // §7 — Expiração: PRIMEIRO consome (FIFO acima), DEPOIS expira o que SOBROU.
        // Reduz o saldo de cada lote pelo que foi consumido neste mês e delega ao
        // ServicoExpiracao; o crédito expirado vira receita UMA vez (não soma ao termo Crédito).
        $lotesAposConsumo = $this->reduzirSaldoPorConsumo($lotesReserva, $fifo['consumos']);
        $expiracao = $this->servicoExpiracao->aplicar($lotesAposConsumo, $e->competencia, $e->tarifa);
        /** @var Reais $receitaExpiracao */
        $receitaExpiracao = $expiracao['receita'];

        // §5 — CUO (subtraído).
        $fioBReais = Reais::deReais(
            $e->geracaoBrutaKwh->valor() * $e->fioB * $e->percentualLei / 100
        );
        $cuo = $e->faturaEnergia->mais($fioBReais)->mais($e->adicionalCuo);

        // §2 — Valor Final (com a receita situacional de expiração somada, §7).
        $valorFinal = $fixo->mais($variavel)->mais($credito)->menos($cuo)->mais($receitaExpiracao);

        // §6 (acúmulo) — excedente guardado quando geração >= média.
        $guardado = $atingiuMedia
            ? $e->geracaoLiquidaKwh->menos($e->mediaKwh)
            : Kwh::zero();

        return new ResultadoCalculoMes(
            valorFixo: $fixo,
            valorVariavel: $variavel,
            credito: $credito,
            cuo: $cuo,
            valorFinal: $valorFinal,
            guardadoKwh: $guardado,
            consumosFifo: $fifo['consumos'],
            receitaExpiracao: $receitaExpiracao,
            expiracoes: $expiracao['expirados'],
        );
    }

    /**
     * Reconstrói os lotes com o saldo reduzido pelo consumo FIFO do mês.
     * Puro: gera novos LoteReserva imutáveis, preservando origem e vencimento.
     *
     * @param LoteReserva[]                                              $lotes
     * @param array<int, array{origem: Competencia, kwh: Kwh}>           $consumos
     *
     * @return LoteReserva[]
     */
    private function reduzirSaldoPorConsumo(array $lotes, array $consumos): array
    {
        $consumidoPorOrigem = [];
        foreach ($consumos as $consumo) {
            $chave = (string) $consumo['origem'];
            $consumidoPorOrigem[$chave] = ($consumidoPorOrigem[$chave] ?? 0.0) + $consumo['kwh']->valor();
        }

        return array_map(
            static function (LoteReserva $lote) use ($consumidoPorOrigem): LoteReserva {
                $consumido = $consumidoPorOrigem[(string) $lote->competenciaOrigem] ?? 0.0;
                $saldoRestante = $lote->saldoKwh->menos(Kwh::de($consumido))->max(Kwh::zero());

                return new LoteReserva($lote->competenciaOrigem, $saldoRestante, $lote->vencimento);
            },
            $lotes,
        );
    }
}
