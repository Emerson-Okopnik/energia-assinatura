<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CreditosDistribuidosService;
use Illuminate\Http\JsonResponse;

class CreditosDistribuidosController extends Controller
{
    public function __construct(CreditosDistribuidosService $creditosDistribuidosservice) {
        $this->creditosDistribuidosservice = $creditosDistribuidosservice;
    }

    public function index(): JsonResponse {
        $dados = $this->creditosDistribuidosservice->findAll();
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
        ]);

        $id = $this->creditosDistribuidosservice->create($data);
        return response()->json(['cd_id' => $id], 201);
    }

    public function show(int $id): JsonResponse {
        $registro = $this->creditosDistribuidosservice->findById($id);

        if (!$registro) {
            return response()->json(['message' => 'Crédito não encontrado.'], 404);
        }

        return response()->json($registro);
    }

    public function update(Request $request, int $id): JsonResponse {
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
        ]);

        $updated = $this->creditosDistribuidosservice->update($id, $data);

        if (!$updated) {
            return response()->json(['message' => 'Crédito não encontrado ou não atualizado.'], 404);
        }

        return response()->json(['message' => 'Crédito atualizado com sucesso.']);
    }

    public function destroy(int $id): JsonResponse {
        $deleted = $this->creditosDistribuidosservice->delete($id);

        if (!$deleted) {
            return response()->json(['message' => 'Crédito não encontrado ou não pôde ser excluído.'], 404);
        }

        return response()->json(['message' => 'Crédito excluído com sucesso.']);
    }
}
