<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\VendedorService;

class VendedorController extends Controller {

    public function __construct(VendedorService $vendedorService) {
        $this->vendedorService = $vendedorService;
    }

    public function index(): JsonResponse {
        $vendedores = $this->vendedorService->findAll();
        return response()->json($vendedores);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'nome' => 'required|string',
            'patente' => 'nullable|string',
        ]);

        $id = $this->vendedorService->create($data);
        return response()->json(['ven_id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $vendedor = $this->vendedorService->findById($id);

        if (!$vendedor) {
            return response()->json(['message' => 'Vendedor não encontrado.'], 404);
        }

        return response()->json($vendedor);
    }

    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate([
            'nome' => 'required|string',
            'patente' => 'nullable|string',
        ]);

        $updated = $this->vendedorService->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Vendedor não encontrado ou não atualizado.'], 404);
        }

        return response()->json(['message' => 'Vendedor atualizado com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->vendedorService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Vendedor não encontrado ou não pôde ser excluído.'], 404);
        }

        return response()->json(['message' => 'Vendedor excluído com sucesso.']);
    }
}
