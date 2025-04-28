<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CreditosDistribuidosUsinaService;
use Illuminate\Http\JsonResponse;

class CreditosDistribuidosUsinaController extends Controller {

    public function __construct(CreditosDistribuidosUsinaService $creditosDistribuidosUsinaservice) {
        $this->creditosDistribuidosUsinaservice = $creditosDistribuidosUsinaservice;
    }

    public function index(): JsonResponse {
        $dados = $this->creditosDistribuidosUsinaservice->findAll();
        return response()->json($dados);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'usi_id' => 'required|integer|exists:usina,usi_id',
            'cli_id' => 'required|integer|exists:cliente,cli_id',
            'cd_id' => 'required|integer|exists:creditos_distribuidos,cd_id',
            'ano' => 'required|integer',
        ]);

        $id = $this->creditosDistribuidosUsinaservice->create($data);
        return response()->json(['cdu_id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $registro = $this->creditosDistribuidosUsinaservice->findById($id);

        if (!$registro) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        return response()->json($registro);
    }

    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate([
            'usi_id' => 'required|integer|exists:usina,usi_id',
            'cli_id' => 'required|integer|exists:cliente,cli_id',
            'cd_id' => 'required|integer|exists:creditos_distribuidos,cd_id',
            'ano' => 'required|integer',
        ]);

        $updated = $this->creditosDistribuidosUsinaservice->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Registro não encontrado ou não atualizado.'], 404);
        }

        return response()->json(['message' => 'Registro atualizado com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->creditosDistribuidosUsinaservice->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Registro não encontrado ou não pôde ser excluído.'], 404);
        }

        return response()->json(['message' => 'Registro excluído com sucesso.']);
    }
}
