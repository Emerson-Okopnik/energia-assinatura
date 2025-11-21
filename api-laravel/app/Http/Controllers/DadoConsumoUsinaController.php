<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DadoConsumoUsinaService;

class DadoConsumoUsinaController extends Controller
{

    public function __construct(DadoConsumoUsinaService $dadoConsumoUsinaService) {
        $this->dadoConsumoUsinaService = $dadoConsumoUsinaService;
    }

    public function index(): JsonResponse {
        $dados = $this->dadoConsumoUsinaService->findAll();
        return response()->json($dados);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'usi_id' => 'required|integer|exists:usina,usi_id',
            'cli_id' => 'required|integer|exists:cliente,cli_id',
            'dcon_id' => 'required|integer|exists:dados_consumo,dcon_id',
            'ano' => 'required|integer',
        ]);


        $id = $this->dadoConsumoUsinaService->create($data);
        return response()->json(['id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $dados = $this->dadoConsumoUsinaService->findById($id);

        if (!$dados) {
            return response()->json(['message' => 'Dados de consumo da usina não encontrados.'], 404);
        }

        return response()->json($dados);
    }

    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate([
            'usi_id' => 'required|integer|exists:usina,usi_id',
            'cli_id' => 'required|integer|exists:cliente,cli_id',
            'dcon_id' => 'required|integer|exists:dados_consumo,dcon_id',
            'ano' => 'required|integer',
        ]);

        $updated = $this->dadoConsumoUsinaService->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Dados de consumo da usina não encontrados ou não atualizados.'], 404);
        }

        return response()->json(['message' => 'Dados de consumo da usina atualizados com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->dadoConsumoUsinaService->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Dados de consumo da usina não encontrados.'], 404);
        }

        return response()->json(['message' => 'Dados de consumo da usina excluídos com sucesso.']);
    }

    public function byUsinaId(int $usi_id): JsonResponse {
        $dados = $this->dadoConsumoUsinaService->findByUsinaId($usi_id);

        if (empty($dados)) {
            return response()->json(['message' => 'Nenhum dado de consumo encontrado para essa usina.'], 404);
        }

        return response()->json($dados);
    }
}