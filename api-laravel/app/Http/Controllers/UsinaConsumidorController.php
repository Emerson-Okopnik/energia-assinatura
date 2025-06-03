<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\UsinaConsumidorService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateUsinaConsumidorRequest;


class UsinaConsumidorController extends Controller
{
    public function __construct(UsinaConsumidorService $usinaConsumidorService) {
        $this->usinaConsumidorService = $usinaConsumidorService;
    }

    public function index(): JsonResponse {
        $usinas = $this->usinaConsumidorService->findAll();
        return response()->json($usinas);
    }

    public function store(Request $request): JsonResponse {
      $data = $request->validate([
        'usi_id' => 'required|integer|exists:usina,usi_id',
        'cli_id' => 'required|integer|exists:cliente,cli_id',
        'con_ids' => 'required|array|min:1',
        'con_ids.*' => 'integer|exists:consumidor,con_id',
      ]);

      $inserted = $this->usinaConsumidorService->createMany($data);

      return response()->json([
        'message' => "$inserted consumidores vinculados à usina com sucesso.",
        'count' => $inserted
      ], 201);
    }

    public function show(int $usi_id): JsonResponse {
        $consumidores = $this->usinaConsumidorService->findByUsinaId($usi_id);

        if (!$consumidores) {
            return response()->json(['message' => 'Relação Usina e Consumidor não encontrada.'], 404);
        }

        return response()->json($consumidores);
    }

    public function update(UpdateUsinaConsumidorRequest $request, int $id): JsonResponse {
        $data = $request->validated();
    
        $result = $this->usinaConsumidorService->update($id, $data);
    
        if (!$result) {
            return response()->json(['message' => 'Relação Usina e Consumidor não encontrado ou não atualizado.'], 404);
        }
        
        return response()->json(['message' => 'Relação Usina e Consumidor atualizado com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->usinaConsumidorService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Relação Usina e Consumidor não encontrada ou não pôde ser excluída.'], 404);
        }

        return response()->json(['message' => 'Relação Usina e Consumidor excluída com sucesso.']);
    }

    public function destroyVinculo(int $usi_id, int $con_id): JsonResponse {
        $deleted = $this->usinaConsumidorService->deleteVinculo($usi_id, $con_id);
    
        if (!$deleted) {
            return response()->json(['message' => 'Vínculo não encontrado.'], 404);
        }
    
        return response()->json(['message' => 'Consumidor desvinculado da usina com sucesso.']);
    }
    
}
