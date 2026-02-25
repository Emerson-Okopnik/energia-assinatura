<?php

namespace App\Http\Controllers;

use App\Models\{HistoricoEstorno, Usina};
use App\Services\EstornoGeracaoService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Log;

class EstornoGeracaoController extends Controller
{
    private EstornoGeracaoService $service;

    public function __construct(EstornoGeracaoService $service)
    {
        $this->service = $service;
    }

    public function estornar(Request $request, int $usi_id, int $ano, int $mes): JsonResponse
    {
        $usina = Usina::find($usi_id);
        if (!$usina) {
            return response()->json(['error' => 'Usina não encontrada'], 404);
        }

        try {
            $this->service->estornar($usina, $ano, $mes, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao estornar faturamento', ['usina_id' => $usi_id, 'exception' => $e]);
            return response()->json(['error' => 'Erro interno'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function historico(Request $request, int $usi_id): JsonResponse
    {
        $registros = HistoricoEstorno::where('usi_id', $usi_id)
            ->with([
                'usuario:id,name',
                'usuarioEstorno:id,name',
            ])
            ->orderByDesc('he_id')
            ->get()
            ->map(fn ($r) => [
                'he_id'        => $r->he_id,
                'ano'          => $r->ano,
                'mes'          => $r->mes,
                'mes_nome'     => $r->mes_nome,
                'revertido_em' => $r->revertido_em?->toDateTimeString(),
                'lancado_por'  => $r->usuario?->name,
                'revertido_por' => $r->usuarioEstorno?->name,
                'created_at'   => $r->created_at->toDateTimeString(),
            ]);

        return response()->json($registros);
    }

    public function ultimoRevertivel(Request $request, int $usi_id): JsonResponse
    {
        $snapshot = $this->service->ultimoRevertivel($usi_id);

        if (!$snapshot) {
            return response()->json(null);
        }

        return response()->json([
            'he_id'    => $snapshot->he_id,
            'ano'      => $snapshot->ano,
            'mes'      => $snapshot->mes,
            'mes_nome' => $snapshot->mes_nome,
        ]);
    }
}
