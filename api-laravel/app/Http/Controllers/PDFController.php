<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Usina;
use App\Models\UsinaConsumidor;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Response;
use App\Models\CreditosDistribuidosUsina;
use App\Models\DadosGeracaoRealUsina;
use Carbon\Carbon;

class PDFController extends Controller {

  public function gerarUsinaPDF(Request $request, $id) {

    $usina = Usina::with('cliente', 'dadoGeracao', 'comercializacao')->findOrFail($id);

    // Pega mês/ano da query ou usa atuais
    $mes = intval($request->query('mes')) ?: Carbon::now()->month;
    $ano = intval($request->query('ano')) ?: Carbon::now()->year;

    $geracao = $usina->dadoGeracao;

    // Mapeia o nome do mês em português para a coluna do banco
    $nomesMeses = [
        1 => 'janeiro',
        2 => 'fevereiro',
        3 => 'marco',
        4 => 'abril',
        5 => 'maio',
        6 => 'junho',
        7 => 'julho',
        8 => 'agosto',
        9 => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    ];

    $colunaMes = $nomesMeses[$mes];

    // Geração do mês informado
    $geracaoMes = floatval($geracao->$colunaMes ?? 0);
    $mesAnoSelecionado = ucfirst($nomesMeses[$mes]) . '/' . substr($ano, -2);
    $observacoes = $request->query('observacoes', '');

    $valor_kwh = $usina->comercializacao->valor_kwh;
    $valor_fixo = $geracao->menor_geracao * $valor_kwh;
    $media = $geracao->media;
    $menor = $geracao->menor_geracao;
    $faturaEnergia = 100;
    $percentualLei = 45;
    $valorFinalFioB = 0.13 * ($percentualLei / 100);

    $fixoSelecionado = $valor_fixo;
    $injetadoSelecionado = ($geracaoMes > $media)
        ? ($media - $menor) * $valor_kwh
        : ($geracaoMes - $menor) * $valor_kwh;
    $creditadoSelecionado = ($geracaoMes < $media)
        ? ($media - $geracaoMes) * $valor_kwh
        : 0;
    $cuoSelecionado = -1 * ($faturaEnergia + ($geracaoMes * $valorFinalFioB));
    $valorReceber = $fixoSelecionado + $injetadoSelecionado + $creditadoSelecionado + $cuoSelecionado;

    $meses = [
            'Janeiro' => $geracao->janeiro,
            'Fevereiro' => $geracao->fevereiro,
            'Marco' => $geracao->marco,
            'Abril' => $geracao->abril,
            'Maio' => $geracao->maio,
            'Junho' => $geracao->junho,
            'Julho' => $geracao->julho,
            'Agosto' => $geracao->agosto,
            'Setembro' => $geracao->setembro,
            'Outubro' => $geracao->outubro,
            'Novembro' => $geracao->novembro,
            'Dezembro' => $geracao->dezembro,
    ];

    $dadosMensais = [];
    $maxGeracao = max(array_map('floatval', array_values($meses)));

    foreach ($meses as $mesNome => $valor) {
        $fixo = $valor_fixo;
        $injetado = ($valor > $media) ? ($media - $menor) * $valor_kwh : ($valor - $menor) * $valor_kwh;
        $creditado = ($valor < $media) ? ($media - $valor) * $valor_kwh : 0;
        $cuo = -1 * ($faturaEnergia + ($valor * $valorFinalFioB));
        $valor_final = $fixo + $injetado + $creditado + $cuo;

        $dadosMensais[$mesNome] = [
            'fixo' => $fixo,
            'injetado' => $injetado,
            'creditado' => $creditado,
            'cuo' => $cuo,
            'valor_final' => $valor_final,
        ];
    }

    $ano = now()->year;
    $faturamento = CreditosDistribuidosUsina::where('usi_id', $id)
        ->where('ano', $ano)
        ->with(['creditosDistribuidos', 'valorAcumuladoReserva', 'faturamentoUsina'])
        ->first();

    $geracaoReal = DadosGeracaoRealUsina::where('usi_id', $id)
        ->where('ano', $ano)
        ->with('dadosGeracaoReal')
        ->first();

    $dadosFaturamento = [];
    if ($faturamento && $geracaoReal) {
        $creditos = $faturamento->creditosDistribuidos;
        $reserva = $faturamento->valorAcumuladoReserva;
        $pago    = $faturamento->faturamentoUsina;
        $geracao = $geracaoReal->dadosGeracaoReal;

        $mesesKeys = [
            'janeiro','fevereiro','marco','abril','maio','junho',
            'julho','agosto','setembro','outubro','novembro','dezembro'
        ];

        foreach ($mesesKeys as $chave) {
            if ($pago->$chave > 0) {
                $dadosFaturamento[Str::ucfirst($chave)] = [
                    'geracao'  => $geracao->$chave ?? 0,
                    'guardado' => $reserva->$chave ?? 0,
                    'creditado'=> $creditos->$chave ?? 0,
                    'pago'     => $pago->$chave ?? 0,
                ];
            }
        }
    }

    $totalGuardado = array_sum(array_column($dadosFaturamento, 'guardado'));
    $totalCreditado = array_sum(array_column($dadosFaturamento, 'creditado'));
    $totalPago      = array_sum(array_column($dadosFaturamento, 'pago'));

    $totalEnergiaReceber = $totalGuardado * $valor_kwh;
    $totalFaturaConcessionaria = $totalCreditado;
    $totalFaturasEmitidas = $totalPago;
    $saldo = $totalEnergiaReceber - $totalFaturaConcessionaria - $totalFaturasEmitidas;

    $uc = optional($usina->cliente->consumidores)->uc ?? 'N/A';
    $loadAsset = function (string $relativePath) {
        $fullPath = public_path($relativePath);

        if (!file_exists($fullPath)) {
            abort(404, "Arquivo público {$relativePath} não encontrado.");
        }

        $base64 = base64_encode(file_get_contents($fullPath));
        $mime = mime_content_type($fullPath);

        return "data:$mime;base64,$base64";
    };

    $logoDataUri = $loadAsset('img/logo-consorcio-lider-energy.png');
    $iconeSolDataUri = $loadAsset('img/sol.png');
    $iconeRelogioDataUri = $loadAsset('img/relogio.png');
    $iconeWebDataUri = $loadAsset('img/web.png');
    $iconeWppDataUri = $loadAsset('img/whatsapp.png');
    $iconeEmailDataUri = $loadAsset('img/email.png');
    $iconeCo2DataUri = $loadAsset('img/icone-co2.png');
    $iconeArvoreDataUri = $loadAsset('img/icone-arvore.png');
    $iconeInfoDataUri = $loadAsset('img/icone-info.png');
    $iconeDinheiroDataUri = $loadAsset('img/dinheiro.png');
    $iconeLampadaDataUri = $loadAsset('img/lampada.png');
    $iconeInstagramDataUri = $loadAsset('img/instagram.png');
    $iconeLinkedinDataUri = $loadAsset('img/linkedin.png');

    $html = view('usina', [
        'usina' => $usina,
        'dadosMensais' => $dadosMensais,
        'valoresGeracao' => array_map('floatval', array_values($meses)),
        'nomesMeses' => array_keys($meses),
        'maxGeracao' => $maxGeracao,
        'logo' => $logoDataUri,
        'iconeSol' => $iconeSolDataUri,
        'iconeRelogio' => $iconeRelogioDataUri,
        'iconeWeb' => $iconeWebDataUri,
        'iconeWpp' => $iconeWppDataUri,
        'iconeEmail' => $iconeEmailDataUri,
        'iconeCo2' => $iconeCo2DataUri,
        'iconeArvore' => $iconeArvoreDataUri,
        'iconeInfo' => $iconeInfoDataUri,
        'iconeDinheiro' => $iconeDinheiroDataUri,
        'iconeLampada' => $iconeLampadaDataUri,
        'iconeInstagram' => $iconeInstagramDataUri,
        'iconeLinkedin' => $iconeLinkedinDataUri,
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
    ])->render();

    $pdf = Browsershot::html($html)
        ->format('A4')
        ->showBackground()
        ->deviceScaleFactor(2)
        ->waitUntilNetworkIdle()
        ->timeout(60)
        ->pdf();

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="grafico_usina.pdf"');
  }
    
  public function gerarConsumidoresPDF($id) {
  
    $usina = Usina::with('cliente')->findOrFail($id);
    $consumidores = UsinaConsumidor::where('usi_id', $id)
        ->with(['consumidor.cliente.endereco', 'consumidor.dado_consumo'])
        ->get();

    $html = view('listagem-consumidores', [
        'usina' => $usina,
        'consumidores' => $consumidores,
    ])->render();

    $pdf = Browsershot::html($html)
        ->format('A4')
        ->margins(10, 10, 10, 10)
        ->showBackground()
        ->deviceScaleFactor(2)
        ->waitUntilNetworkIdle()
        ->timeout(60)
        ->landscape()
        ->pdf();

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="consumidores_usina_' . $id . '.pdf"');
  }
}