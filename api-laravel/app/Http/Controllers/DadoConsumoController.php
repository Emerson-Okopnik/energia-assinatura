<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DadoConsumoService;

class DadoConsumoController extends Controller {

    public function __construct(DadoConsumoService $dadoConsumoService) {
        $this->dadoConsumoService = $dadoConsumoService;
    }

    public function index(): JsonResponse {
        $dados = $this->dadoConsumoService->findAll();
        return response()->json($dados);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'janeiro' => 'required|numeric',
            'fevereiro' => 'required|numeric',
            'marco' => 'required|numeric',
            'abril' => 'required|numeric',
            'maio' => 'required|numeric',
            'junho' => 'required|numeric',
            'julho' => 'required|numeric',
            'agosto' => 'required|numeric',
            'setembro' => 'required|numeric',
            'outubro' => 'required|numeric',
            'novembro' => 'required|numeric',
            'dezembro' => 'required|numeric',
            'media' => 'required|numeric',
        ]);

        $id = $this->dadoConsumoService->create($data);
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $dados = $this->dadoConsumoService->findById($id);

        if (!$dados) {
            return response()->json(['message' => 'Dados de consumo não encontrados.'], 404);
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
            'media' => 'sometimes|numeric',
        ]);

        $updated = $this->dadoConsumoService->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Dados de consumo não atualizados.'], 404);
        }

        return response()->json(['message' => 'Dados de consumo atualizados com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->dadoConsumoService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Dados de consumo não encontrados.'], 404);
        }

        return response()->json(['message' => 'Dados de consumo excluídos com sucesso.']);
    }
}
