<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ConsumidorService;
use Illuminate\Http\JsonResponse;

class ConsumidorController extends Controller {

    public function __construct(ConsumidorService $consumidorService) {
        $this->consumidorService = $consumidorService;
    }

    public function index(): JsonResponse {
        $consumidores = $this->consumidorService->findAll();
        return response()->json($consumidores);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'cli_id' => 'required|integer|exists:cliente,cli_id',
            'dcon_id' => 'required|integer|exists:dados_consumo,dcon_id',
            'ven_id' => 'required|integer|exists:vendedor,ven_id',
            'cia_energia' => 'required|string',
            'data_entrega' => 'nullable|date',
            'status' => 'nullable|string|max:255',
            'alocacao' => 'nullable|string|max:255',
        ]);

        $id = $this->consumidorService->create($data);
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $consumidor = $this->consumidorService->findById($id);

        if (!$consumidor) {
            return response()->json(['message' => 'Consumidor não encontrado.'], 404);
        }

        return response()->json($consumidor);
    }

    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate([
            'cli_id' => 'required|integer|exists:cliente,cli_id',
            'dcon_id' => 'required|integer|exists:dados_consumo,dcon_id',
            'ven_id' => 'required|integer|exists:vendedor,ven_id',
            'cia_energia' => 'required|string',
            'data_entrega' => 'nullable|date',
            'status' => 'nullable|string|max:255',
            'alocacao' => 'nullable|string|max:255',
        ]);

        $updated = $this->consumidorService->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Consumidor não encontrado ou não atualizado.'], 404);
        }

        return response()->json(['message' => 'Consumidor atualizado com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->consumidorService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Consumidor não encontrado ou não pôde ser excluído.'], 404);
        }

        return response()->json(['message' => 'Consumidor excluído com sucesso.']);
    }

    public function consumidoresNaoVinculados(): JsonResponse {
        $consumidores = $this->consumidorService->buscarNaoVinculados();

        return response()->json($consumidores);
    }

}
