<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DadoGeracaoService;

class DadoGeracaoController extends Controller {

    public function __construct(DadoGeracaoService $dadoGeracaoService) {
        $this->dadoGeracaoService = $dadoGeracaoService;
    }


    public function index(): JsonResponse {
        $dados = $this->dadoGeracaoService->findAll();
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
            'menor_geracao' => 'required|numeric',
        ]);

        $id = $this->dadoGeracaoService->create($data);
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $dados = $this->dadoGeracaoService->findById($id);

        if (!$dados) {
            return response()->json(['message' => 'Dados de geração não encontrados.'], 404);
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
            'menor_geracao' => 'sometimes|numeric',
        ]);

        $updated = $this->dadoGeracaoService->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Dados de geração não atualizados.'], 404);
        }

        return response()->json(['message' => 'Dados de geração atualizados com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->dadoGeracaoService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Dados de geração não encontrados.'], 404);
        }

        return response()->json(['message' => 'Dados de geração excluídos com sucesso.']);
    }
}
