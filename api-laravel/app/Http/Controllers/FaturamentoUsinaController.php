<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FaturamentoUsinaService;

class FaturamentoUsinaController extends Controller {
   
    public function __construct(FaturamentoUsinaService $faturamentoUsinaService) {
        $this->faturamentoUsinaService = $faturamentoUsinaService;
    }


    public function index(): JsonResponse {
        $dados = $this->faturamentoUsinaService->findAll();
        return response()->json($dados);
    }

    public function store(): JsonResponse {
        $id = $this->faturamentoUsinaService->create(); // cria com defaults
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $dados = $this->faturamentoUsinaService->findById($id);

        if (!$dados) {
            return response()->json(['message' => 'Faturamento da Usina não encontrados.'], 404);
        }
 
        return response()->json($dados);
    }

    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate([
            'janeiro' => 'sometimes|numeric',
            'fevereiro' => 'sometimes|numeric',
            'marco' => 'sometimes|numeric',
            'abril' => 'sometimes|numeric',
            'maio' => 'sometimes|numeric',
            'junho' => 'sometimes|numeric',
            'julho' => 'sometimes|numeric',
            'agosto' => 'sometimes|numeric',
            'setembro' => 'sometimes|numeric',
            'outubro' => 'sometimes|numeric',
            'novembro' => 'sometimes|numeric',
            'dezembro' => 'sometimes|numeric',
        ]);

        $updated = $this->faturamentoUsinaService->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Faturamento da Usina não atualizados.'], 404);
        }

        return response()->json(['message' => 'Faturamento da Usina atualizados com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->faturamentoUsinaService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Faturamento da Usina não encontrados.'], 404);
        }

        return response()->json(['message' => 'Faturamento da Usina excluídos com sucesso.']);
    }
}
