<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ComercializacaoService;

class ComercializacaoController extends Controller
{
    
    public function __construct(ComercializacaoService $comercializacaoService) {
        $this->comercializacaoService = $comercializacaoService;
    }

    public function index(): JsonResponse {
        $comercializacao = $this->comercializacaoService->findAll();
        return response()->json($comercializacao);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'valor_kwh' => 'required|numeric',
            'valor_fixo' => 'required|numeric',
            'cia_energia' => 'required|string',
            'valor_final_media' => 'required|numeric',
            'previsao_conexao' => 'required|date',
            'data_conexao' => 'required|date',
        ]);

        $id = $this->comercializacaoService->create($data);
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $comercializacao = $this->comercializacaoService->findById($id);

        if (!$comercializacao) {
            return response()->json(['message' => 'Dados da comercializacão não encontrado'], 404);
        }

        return response()->json($comercializacao);
    }

    public function update(Request $request, string $id): JsonResponse {
        $data = $request->validate([
            'valor_kwh' => 'numeric',
            'valor_fixo' => 'numeric',
            'cia_energia' => 'string',
            'valor_final_media' => 'numeric',
            'previsao_conexao' => 'date',
            'data_conexao' => 'date',
        ]);
    
        $updated = $this->comercializacaoService->update((int) $id, $data);
    
        if (!$updated) {
            return response()->json(['message' => 'Dados da comercializacão não encontrados ou não atualizados.'], 404);
        }
    
        return response()->json(['message' => 'Dados da comercializacão atualizado com sucesso.']);
    }

    public function destroy(string $id): JsonResponse {
        $deleted = $this->comercializacaoService->delete((int) $id);
    
        if (!$deleted) {
            return response()->json(['message' => 'Dados da comercializacão não encontrados ou não pôdem ser excluídos.'], 404);
        }
    
        return response()->json(['message' => 'Dados da comercializacão excluídos com sucesso.']);
    }
}
