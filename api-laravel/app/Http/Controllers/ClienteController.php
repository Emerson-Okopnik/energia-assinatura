<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller {

    public function __construct(ClienteService $clienteService) {
        $this->clienteService = $clienteService;
    }
    
    public function index(): JsonResponse {
        $clientes = $this->clienteService->findAll();
        return response()->json($clientes);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'nome' => 'required|string',
            'cpf_cnpj' => 'required|string',
            'telefone' => 'nullable|string',
            'email' => 'nullable|email',
            'end_id' => 'required|integer|exists:endereco,end_id',
        ]);

        $clienteId = $this->clienteService->create($data);
        return response()->json(['id' => $clienteId], 201);
    }

    public function show(int $id): JsonResponse {
        $cliente = $this->clienteService->findById($id);
    
        if (!$cliente) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }
    
        return response()->json($cliente);
    }

    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate([
            'nome' => 'string',
            'cpf_cnpj' => 'string',
            'telefone' => 'nullable|string',
            'email' => 'nullable|email',
            'end_id' => 'integer|exists:endereco,end_id',
        ]);
    
        $updated = $this->clienteService->update($id, $data);
    
        if (!$updated) {
            return response()->json(['message' => 'Cliente não encontrado ou não atualizado.'], 404);
        }
    
        return response()->json(['message' => 'Cliente atualizado com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->clienteService->delete($id);
    
        if (!$deleted) {
            return response()->json(['message' => 'Cliente não encontrado ou não pôde ser excluído.'], 404);
        }
    
        return response()->json(['message' => 'Cliente excluído com sucesso.']);
    }
}
