<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DadosGeracaoRealService;

class DadosGeracaoRealController extends Controller {
  
  public function __construct(DadosGeracaoRealService $dadosGeracaoRealService) {
    $this->dadosGeracaoRealService = $dadosGeracaoRealService;
  }

  public function index(): JsonResponse {
    $response = $this->dadosGeracaoRealService->findAll();
    return response()->json($response);
  }

  public function store(Request $request): JsonResponse {
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
      'dezembro'=> 'sometimes|numeric',
    ]);

    $id = $this->dadosGeracaoRealService->create($data);
    return response()->json(['id' => $id], 201);
  }

  public function show(int $id): JsonResponse {
     $response = $this->dadosGeracaoRealService->findById($id);

    if (!$response) {
        return response()->json(['message' => 'Dados de Geração Real não encontrado.'], 404);
    }

    return response()->json($response);
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
      'dezembro'=> 'sometimes|numeric',
    ]);

    $updated = $this->dadosGeracaoRealService->update($id, $data);

    if (!$updated) {
        return response()->json(['message' => 'Dados de Geração Real não encontrado ou não atualizado.'], 404);
    }

    return response()->json(['message' => 'Dados de Geração Real atualizado com sucesso.']);
  }

  public function destroy(int $id): JsonResponse {
    $deleted = $this->dadosGeracaoRealService->delete($id);

    if (!$deleted) {
        return response()->json(['message' => 'Dados de Geração Real não encontrado ou não pôde ser excluído.'], 404);
    }

    return response()->json(['message' => 'Dados de Geração Real excluído com sucesso.']);
  }
}
