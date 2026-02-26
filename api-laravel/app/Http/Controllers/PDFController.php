<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CelescApiService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use App\Models\Usina;
use App\Models\UsinaConsumidor;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Response;
use App\Models\CreditosDistribuidosUsina;
use App\Models\DadosGeracaoRealUsina;
use App\Models\DemonstrativoCreditosPdf;
use App\Models\GeracaoFaturamentoPdf;
use Carbon\Carbon;
use Symfony\Component\Process\Process;

class PDFController extends Controller {

  private const TRANSPARENT_PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
  private CelescApiService $celescApiService;

  public function __construct(CelescApiService $celescApiService)
  {
      $this->celescApiService = $celescApiService;
  }

  public function gerarUsinaPDF(Request $request, $id) {
    try {
        $usina = Usina::select([
            'usi_id', 'cli_id', 'dger_id', 'com_id', 'ven_id', 'uc', 'rede',
            'data_limite_troca_titularidade', 'data_ass_contrato', 'status', 'andamento_processo'
        ])
        ->with([
            'cliente' => function ($query) {
                $query->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'email', 'end_id')
                    ->with(['consumidores' => function ($q) {
                        $q->select('con_id', 'cli_id', 'uc');
                    }]);
            },
            'dadoGeracao' => function ($query) {
                $query->select('dger_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
                    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'media', 'menor_geracao');
            },
            'comercializacao' => function ($query) {
                $query->select(
                    'com_id',
                    'valor_kwh',
                    'valor_fixo',
                    'cia_energia',
                    'valor_final_media',
                    'previsao_conexao',
                    'data_conexao',
                    'fio_b',
                    'percentual_lei'
                );
            }
        ])
        ->findOrFail($id);

        // Guard clauses para erros óbvios de dados ausentes
        if (!$usina->comercializacao) {
            return response()->json(['message' => 'Usina sem dados de comercialização (valor_kwh).'], 422);
        }
        if (!$usina->dadoGeracao) {
            return response()->json(['message' => 'Usina sem dados de geração cadastrados.'], 422);
        }

        // Mês/Ano válidos (1..12)
        $mes = (int) $request->query('mes', now()->month);
        if ($mes < 1 || $mes > 12) { $mes = now()->month; }
        $anoInformado = $request->query('ano');
        $ano = is_numeric($anoInformado) ? (int) $anoInformado : (int) (DadosGeracaoRealUsina::where('usi_id', $id)->max('ano') ?? now()->year);
        $observacoes = (string) $request->query('observacoes', '');

        // Mapa de colunas no banco
        $nomesMeses = [
            1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho', 7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro',
        ];
        $colunaMes = $nomesMeses[$mes];

        $anchorData = Carbon::createFromDate($ano, $mes, 1);
        $formatarCompetencia = function (Carbon $data) use ($nomesMeses): string {
            return Str::ucfirst($nomesMeses[$data->month]) . '/' . substr((string) $data->year, -2);
        };
        $celescInvoiceBase64 = '';
        $celescInvoiceId = '';
        $celescBillingPeriod = $anchorData->format('Y/m');

        // Meses: atual + últimos 11 (12 no total), mais antigos primeiro
        $datasRange = collect();
        for ($i = 11; $i >= 0; $i--) {
            $datasRange->push($anchorData->copy()->subMonths($i));
        }

        $anosBusca = $datasRange->map->year->unique()->values();

        $geracoesReais = DadosGeracaoRealUsina::select(['dgru_id', 'dgr_id', 'usi_id', 'cli_id', 'ano'])
            ->where('usi_id', $id)
            ->whereIn('ano', $anosBusca->toArray())
            ->with(['DadosGeracaoReal' => function ($query) {
                $query->select('dgr_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
                    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro');
            }])
            ->get()
            ->keyBy('ano');

        $janelaMeses = $datasRange->mapWithKeys(function ($dataMes) use ($nomesMeses) {
            $competencia = $dataMes->copy()->startOfMonth()->toDateString();
            return [
                $dataMes->format('Y-m') => [
                    'ano' => (int) $dataMes->year,
                    'coluna' => $nomesMeses[$dataMes->month],
                    'mes' => (int) $dataMes->month,
                    'label' => Str::ucfirst($nomesMeses[$dataMes->month]) . '/' . substr((string) $dataMes->year, -2),
                    'competencia' => $dataMes->format('Y-m-01'),
                ],
            ];
        });

        $geracaoRealAnoSelecionado = $geracoesReais->get($ano);

        $faturamento = CreditosDistribuidosUsina::select([
            'cdu_id', 'cd_id', 'usi_id', 'cli_id', 'fa_id', 'var_id', 'ano'
        ])
        ->where('usi_id', $id)
        ->where('ano', $ano)
        ->with([
            'creditosDistribuidos' => function ($query) {
                $query->select('cd_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
                    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro');
            },
            'valorAcumuladoReserva' => function ($query) {
                $query->select('var_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
                    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'total');
            },
            'faturamentoUsina' => function ($query) {
                $query->select('fa_id', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
                    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro');
            }
        ])
        ->first();

        $geracaoMensalReal = [];
        $meses = [];
        $mesesInfo = [];
        
        foreach ($janelaMeses as $chave => $infoMes) {
            if ((int) $infoMes['ano'] !== $ano || (int) $infoMes['mes'] > $mes) {
                continue;
            }
            $registroAno = $geracoesReais->get($infoMes['ano']);
            $valor = optional($registroAno?->DadosGeracaoReal)->{$infoMes['coluna']};

            if ($valor === null || (float) $valor === 0.0) {
                continue;
            }

            $valorFloat = (float) $valor;
            $geracaoMensalReal[$infoMes['label']] = $valorFloat;
            $meses[$infoMes['label']] = $valorFloat;
            $mesesInfo[$infoMes['label']] = $infoMes;
        }

        $valoresGeracao = array_values($meses);
        $maxGeracao = count($valoresGeracao) ? max($valoresGeracao) : 0;

        $fioB = (float) ($usina->comercializacao->fio_b ?? 0);
        $percentualLei = (float) ($usina->comercializacao->percentual_lei ?? 0);
        $faturaEnergia = (float) $request->query('fatura', 0);
        $adicionalCuo = (float) $request->query('adicional_cuo', 0);
        $valorFinalFioB = $fioB * ($percentualLei / 100);
        $valorKwh = (float) ($usina->comercializacao->valor_kwh ?? 0);
        $mediaGeracao = (float) ($usina->dadoGeracao->media ?? 0);
        $menorGeracao = (float) ($usina->dadoGeracao->menor_geracao ?? 0);

        // Tabela mensal de valores (apenas meses com geração informada)
        $mesSelecionadoLabel = ucfirst($nomesMeses[$mes]) . '/' . substr((string) $ano, -2);
        $dadosMensais = [];
        $competencias = [];
        foreach ($meses as $mesNome => $valor) {
            $competencias[] = $mesesInfo[$mesNome]['competencia'];
        }

        $competencias = array_values(array_unique($competencias));

        $dadosPersistidos = count($competencias)
            ? GeracaoFaturamentoPdf::where('usi_id', $id)
                ->whereIn('competencia', $competencias)
                ->get()
                ->keyBy(function ($registro) {
                    return $registro->competencia instanceof Carbon
                        ? $registro->competencia->toDateString()
                        : (string) $registro->competencia;
                })
            : collect();

        $novosRegistros = [];
        foreach ($meses as $mesNome => $valor) {
            $competencia = $mesesInfo[$mesNome]['competencia'];
            $registroPersistido = $dadosPersistidos->get($competencia);

            if ($registroPersistido) {
                $dadosMensais[$mesNome] = [
                    'geracao_kwh' => (float) $registroPersistido->geracao_kwh,
                    'fixo' => (float) $registroPersistido->valor_fixo,
                    'injetado' => (float) $registroPersistido->injetado,
                    'creditado' => (float) $registroPersistido->creditado,
                    'cuo' => (float) $registroPersistido->cuo,
                    'valor_final' => (float) $registroPersistido->valor_final,
                ];
                continue;
            }

            $fixo           = (float) ($usina->comercializacao->valor_fixo ?? 0);
            $injetado       = ($valor >= $mediaGeracao) ? ($mediaGeracao - $menorGeracao) * $valorKwh : ($valor - $menorGeracao) * $valorKwh;
            $valorBaseCuo   = $faturaEnergia + ($valor * $valorFinalFioB);
            $cuo            = ($mesesInfo[$mesNome]['coluna'] ?? null) === $colunaMes ? $valorBaseCuo + $adicionalCuo : $valorBaseCuo;

            $creditado = 0;
            if ($valor < $mediaGeracao) {
                $reservaTotal = (float) ($faturamento?->valorAcumuladoReserva?->total ?? 0);
                if ($reservaTotal > 0) {
                    $creditado = ($mediaGeracao - $valor) * $valorKwh;
                }
            }
            //$cuo       =  ($faturaEnergia + ($fioB * $valor * ($percentualLei / 100)));
            $valorFinal = ($fixo + $injetado + $creditado) - $cuo;

            $dadosMensais[$mesNome] = [
                'geracao_kwh' => $valor,
                'fixo' => $fixo,
                'injetado' => $injetado,
                'creditado' => $creditado,
                'cuo' => $cuo,
                'valor_final' => $valorFinal,
            ];
            
            $novosRegistros[] = [
                'usi_id' => $id,
                'competencia' => $competencia,
                'geracao_kwh' => $valor,
                'valor_fixo' => $fixo,
                'injetado' => $injetado,
                'creditado' => $creditado,
                'cuo' => $cuo,
                'valor_final' => $valorFinal,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (count($novosRegistros)) {
            GeracaoFaturamentoPdf::insertOrIgnore($novosRegistros);
        }

        $dadosFaturamento = [];
        if (
            $faturamento && $geracaoRealAnoSelecionado &&
            $faturamento->creditosDistribuidos &&
            $faturamento->valorAcumuladoReserva &&
            $faturamento->faturamentoUsina &&
            $geracaoRealAnoSelecionado->DadosGeracaoReal
        ) {
            $creditos = $faturamento->creditosDistribuidos;
            $reserva  = $faturamento->valorAcumuladoReserva;
            $pago     = $faturamento->faturamentoUsina;
            $geracaoMensalRealObj = $geracaoRealAnoSelecionado->DadosGeracaoReal;
            $mesesUtilizadosPorMes = [];
            $mesesExpiradosPorMes = [];
            $reservaSimulada = array_fill_keys(array_values($nomesMeses), 0.0);

            // Simula o uso da reserva para listar os meses utilizados e os que venceram (180 dias).
            foreach ($nomesMeses as $indiceMes => $chave) {
                if ($indiceMes > $mes) {
                    continue;
                }
                $pagoVal = (float) ($pago?->$chave ?? 0);
                if ($pagoVal <= 0) {
                    continue;
                }

                $referencia = Carbon::createFromDate($ano, $indiceMes, 1)->endOfMonth();
                $mesesExpiradosNoMes = [];
                foreach ($nomesMeses as $num => $nome) {
                    $saldo = (float) ($reservaSimulada[$nome] ?? 0);
                    if ($saldo <= 0) {
                        continue;
                    }
                    $dataMes = Carbon::createFromDate($ano, $num, 1)->endOfMonth();
                    if ($dataMes->lessThan($referencia) && $dataMes->diffInDays($referencia) > 180) {
                        $reservaSimulada[$nome] = 0.0;
                        $mesesExpiradosNoMes[] = $formatarCompetencia(Carbon::createFromDate($ano, $num, 1));
                    }
                }
                $mesesExpiradosPorMes[$chave] = $mesesExpiradosNoMes;

                $geracaoMes = (float) ($geracaoMensalRealObj?->$chave ?? 0);
                if ($mediaGeracao <= 0) {
                    $mesesUtilizadosPorMes[$chave] = [];
                    continue;
                }

                if ($geracaoMes >= $mediaGeracao) {
                    $valorGuardado = $geracaoMes - $mediaGeracao;
                    if ($valorGuardado > 0) {
                        $reservaSimulada[$chave] = ($reservaSimulada[$chave] ?? 0) + $valorGuardado;
                    }
                    $mesesUtilizadosPorMes[$chave] = [];
                    continue;
                }

                $faltante = $mediaGeracao - $geracaoMes;
                $energiaParaDescontar = min($faltante, array_sum($reservaSimulada));
                $mesesUsados = [];

                if ($energiaParaDescontar > 0) {
                    foreach ($nomesMeses as $num => $nome) {
                        $saldo = (float) ($reservaSimulada[$nome] ?? 0);
                        if ($saldo <= 0) {
                            continue;
                        }
                        $retirar = min($saldo, $energiaParaDescontar);
                        if ($retirar > 0) {
                            $reservaSimulada[$nome] = $saldo - $retirar;
                            $energiaParaDescontar -= $retirar;
                            $mesesUsados[] = $formatarCompetencia(Carbon::createFromDate($ano, $num, 1));
                        }
                        if ($energiaParaDescontar <= 0) {
                            break;
                        }
                    }
                }

                $mesesUtilizadosPorMes[$chave] = $mesesUsados;
            }

            $demonstrativosUpsert = [];

            foreach ($nomesMeses as $indiceMes => $chave) {
                if ($indiceMes > $mes) {
                    continue;
                }
                $pagoVal = (float) ($pago?->$chave ?? 0);
                if ($pagoVal > 0) {
                    $dataBaseCredito = Carbon::createFromDate($ano, $indiceMes, 1)->startOfMonth();
                    $dataVencimento = $dataBaseCredito->copy()->addDays(210);
                    $creditado = (float) ($creditos?->$chave ?? 0);
                    $creditadoKwh = $valorKwh > 0 ? ($creditado / $valorKwh) : 0;
                    $guardadoMes = (float) ($reserva?->$chave ?? 0);
                    $competencia = $dataBaseCredito->toDateString();
                    $vencimentoLabel = $formatarCompetencia($dataVencimento);
                    $mesesExpirados = $mesesExpiradosPorMes[$chave] ?? [];
                    $mesesListados = array_values(array_unique($mesesExpirados));
                    $mesesUtilizados = count($mesesListados) ? implode(', ', $mesesListados) : '-';

                    $demonstrativosUpsert[] = [
                        'usi_id' => $id,
                        'competencia' => $competencia,
                        'vencimento' => $dataVencimento->toDateString(),
                        'guardado_kwh' => $guardadoMes,
                        'creditado_kwh' => $creditadoKwh,
                        'meses_utilizados' => $mesesUtilizados === '-' ? null : $mesesUtilizados,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $mesCreditado = $creditado > 0
                        ? $formatarCompetencia($dataBaseCredito)
                        : '-';

                    $dadosFaturamento[$formatarCompetencia($dataBaseCredito)] = [
                        'competencia'      => $formatarCompetencia($dataBaseCredito),
                        'geracao'          => (float) ($geracaoMensalRealObj?->$chave ?? 0),
                        'guardado'         => $guardadoMes,
                        'creditado'        => $creditado,
                        'creditado_kwh'    => $creditadoKwh,
                        'pago'             => $pagoVal,
                        'vencimento'       => $vencimentoLabel,
                        'mes_creditado'    => $mesCreditado,
                        'meses_utilizados' => $mesesUtilizados,
                    ];
                }
            }

            if (count($demonstrativosUpsert)) {
                DemonstrativoCreditosPdf::upsert(
                    $demonstrativosUpsert,
                    ['usi_id', 'competencia'],
                    ['vencimento', 'guardado_kwh', 'creditado_kwh', 'meses_utilizados', 'updated_at']
                );
            }
        }

        $totalGuardado = (array_sum(array_column($dadosFaturamento, 'guardado')) * $valorKwh);
        $totalPago      = array_sum(array_column($dadosFaturamento, 'pago'));
        $totalCuos      = array_sum(array_column($dadosMensais, 'cuo'));

        $totalEnergiaReceber       = (float) ($faturamento?->valorAcumuladoReserva?->total ?? 0);
        $totalFaturaConcessionaria = $totalCuos;
        $totalFaturasEmitidas      = $totalPago;
        $saldo                     = $totalFaturasEmitidas;

        $chaveMesSelecionado = $formatarCompetencia($anchorData);
        $valorReceber = $dadosMensais[$mesSelecionadoLabel]['valor_final']
            ?? ($dadosFaturamento[$chaveMesSelecionado]['pago'] ?? 0);
        $geracaoMes = $geracaoMensalReal[$mesSelecionadoLabel] ?? 0;

        // UC: prioriza a UC da própria usina, depois a primeira do cliente (se houver)
        $uc = $usina->uc ?: optional(optional(optional($usina->cliente)->consumidores)->first())->uc ?: 'N/A';

        if (!$celescInvoiceBase64 && $uc) {
            try {
                $celescPayload = [
                    'installation' => $uc,
                    'billingPeriod' => $celescBillingPeriod
                ];

                $celescResponse = $this->celescApiService->gerarSegundaVia($celescPayload);
                $celescInvoiceBase64 = (string) ($celescResponse['invoiceBase64'] ?? '');
                $celescInvoiceId = (string) ($celescResponse['invoiceId'] ?? $celescInvoiceId);
            } catch (\Throwable $e) {
                \Log::warning('Celesc invoice not attached to PDF', [
                    'usina_id' => $id,
                    'installation' => $uc,
                    'mensagem' => $e->getMessage(),
                ]);
            }
        }

        // Imagens inline (sem depender de fileinfo/mime_content_type)
        $imagensInline = [
            'logo' => 'img/logo-consorcio-lider-energy.png',
            'iconeSol' => 'img/sol.png',
            'iconeRelogio' => 'img/relogio.png',
            'iconeWeb' => 'img/web.png',
            'iconeWpp' => 'img/whatsapp.png',
            'iconeEmail' => 'img/email.png',
            'iconeCo2' => 'img/icone-co2.png',
            'iconeArvore' => 'img/icone-arvore.png',
            'iconeInfo' => 'img/icone-info.png',
            'iconeDinheiro' => 'img/dinheiro.png',
            'iconeLampada' => 'img/lampada.png',
            'iconeInstagram' => 'img/instagram.png',
            'iconeLinkedin' => 'img/linkedin.png',
        ];

        $imagensInline = collect($imagensInline)->mapWithKeys(function ($path, $chave) {
            return [$chave => $this->inlinePublicImage($path)];
        });

        $mesAnoSelecionado = $formatarCompetencia($anchorData);

        $html = View::file(resource_path('views/usina.blade.php'), [
            'usina' => $usina,
            'dadosMensais' => $dadosMensais,
            'valoresGeracao' => $valoresGeracao,
            'nomesMeses' => array_keys($meses),
            'maxGeracao' => $maxGeracao,
            'logo' => $imagensInline['logo'],
            'iconeSol' => $imagensInline['iconeSol'],
            'iconeRelogio' => $imagensInline['iconeRelogio'],
            'iconeWeb' => $imagensInline['iconeWeb'],
            'iconeWpp' => $imagensInline['iconeWpp'],
            'iconeEmail' => $imagensInline['iconeEmail'],
            'iconeCo2' => $imagensInline['iconeCo2'],
            'iconeArvore' => $imagensInline['iconeArvore'],
            'iconeInfo' => $imagensInline['iconeInfo'],
            'iconeDinheiro' => $imagensInline['iconeDinheiro'],
            'iconeLampada' => $imagensInline['iconeLampada'],
            'iconeInstagram' => $imagensInline['iconeInstagram'],
            'iconeLinkedin' => $imagensInline['iconeLinkedin'],
            'valorReceber' => $valorReceber,
            'mesAnoSelecionado' => $mesAnoSelecionado,
            'geracaoMes' => $geracaoMes,
            'dadosFaturamento' => $dadosFaturamento,
            'observacoes' => $observacoes,
            'totalEnergiaReceber' => $totalEnergiaReceber,
            'totalFaturaConcessionaria' => $totalFaturaConcessionaria,
            'totalFaturasEmitidas' => $totalFaturasEmitidas,
            'saldo' => $saldo,
            'uc' => $uc,
            'geracaoMensalReal' => $geracaoMensalReal,
            'celescInvoiceBase64' => $celescInvoiceBase64,
            'celescInvoiceId' => $celescInvoiceId,
        ])->render();

        $pdf = $this->configureBrowsershot(Browsershot::html($html))
            ->format('A4')
            ->showBackground()
            ->deviceScaleFactor(1)
            ->waitUntilNetworkIdle()
            ->setDelay(1500) // aguarda render do pdf.js
            ->timeout(90)
            ->pdf();

        if (!empty($celescInvoiceBase64)) {
            $pdf = $this->anexarFaturaCelesc($pdf, $celescInvoiceBase64);
        }
        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="grafico_usina.pdf"');

    } catch (\Throwable $e) {
        \Log::error('Erro ao gerarUsinaPDF', [
            'usina_id' => $id,
            'mensagem' => $e->getMessage(),
            'trace'    => $e->getTraceAsString(),
        ]);
        return response()->json(['message' => 'Erro ao gerar PDF da usina. '.$e->getMessage()], 500);
    }
  }

  //Gera um Data URI de uma imagem no public/ sem depender do fileinfo.

  private function inlinePublicImage(string $relativePath): string {
    $path = public_path($relativePath);
    if (is_file($path)) {
        $mime = mime_content_type($path) ?: 'image/png';
        $contents = file_get_contents($path);

        if ($contents === false) {
            return self::TRANSPARENT_PIXEL;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
    return self::TRANSPARENT_PIXEL;
  }

private function anexarFaturaCelesc(string $pdfPrincipal, string $celescBase64): string
{
    $pdfCelesc = $this->decodePdfBase64($celescBase64);

    return $pdfCelesc
        ? ($this->mergePdfsWithPdfLib($pdfPrincipal, $pdfCelesc) ?? $pdfPrincipal)
        : $pdfPrincipal;
}

/**
 * Decodifica base64 (puro ou data URI) e retorna binário do PDF, ou null.
 */
private function decodePdfBase64(?string $base64): ?string
{
    $base64 = trim((string) $base64);
    if ($base64 === '') return null;

    $base64 = preg_replace('#^data:.*;base64,#i', '', $base64) ?? $base64;
    $base64 = preg_replace('/\s+/', '', trim($base64, "\"'")) ?? '';

    $bin = base64_decode($base64, true) ?: base64_decode(strtr($base64, '-_', '+/'), true);

    return ($bin && strncmp($bin, '%PDF-', 5) === 0) ? $bin : null;
}

private function mergePdfsWithPdfLib(string $pdfA, string $pdfB): ?string
{
    $tmpA   = tempnam(sys_get_temp_dir(), 'pdf-a-');
    $tmpB   = tempnam(sys_get_temp_dir(), 'pdf-b-');
    $tmpOut = tempnam(sys_get_temp_dir(), 'pdf-out-');

    if (!$tmpA || !$tmpB || !$tmpOut) return null;

    try {
        file_put_contents($tmpA, $pdfA);
        file_put_contents($tmpB, $pdfB);

        // Ajuste se necessário (no Windows às vezes precisa caminho completo do node.exe)
        $node   = config('services.node.binary', 'node');
        $script = base_path('resources/node/merge-pdf.cjs');

        if (!is_file($script)) {
            \Log::warning('Script de merge PDF não encontrado.', ['script' => $script]);
            return null;
        }

        $args = [$node, $script, $tmpA, $tmpB, $tmpOut];

        $exitCode = null;
        $out = '';

        if (class_exists(Process::class) && function_exists('proc_open')) {
            $p = new Process($args, base_path()); // cwd = raiz (para achar node_modules)
            $p->setTimeout(60);
            $p->run();
            $exitCode = $p->getExitCode();
            $out = trim($p->getErrorOutput() ?: $p->getOutput());
        } elseif (function_exists('exec')) {
            $cmd = implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
            @exec($cmd, $lines, $code);
            $exitCode = $code;
            $out = is_array($lines) ? implode("\n", array_slice($lines, 0, 120)) : '';
        } else {
            \Log::warning('Nem proc_open nem exec disponíveis; não dá para rodar o merge via Node.');
            return null;
        }

        if ($exitCode === 0 && is_file($tmpOut) && filesize($tmpOut) > 0) {
            $merged = file_get_contents($tmpOut);
            return ($merged !== false && $merged !== '') ? $merged : null;
        }

        \Log::warning('Falha ao mesclar PDFs via pdf-lib (node).', [
            'exit_code' => $exitCode,
            'output' => $out ? substr($out, 0, 2000) : null,
        ]);

        return null;
    } finally {
        @unlink($tmpA);
        @unlink($tmpB);
        @unlink($tmpOut);
    }
}


/**
 * Importa todas as páginas de um PDF binário para o PDF de saída.
 */
private function appendPdfPages(Fpdi $out, string $pdfBin): void
{
    $pageCount = $out->setSourceFile(StreamReader::createByString($pdfBin));

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $tpl = $out->importPage($pageNo);
        $size = $out->getTemplateSize($tpl);

        $out->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $out->useTemplate($tpl);
    }
}


  public function gerarConsumidoresPDF($id) {
  
    $usina = Usina::select('usi_id', 'cli_id', 'dger_id')
        ->with([
            'cliente' => function ($query) {
                $query->select('cli_id', 'nome');
            },
            'dadoGeracao' => function ($query) {
                $query->select('dger_id', 'media');
            }
        ])
        ->findOrFail($id);
        
    $consumidores = UsinaConsumidor::select('usic_id', 'usi_id', 'con_id', 'cli_id')
        ->where('usi_id', $id)
        ->with([
            'consumidor' => function ($query) {
                $query->select('con_id', 'cli_id', 'dcon_id', 'cia_energia', 'uc', 'rede', 'status')
                    ->with([
                        'cliente' => function ($q) {
                            $q->select('cli_id', 'nome', 'cpf_cnpj', 'telefone', 'end_id')
                                ->with(['endereco' => function ($eq) {
                                    $eq->select('end_id', 'rua', 'numero', 'cidade', 'estado');
                                }]);
                        },
                        'dado_consumo' => function ($q) {
                            $q->select('dcon_id', 'media');
                        }
                    ]);
            }
        ])
        ->get();

    $html = View::file(resource_path('views/listagem-consumidores.blade.php'), [
            'usina' => $usina,
        'consumidores' => $consumidores,
    ])->render();

    $pdf = $this->configureBrowsershot(Browsershot::html($html))
        ->format('A4')
        ->margins(10, 10, 10, 10)
        ->showBackground()
        ->deviceScaleFactor(1)
        //->waitUntilNetworkIdle()
        ->timeout(60)
        ->landscape()
        ->pdf();

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="consumidores_usina_' . $id . '.pdf"');
  }

  private function configureBrowsershot(Browsershot $browsershot): Browsershot {
    $chromePath = config('services.browsershot.chrome_path');
    if (!empty($chromePath)) {
      $browsershot->setChromePath($chromePath);
    }

    $nodePath = config('services.browsershot.node_path');
    if (!empty($nodePath)) {
      $browsershot->setNodeBinary($nodePath);
    }

    if (config('services.browsershot.disable_sandbox')) {
      $browsershot->addChromiumArguments(['--no-sandbox', '--disable-setuid-sandbox']);
    }

    return $browsershot;
  }
}
