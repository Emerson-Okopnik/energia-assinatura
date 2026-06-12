<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Faturamento\FaturamentoService;
use App\Http\ViewModels\UsinaPdfViewModel;
use App\Services\CelescApiService;
use Illuminate\Support\Facades\View;
use App\Models\Usina;
use App\Models\UsinaConsumidor;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Response;
use App\Models\DadosGeracaoRealUsina;
use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;

class PDFController extends Controller {

  private const TRANSPARENT_PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';

  public function __construct(
      private CelescApiService $celescApiService,
      private FaturamentoService $faturamentoService,
  ) {
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
        $observacoes = Str::limit((string) $request->query('observacoes', ''), 280);

        $anchorData = Carbon::createFromDate($ano, $mes, 1);
        $celescInvoiceBase64 = '';
        $celescInvoiceId = '';
        $celescBillingPeriod = $anchorData->format('Y/m');

        // Fase 6: o PDF LÊ do motor único (FaturamentoService). Toda a montagem do
        // ViewModel — termos e demonstrativo — vive em UsinaPdfViewModel,
        // que apenas orquestra o serviço. ZERO recálculo aqui (DRY).
        $viewModel = (new UsinaPdfViewModel($this->faturamentoService))
            ->montar($usina, $ano, $mes, $observacoes);

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

        // Imagens inline como data URI (mime via fileinfo quando disponível, com fallback)
        $imagensInline = [
            'logo' => 'img/logo-lider-energy-color.png',
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

        // Dados de cálculo vêm PRONTOS do motor (UsinaPdfViewModel); aqui o
        // controller só agrega o que é de apresentação (imagens, UC, Celesc).
        $dados = array_merge($viewModel, [
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
            'uc' => $uc,
            'celescInvoiceBase64' => $celescInvoiceBase64,
            'celescInvoiceId' => $celescInvoiceId,
            'chartJs' => $this->publicJsContents('vendor/chart.umd.js'),
            'datalabelsJs' => $this->publicJsContents('vendor/chartjs-plugin-datalabels.min.js'),
            'fontFaceCss' => $this->buildFontFaceCss(),
        ]);

        $html = View::file(resource_path('views/usina.blade.php'), $dados)->render();

        $pdf = $this->configureBrowsershot(Browsershot::html($html))
            ->format('A4')
            ->showBackground()
            ->deviceScaleFactor(1)
            // Sem CDN/Google Fonts no HTML: nada de waitUntilNetworkIdle/setDelay.
            // Contrato: o Blade novo seta window.chartRendered em try/finally ao terminar o gráfico.
            ->waitForFunction('window.chartRendered === true')
            ->timeout(30)
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

  /** Gera um Data URI de qualquer asset em public/ (imagem, fonte). */
  private function inlineAsset(string $relativePath, string $mimeFallback = 'application/octet-stream'): string {
    $contents = $this->publicFileContents($relativePath);
    if ($contents === null) {
        return self::TRANSPARENT_PIXEL;
    }
    $mime = (function_exists('mime_content_type') ? mime_content_type(public_path($relativePath)) : null) ?: $mimeFallback;
    return 'data:' . $mime . ';base64,' . base64_encode($contents);
  }

  /** Conteúdo bruto de um arquivo em public/, ou null se ausente/ilegível. */
  private function publicFileContents(string $relativePath): ?string {
    $path = public_path($relativePath);
    if (!is_file($path)) {
        return null;
    }
    $contents = file_get_contents($path);
    return $contents === false ? null : $contents;
  }

  /** JS de public/ para inline no Blade; loga warning se o arquivo estiver ausente. */
  private function publicJsContents(string $relativePath): string {
    $contents = $this->publicFileContents($relativePath);
    if ($contents === null) {
        \Log::warning('Asset JS do PDF ausente', ['path' => $relativePath]);
        return '';
    }
    return $contents;
  }

  private function inlinePublicImage(string $relativePath): string {
    return $this->inlineAsset($relativePath, 'image/png');
  }

  /** CSS @font-face com woff2 embutido (zero rede no Browsershot). */
  private function buildFontFaceCss(): string {
    $fontes = [
        ['Nunito', 400, 'fonts/nunito-400.woff2'],
        ['Nunito', 600, 'fonts/nunito-600.woff2'],
        ['Nunito', 700, 'fonts/nunito-700.woff2'],
        ['Nunito', 800, 'fonts/nunito-800.woff2'],
        ['JetBrains Mono', 400, 'fonts/jetbrains-mono-400.woff2'],
        ['JetBrains Mono', 700, 'fonts/jetbrains-mono-700.woff2'],
    ];

    return collect($fontes)->map(function (array $f) {
        [$family, $weight, $path] = $f;
        $contents = $this->publicFileContents($path);
        if ($contents === null) {
            \Log::warning('Fonte do PDF ausente', ['path' => $path]);
            return null;
        }
        $uri = 'data:font/woff2;base64,' . base64_encode($contents);
        return "@font-face{font-family:'$family';font-weight:$weight;font-style:normal;src:url($uri) format('woff2');}";
    })->filter()->implode("\n");
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
      // noSandbox() injeta --no-sandbox corretamente. NÃO usar addChromiumArguments(['--no-sandbox'])
      // pois o Browsershot prefixa '--' em cada arg, gerando '----no-sandbox' (ignorado pelo Chrome).
      $browsershot->noSandbox();
    }

    return $browsershot;
  }
}
