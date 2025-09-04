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

    $logoPath = public_path('img/logo-consorcio-lider-energy.png');
    $logoBase64 = base64_encode(file_get_contents($logoPath));
    $logoMime = mime_content_type($logoPath);
    $logoDataUri = "data:$logoMime;base64,$logoBase64";

    $iconeSolPath = public_path('img/sol.png');
    $iconeSolBase64 = base64_encode(file_get_contents($iconeSolPath));
    $iconeSolMime = mime_content_type($iconeSolPath);
    $iconeSolDataUri = "data:$iconeSolMime;base64,$iconeSolBase64";

    $iconeRelogioPath = public_path('img/relogio.png');
    $iconeRelogioBase64 = base64_encode(file_get_contents($iconeRelogioPath));
    $iconeRelogioMime = mime_content_type($iconeRelogioPath);
    $iconeRelogioDataUri = "data:$iconeRelogioMime;base64,$iconeRelogioBase64";

    $iconeWebPath = public_path('img/web.png');
    $iconeWebBase64 = base64_encode(file_get_contents($iconeWebPath));
    $iconeWebMime = mime_content_type($iconeWebPath);
    $iconeWebDataUri = "data:$iconeWebMime;base64,$iconeWebBase64";

    $iconeWppPath = public_path('img/whatsapp.png');
    $iconeWppBase64 = base64_encode(file_get_contents($iconeWppPath));
    $iconeWppMime = mime_content_type($iconeWppPath);
    $iconeWppDataUri = "data:$iconeWppMime;base64,$iconeWppBase64";

    $iconeEmailPath = public_path('img/email.png');
    $iconeEmailBase64 = base64_encode(file_get_contents($iconeEmailPath));
    $iconeEmailMime = mime_content_type($iconeEmailPath);
    $iconeEmailDataUri = "data:$iconeEmailMime;base64,$iconeEmailBase64";

    $iconeCo2Path = public_path('img/icone-co2.png');
    $iconeCo2Base64 = base64_encode(file_get_contents($iconeCo2Path));
    $iconeCo2Mime = mime_content_type($iconeCo2Path);
    $iconeCo2DataUri = "data:$iconeCo2Mime;base64,$iconeCo2Base64";

    $iconeArvorePath = public_path('img/icone-Arvore.png');
    $iconeArvoreBase64 = base64_encode(file_get_contents($iconeArvorePath));
    $iconeArvoreMime = mime_content_type($iconeArvorePath);
    $iconeArvoreDataUri = "data:$iconeArvoreMime;base64,$iconeArvoreBase64";

    $iconeInfoPath = public_path('img/icone-info.png');
    $iconeInfoBase64 = base64_encode(file_get_contents($iconeInfoPath));
    $iconeInfoMime = mime_content_type($iconeInfoPath);
    $iconeInfoDataUri = "data:$iconeInfoMime;base64,$iconeInfoBase64";

    $iconeDinheiroPath = public_path('img/dinheiro.png');
    $iconeDinheiroBase64 = base64_encode(file_get_contents($iconeDinheiroPath));
    $iconeDinheiroMime = mime_content_type($iconeDinheiroPath);
    $iconeDinheiroDataUri = "data:$iconeDinheiroMime;base64,$iconeDinheiroBase64";

    $iconeLampadaPath = public_path('img/lampada.png');
    $iconeLampadaBase64 = base64_encode(file_get_contents($iconeLampadaPath));
    $iconeLampadaMime = mime_content_type($iconeLampadaPath);
    $iconeLampadaDataUri = "data:$iconeLampadaMime;base64,$iconeLampadaBase64";

    $iconeInstagramPath = public_path('img/instagram.png');
    $iconeInstagramBase64 = base64_encode(file_get_contents($iconeInstagramPath));
    $iconeInstagramMime = mime_content_type($iconeInstagramPath);
    $iconeInstagramDataUri = "data:$iconeInstagramMime;base64,$iconeInstagramBase64";

    $iconeLinkedinPath = public_path('img/linkedin.png');
    $iconeLinkedinBase64 = base64_encode(file_get_contents($iconeLinkedinPath));
    $iconeLinkedinMime = mime_content_type($iconeLinkedinPath);
    $iconeLinkedinDataUri = "data:$iconeLinkedinMime;base64,$iconeLinkedinBase64";

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