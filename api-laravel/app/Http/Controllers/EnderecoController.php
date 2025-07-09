<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\EnderecoService;

class EnderecoController extends Controller
{

    public function __construct(EnderecoService $enderecoService) {
        $this->enderecoService = $enderecoService;
    }

    public function index(): JsonResponse {
        $endereco = $this->enderecoService->findAll();
        return response()->json($endereco);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'rua' => 'string',
            'cidade' => 'string',
            'bairro' => 'string',
            'estado' => 'string|max:2',
            'complemento' => 'string',
            'cep' => 'string|max:10',
            'numero' => 'integer',
        ]);

        $id = $this->enderecoService->create($data);
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $endereco = $this->enderecoService->findById($id);

        if (!$endereco) {
            return response()->json(['message' => 'Endereço não encontrada'], 404);
        }

        return response()->json($endereco);
    }

    public function update(Request $request, string $id): JsonResponse {
        $data = $request->validate([
            'rua' => 'string',
            'cidade' => 'string',
            'bairro' => 'string',
            'estado' => 'string|max:2',
            'complemento' => 'string',
            'cep' => 'string|max:10',
            'numero' => 'integer',
        ]);
    
        $updated = $this->enderecoService->update((int) $id, $data);
    
        if (!$updated) {
            return response()->json(['message' => 'Endereço não encontrado ou não atualizado.'], 404);
        }
    
        return response()->json(['message' => 'Endereço atualizado com sucesso.']);
    }

    public function destroy(string $id): JsonResponse {
        $deleted = $this->enderecoService->delete((int) $id);
    
        if (!$deleted) {
            return response()->json(['message' => 'Endereço não encontrado ou não pôde ser excluído.'], 404);
        }
    
        return response()->json(['message' => 'Endereço excluído com sucesso.']);
    }
}
