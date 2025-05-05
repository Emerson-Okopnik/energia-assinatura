<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ValorAcumuladoReservaService;

class ValorAcumuladoReservaController extends Controller {

    public function __construct(ValorAcumuladoReservaService $valorAcumuladoReservaService) {
        $this->valorAcumuladoReservaService = $valorAcumuladoReservaService;
    }


    public function index(): JsonResponse {
        $dados = $this->valorAcumuladoReservaService->findAll();
        return response()->json($dados);
    }

    public function store(): JsonResponse {
        $id = $this->valorAcumuladoReservaService->create(); // cria com defaults
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $dados = $this->valorAcumuladoReservaService->findById($id);

        if (!$dados) {
            return response()->json(['message' => 'Valores acumulados não encontrados.'], 404);
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

        $updated = $this->valorAcumuladoReservaService->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Valores acumulados não atualizados.'], 404);
        }

        return response()->json(['message' => 'Valores acumulados atualizados com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->valorAcumuladoReservaService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Valores acumulados não encontrados.'], 404);
        }

        return response()->json(['message' => 'Valores acumulados excluídos com sucesso.']);
    }
}
