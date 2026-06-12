<?php

declare(strict_types=1);

namespace App\Http\ViewModels;

use App\Application\Faturamento\DTO\RespostaCalculoMes;
use App\Application\Faturamento\FaturamentoService;
use App\Models\DadosGeracaoRealUsina;
use App\Models\GeracaoFaturamentoPdf;
use App\Models\Usina;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Monta o ViewModel do PDF da usina LENDO do motor único (FaturamentoService).
 *
 * PLANO_REDESENHO.md Fase 6 / REGRAS_DE_CALCULO.md: o PDF NÃO recalcula nada.
 * Esta classe apenas ORQUESTRA: carrega os dados de entrada, chama
 * {@see FaturamentoService::calcularMes()} (preview, sem persistir) para cada
 * mês da janela e reúne os termos do motor (fixo, injetado=valor_variavel,
 * creditado=credito, cuo, valor_final, receita_expiracao, consumo_fifo, etc.).
 *
 * Toda a fórmula vive no núcleo (DRY); aqui não há nenhuma operação aritmética
 * de faturamento — apenas somas de totais já calculados pelo motor.
 *
 * §3.1: As seções "Auditoria" e "Parâmetros de Cálculo" foram removidas do PDF.
 * O ViewModel não produz mais `auditoria` nem `parametros`.
 *
 * Extraída do controller para ser testável sem Browsershot/HTTP.
 */
final class UsinaPdfViewModel
{
    /** @var array<int, string> */
    private const MESES = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    /** Fatores ambientais (mesmos do motor: RespostaCalculoMes). */
    private const FATOR_CO2_KG_POR_KWH = 0.4;
    private const KG_CO2_POR_ARVORE = 20;

    public function __construct(
        private readonly FaturamentoService $faturamento,
    ) {
    }

    /**
     * @return array<string, mixed> dados prontos para a view usina.blade.php
     */
    public function montar(Usina $usina, int $ano, int $mes, string $observacoes = ''): array
    {
        $anchor = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
        $usiId = (int) $usina->usi_id;

        // Janela: mês selecionado + 11 anteriores; só meses com geração real > 0
        // e dentro do ano/mês selecionado (mesma regra da implementação anterior).
        $datas = collect();
        for ($i = 11; $i >= 0; $i--) {
            $datas->push($anchor->copy()->subMonths($i));
        }

        $geracoesReais = DadosGeracaoRealUsina::where('usi_id', $usiId)
            ->whereIn('ano', $datas->map->year->unique()->values()->all())
            ->with('DadosGeracaoReal')
            ->get()
            ->keyBy('ano');

        // CACHE do motor já persistido (fonte preferida): se existir, reflete o
        // que o FaturamentoService gravou na última apuração.
        $cachePorCompetencia = GeracaoFaturamentoPdf::where('usi_id', $usiId)
            ->get()
            ->keyBy(fn ($r) => $this->competenciaChave($r->competencia));

        $dadosMensais = [];
        $valoresGeracao = [];
        $labels = [];
        $totalCuo = 0.0;
        $totalValorFinal = 0.0;
        $totalGuardadoKwh = 0.0;
        $linhasCreditos = [];

        $valorFinalMesSelecionado = 0.0;
        $geracaoMesSelecionado = 0.0;

        foreach ($datas as $data) {
            $anoMes = (int) $data->year;
            $mesNum = (int) $data->month;

            if ($anoMes !== $ano || $mesNum > $mes) {
                continue;
            }

            $bruta = $this->geracaoRealDoMes($geracoesReais, $anoMes, $mesNum);
            if ($bruta <= 0.0) {
                continue;
            }

            $label = $this->label($anoMes, $mesNum);
            $competenciaData = $data->copy()->startOfMonth()->toDateString();

            // fatura_energia DERIVADA do cache existente (transitório, Fase 6):
            // a fatura da concessionária não vive em tabela própria, então é
            // reconstruída do CUO já gravado:
            //   fatura = cuo − (geracao × fio_b × percentual_lei / 100), clamp >= 0.
            // Ausente => 0 (documentado: motor recalcula com fatura 0 até o
            // próximo lançamento real persistir o CUO definitivo).
            $faturaEnergia = $this->faturaEnergiaDerivada(
                $cachePorCompetencia->get($competenciaData),
                $usina,
                $bruta,
            );

            $resposta = $this->faturamento->calcularMes(
                $usina,
                $anoMes,
                $mesNum,
                [
                    'geracao_bruta_kwh' => $bruta,
                    'fatura_energia' => $faturaEnergia,
                ],
                persistir: false,
            );

            $termos = $resposta->resultado;

            $dadosMensais[$label] = [
                'geracao_kwh' => $resposta->entrada->geracaoBrutaKwh->valor(),
                'fixo' => $termos->valorFixo->emReais(),
                'injetado' => $termos->valorVariavel->emReais(),
                'creditado' => $termos->credito->emReais(),
                'cuo' => $termos->cuo->emReais(),
                'valor_final' => $termos->valorFinal->emReais(),
            ];

            $valoresGeracao[] = $resposta->entrada->geracaoBrutaKwh->valor();
            $labels[] = $label;

            // Demonstrativo de créditos (substitui a 2ª simulação FIFO removida).
            $vencimento = $data->copy()->startOfMonth()
                ->addDays(self::vencimentoDias())->format('Y-m-d');
            $linhasCreditos[$label] = [
                'guardado' => $termos->guardadoKwh->valor(),
                'creditado_kwh' => $this->somaConsumoFifoKwh($resposta),
                'vencimento' => $this->labelData($vencimento),
                'meses_utilizados' => $this->mesesUtilizadosTexto($resposta),
                // Crédito vencido PAGO como receita no Valor Final (§3.1 — nunca
                // rotular como perda; o motor o converte em dinheiro).
                'convertido_receita' => $termos->receitaExpiracao->emReais(),
            ];

            $totalCuo += $termos->cuo->emReais();
            $totalValorFinal += $termos->valorFinal->emReais();
            $totalGuardadoKwh += $termos->guardadoKwh->valor();

            if ($mesNum === $mes) {
                $valorFinalMesSelecionado = $termos->valorFinal->emReais();
                $geracaoMesSelecionado = $resposta->entrada->geracaoBrutaKwh->valor();
            }
        }

        $co2Evitado = round($geracaoMesSelecionado * self::FATOR_CO2_KG_POR_KWH, 2);
        $arvores = round($co2Evitado / self::KG_CO2_POR_ARVORE, 2);

        // Demonstrativo de Créditos: últimos 6 meses, pronto (zero lógica no Blade).
        // temConvertidoReceita olha o MESMO slice exibido — senão a coluna
        // renderizaria com todas as células visíveis em '-'.
        $dadosCreditos = array_slice($linhasCreditos, -6, null, true);

        return [
            'usina' => $usina,
            'dadosMensais' => $dadosMensais,
            'valoresGeracao' => $valoresGeracao,
            'nomesMeses' => $labels,

            'valorReceber' => $valorFinalMesSelecionado,
            'mesAnoSelecionado' => $this->label($ano, $mes),
            'geracaoMes' => $geracaoMesSelecionado,
            'co2Evitado' => $co2Evitado,
            'arvores' => $arvores,

            'observacoes' => $observacoes,

            'dadosCreditos' => $dadosCreditos,
            'temConvertidoReceita' => collect($dadosCreditos)
                ->contains(fn (array $l): bool => $l['convertido_receita'] > 0),

            // Totais com nomes honestos — somam APENAS valores do motor.
            'totalGuardadoKwh' => $totalGuardadoKwh,
            'totalCuo' => $totalCuo,
            'totalValorFinal' => $totalValorFinal,
        ];
    }

    private function geracaoRealDoMes($geracoesReais, int $ano, int $mes): float
    {
        $registroAno = $geracoesReais->get($ano);
        $coluna = self::MESES[$mes];
        $valor = optional($registroAno?->DadosGeracaoReal)->{$coluna};

        return $valor === null ? 0.0 : (float) $valor;
    }

    /**
     * Usa a fatura_energia PERSISTIDA quando disponível (coluna adicionada na
     * migration 2026_06_11_210000; o FaturamentoService a grava ao persistir);
     * deriva do CUO apenas para cache antigo (Fase 6, transitório). Como a
     * coluna tem default 0 (linhas antigas recebem 0, nunca NULL), o valor
     * persistido só é considerado autoritativo quando > 0 — para fatura
     * realmente salva como 0 a derivação devolve o mesmo ~0, pois o motor
     * grava CUO = fatura + fioB total. Se não há cache, retorna 0.
     */
    private function faturaEnergiaDerivada(?GeracaoFaturamentoPdf $cache, Usina $usina, float $geracao): float
    {
        if ($cache === null) {
            return 0.0;
        }

        if ($cache->fatura_energia !== null && (float) $cache->fatura_energia > 0.0) {
            return (float) $cache->fatura_energia;
        }

        $fioB = (float) ($usina->comercializacao->fio_b ?? 0);
        $percentualLei = (float) ($usina->comercializacao->percentual_lei ?? 0);
        $fioBTotal = $geracao * $fioB * ($percentualLei / 100);

        return max((float) $cache->cuo - $fioBTotal, 0.0);
    }

    private function somaConsumoFifoKwh(RespostaCalculoMes $resposta): float
    {
        return array_sum(array_map(
            static fn (array $c): float => $c['kwh']->valor(),
            $resposta->resultado->consumosFifo,
        ));
    }

    private function mesesUtilizadosTexto(RespostaCalculoMes $resposta): string
    {
        $origens = array_map(
            fn (array $c): string => $this->labelDeChave((string) $c['origem']),
            $resposta->resultado->consumosFifo,
        );

        return count($origens) ? implode(', ', $origens) : '-';
    }

    private function label(int $ano, int $mes): string
    {
        return Str::ucfirst(self::MESES[$mes]) . '/' . substr((string) $ano, -2);
    }

    private function labelDeChave(string $chave): string
    {
        [$ano, $mes] = array_map('intval', explode('-', $chave));

        return $this->label($ano, $mes);
    }

    private function labelData(string $data): string
    {
        $c = Carbon::parse($data);

        return $this->label((int) $c->year, (int) $c->month);
    }

    private function competenciaChave($competencia): string
    {
        return $competencia instanceof Carbon
            ? $competencia->toDateString()
            : (string) $competencia;
    }

    private static function vencimentoDias(): int
    {
        return 180;
    }
}
