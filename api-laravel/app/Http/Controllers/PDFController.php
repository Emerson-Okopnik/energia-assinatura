<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Usina;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Response;


class PDFController extends Controller
{
    public function gerarUsinaPDF($id)
    {
        $usina = Usina::with('cliente', 'dadoGeracao', 'comercializacao')->findOrFail($id);
    
        // Geração dos dados como no seu código atual
        $geracao = $usina->dadoGeracao;
        $valor_kwh = $usina->comercializacao->valor_kwh;
        $valor_fixo = $usina->dadoGeracao->menor_geracao * $valor_kwh;
        $media = $usina->dadoGeracao->media;
        $menor = $usina->dadoGeracao->menor_geracao;
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
    
        foreach ($meses as $mes => $valor) {
            $fixo = $valor_fixo;
            $injetado = ($valor > $media) ? ($media - $menor) * $valor_kwh : ($valor - $menor) * $valor_kwh;
            $creditado = ($valor < $media) ? ($media - $valor) * $valor_kwh : 0;
            $cuo = -1 * ($faturaEnergia + ($valor * $valorFinalFioB));
            $valor_final = $fixo + $injetado + $creditado + $cuo;
    
            $dadosMensais[$mes] = [
                'fixo' => $fixo,
                'injetado' => $injetado,
                'creditado' => $creditado,
                'cuo' => $cuo,
                'valor_final' => $valor_final,
            ];
        }
    
        $html = view('usina', [
            'usina' => $usina,
            'dadosMensais' => $dadosMensais,
            'valoresGeracao' => array_map('floatval', array_values($meses)),
            'nomesMeses' => array_keys($meses),
            'maxGeracao' => $maxGeracao,
        ])->render();

        $pdf = Browsershot::html($html)
        ->format('A4')
        ->margins(10, 10, 10, 10)
        ->showBackground()
        ->deviceScaleFactor(2)
        ->waitUntilNetworkIdle() // Espera os scripts carregarem
        ->timeout(60)
        ->pdf();
    
        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="grafico_usina.pdf"');
    }
    
}