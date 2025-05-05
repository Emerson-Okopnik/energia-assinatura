<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Models\Usina;

class PDFController extends Controller
{
    
    public function gerarUsinaPDF($id)
    {
        $usina = Usina::with('cliente', 'dadoGeracao', 'comercializacao')->findOrFail($id);
    
        $geracao = $usina->dadoGeracao;
        $valor_kwh = $usina->comercializacao->valor_kwh;
        $valor_fixo = $usina->dadoGeracao->menor_geracao * $valor_kwh;
        $media = $usina->dadoGeracao->media;
        $menor = $usina->dadoGeracao->menor_geracao;
    
        $faturaEnergia = 100; // você pode substituir por parâmetro se quiser
        $percentualLei = 45;  // idem
        $valorFinalFioB = 0.13 * ($percentualLei / 100); // também pode vir da requisição
    
        $meses = [
            'Janeiro' => $geracao->janeiro,
            'Fevereiro' => $geracao->fevereiro,
            'Março' => $geracao->marco,
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
    
        $pdf = Pdf::loadView('usina', compact('usina', 'dadosMensais'));
    
        return $pdf->download('usina_' . Str::slug($usina->cliente->nome) . '.pdf');
    }
}
