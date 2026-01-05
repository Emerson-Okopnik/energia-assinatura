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
use Carbon\Carbon;

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
        $ano = is_numeric($anoInformado)
            ? (int) $anoInformado
            : (int) (DadosGeracaoRealUsina::where('usi_id', $id)->max('ano') ?? now()->year);
        $observacoes = (string) $request->query('observacoes', '');

        // Mapa de colunas no banco
        $nomesMeses = [
            1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
            7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro',
        ];
        $colunaMes = $nomesMeses[$mes];

        $anchorData = Carbon::createFromDate($ano, $mes, 1);
        $celescInvoiceBase64 = (string) $request->query('celesc_invoice_base64', '');
        $celescInvoiceId = (string) $request->query('celesc_invoice_id', '');
        $celescBillingPeriod = (string) $request->query('celesc_billing_period', '');

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
            return [
                $dataMes->format('Y-m') => [
                    'ano' => (int) $dataMes->year,
                    'coluna' => $nomesMeses[$dataMes->month],
                    'label' => Str::ucfirst($nomesMeses[$dataMes->month]) . '/' . substr((string) $dataMes->year, -2),
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
        foreach ($meses as $mesNome => $valor) {
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
            $dadosMensais[$mesNome] = [
                'fixo' => $fixo,
                'injetado' => $injetado,
                'creditado' => $creditado,
                'cuo' => $cuo,
                'valor_final' => ($fixo + $injetado + $creditado) - $cuo,
            ];
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

            foreach (array_values($nomesMeses) as $chave) {
                $pagoVal = (float) ($pago?->$chave ?? 0);
                if ($pagoVal > 0) {
                    $dadosFaturamento[\Illuminate\Support\Str::ucfirst($chave)] = [
                        'geracao'   => (float) ($geracaoMensalRealObj?->$chave ?? 0),
                        'guardado'  => (float) ($reserva?->$chave ?? 0),
                        'creditado' => (float) ($creditos?->$chave ?? 0),
                        'pago'      => $pagoVal,
                    ];
                }
            }
        }

        $totalGuardado = (array_sum(array_column($dadosFaturamento, 'guardado')) * $valorKwh);
        $totalPago      = array_sum(array_column($dadosFaturamento, 'pago'));
        $totalCuos      = array_sum(array_column($dadosMensais, 'cuo'));

        $totalEnergiaReceber       = (float) ($faturamento?->valorAcumuladoReserva?->total ?? 0);
        $totalFaturaConcessionaria = $totalCuos;
        $totalFaturasEmitidas      = $totalPago;
        $saldo                     = $totalFaturasEmitidas;

        $chaveMesSelecionado = Str::ucfirst($nomesMeses[$mes]);
        $valorReceber = $dadosFaturamento[$chaveMesSelecionado]['pago'] ?? 0;
        $geracaoMes = $geracaoMensalReal[$mesSelecionadoLabel] ?? 0;

        // UC: prioriza a UC da própria usina, depois a primeira do cliente (se houver)
        $uc = $usina->uc
            ?: optional(optional(optional($usina->cliente)->consumidores)->first())->uc
            ?: 'N/A';

        if (!$celescInvoiceBase64 && $uc) {
            try {
                $celescPayload = [
                    'installation' => $request->query('celesc_installation', $uc),
                    'contract_account' => $request->query('celesc_contract_account'),
                    'billingPeriod' => $celescBillingPeriod,
                    'invoiceId' => $celescInvoiceId ?: $request->query('celesc_invoice_id'),
                    'channelCode' => $request->query('celesc_channel', config('services.celesc.channel', 'ZAW')),
                    'target' => $request->query('celesc_target', 'sap'),
                    'username' => $request->query('celesc_username'),
                    'password' => $request->query('celesc_password'),
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

        $mesAnoSelecionado = ucfirst($nomesMeses[$mes]) . '/' . substr((string) $ano, -2);

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
            //->waitUntilNetworkIdle()
            ->timeout(60)
            ->pdf();

        if ($celescInvoiceBase64) {
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

   /**
   * Anexa a fatura da Celesc (base64 PDF) ao PDF principal.
   */
  private function anexarFaturaCelesc(string $pdfPrincipal, string $celescBase64): string
  {
      $tmpMain = tempnam(sys_get_temp_dir(), 'lider-main-') . '.pdf';
      $tmpCelesc = tempnam(sys_get_temp_dir(), 'celesc-') . '.pdf';
      $tmpMerged = tempnam(sys_get_temp_dir(), 'lider-merged-') . '.pdf';

      file_put_contents($tmpMain, $pdfPrincipal);
      file_put_contents($tmpCelesc, base64_decode($celescBase64));

      $cmd = sprintf(
          'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=%s %s %s 2>&1',
          escapeshellarg($tmpMerged),
          escapeshellarg($tmpMain),
          escapeshellarg($tmpCelesc)
      );

      @exec($cmd, $output, $code);

      $mergedContent = ($code === 0 && is_file($tmpMerged)) ? file_get_contents($tmpMerged) : null;

      @unlink($tmpMain);
      @unlink($tmpCelesc);
      @unlink($tmpMerged);

      if ($mergedContent !== null) {
          return $mergedContent;
      }

      \Log::warning('Falha ao mesclar fatura Celesc no PDF; retornando PDF principal.', [
          'exit_code' => $code ?? null,
          'output' => $output ?? [],
      ]);

      return $pdfPrincipal;
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
