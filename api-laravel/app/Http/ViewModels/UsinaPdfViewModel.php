<?php

declare(strict_types=1);

namespace App\Http\ViewModels;

use App\Application\Faturamento\DTO\RespostaCalculoMes;
use App\Application\Faturamento\FaturamentoService;
use App\Models\DadoConsumo;
use App\Models\DadoConsumoUsina;
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
        $geracaoMensalReal = [];
        $auditoria = [];
        $totalCuo = 0.0;
        $totalValorFinal = 0.0;
        $totalReceitaExpiracao = 0.0;
        $totalGuardadoKwh = 0.0;
        $dadosFaturamento = [];

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
            $geracaoMensalReal[$label] = $resposta->entrada->geracaoBrutaKwh->valor();

            // Auditoria §8: projetada × realizada × faltante + crédito expirado.
            $media = $resposta->entrada->mediaKwh->valor();
            $liquida = $resposta->entrada->geracaoLiquidaKwh->valor();
            $auditoria[$label] = [
                'label' => $label,
                'geracao_real_kwh' => $resposta->entrada->geracaoBrutaKwh->valor(),
                'geracao_liquida_kwh' => $liquida,
                'projetada_media_kwh' => $media,
                'faltante_kwh' => max($media - $liquida, 0.0),
                'credito_expirado_kwh' => $this->somaExpiracaoKwh($resposta),
                'credito_expirado_reais' => $termos->receitaExpiracao->emReais(),
                'meses_utilizados' => $this->mesesUtilizadosTexto($resposta),
            ];

            // Demonstrativo de créditos (substitui a 2ª simulação FIFO removida).
            $creditadoKwh = $this->somaConsumoFifoKwh($resposta);
            $vencimento = $data->copy()->startOfMonth()
                ->addDays(self::vencimentoDias())->format('Y-m-d');
            $dadosFaturamento[$label] = [
                'competencia' => $label,
                'geracao' => $resposta->entrada->geracaoBrutaKwh->valor(),
                'guardado' => $termos->guardadoKwh->valor(),
                'creditado' => $termos->credito->emReais(),
                'creditado_kwh' => $creditadoKwh,
                'pago' => $termos->valorFinal->emReais(),
                'vencimento' => $this->labelData($vencimento),
                'mes_creditado' => $termos->credito->emReais() > 0 ? $label : '-',
                'meses_utilizados' => $this->mesesUtilizadosTexto($resposta),
            ];

            $totalCuo += $termos->cuo->emReais();
            $totalValorFinal += $termos->valorFinal->emReais();
            $totalReceitaExpiracao += $termos->receitaExpiracao->emReais();
            $totalGuardadoKwh += $termos->guardadoKwh->valor();

            if ($mesNum === $mes) {
                $valorFinalMesSelecionado = $termos->valorFinal->emReais();
                $geracaoMesSelecionado = $resposta->entrada->geracaoBrutaKwh->valor();
            }
        }

        $co2Evitado = round($geracaoMesSelecionado * self::FATOR_CO2_KG_POR_KWH, 2);
        $arvores = round($co2Evitado / self::KG_CO2_POR_ARVORE, 2);

        $comercializacao = $usina->comercializacao;
        $dadoGeracao = $usina->dadoGeracao;

        return [
            'usina' => $usina,
            'dadosMensais' => $dadosMensais,
            'valoresGeracao' => $valoresGeracao,
            'nomesMeses' => $labels,
            'maxGeracao' => count($valoresGeracao) ? max($valoresGeracao) : 0,

            'valorReceber' => $valorFinalMesSelecionado,
            'mesAnoSelecionado' => $this->label($ano, $mes),
            'geracaoMes' => $geracaoMesSelecionado,
            'co2Evitado' => $co2Evitado,
            'arvores' => $arvores,

            'dadosFaturamento' => $dadosFaturamento,
            'auditoria' => array_values($auditoria),
            'observacoes' => $observacoes,

            // Totais somam APENAS valores já calculados pelo motor.
            'totalEnergiaReceber' => $totalGuardadoKwh,
            'totalFaturaConcessionaria' => $totalCuo,
            'totalFaturasEmitidas' => $totalValorFinal,
            'totalReceitaExpiracao' => $totalReceitaExpiracao,
            'saldo' => $totalValorFinal,

            // Seção "Parâmetros de Cálculo" (§8) — derivados da usina, sem fórmula.
            'parametros' => [
                'tarifa' => (float) ($comercializacao->valor_kwh ?? 0),
                'valor_fixo' => (float) ($comercializacao->valor_fixo ?? 0),
                'fio_b' => (float) ($comercializacao->fio_b ?? 0),
                'percentual_lei' => (float) ($comercializacao->percentual_lei ?? 0),
                'menor_geracao' => (float) ($dadoGeracao->menor_geracao ?? 0),
                'media' => (float) ($dadoGeracao->media ?? 0),
                'rede' => $usina->rede,
            ],
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
     * Reconstrói a fatura da concessionária a partir do CUO já gravado no cache
     * (Fase 6, transitório). Se não há cache, retorna 0 — o motor calcula com
     * fatura 0 até a próxima persistência gravar o CUO definitivo.
     */
    private function faturaEnergiaDerivada(?GeracaoFaturamentoPdf $cache, Usina $usina, float $geracao): float
    {
        if ($cache === null) {
            return 0.0;
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

    private function somaExpiracaoKwh(RespostaCalculoMes $resposta): float
    {
        return array_sum(array_map(
            static fn (array $e): float => $e['kwh']->valor(),
            $resposta->resultado->expiracoes,
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
