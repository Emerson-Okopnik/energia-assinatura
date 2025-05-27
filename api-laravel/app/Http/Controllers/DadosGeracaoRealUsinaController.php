<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\DadosGeracaoRealUsinaService;

class DadosGeracaoRealUsinaController extends Controller
{
  
  public function __construct(DadosGeracaoRealUsinaService $dadosGeracaoRealUsina) {
    $this->dadosGeracaoRealUsina = $dadosGeracaoRealUsina;
  }

  public function index(): JsonResponse {
    $response = $this->dadosGeracaoRealUsina->findAll();
    return response()->json($response);
  }

  public function store(Request $request): JsonResponse {
    $data = $request->validate([
      'usi_id' => 'required|integer|exists:usina,usi_id',
      'cli_id' => 'required|integer|exists:cliente,cli_id',
      'dgr_id' => 'required|integer|exists:dados_geracao_real,dgr_id',
      'ano' => 'required|integer',
    ]);

    $id = $this->dadosGeracaoRealUsina->create($data);
    return response()->json(['id' => $id], 201);
  }

  public function show(int $id): JsonResponse {
     $response = $this->dadosGeracaoRealUsina->findById($id);

    if (!$response) {
        return response()->json(['message' => 'Dados de Geração Real da Usina não encontrado.'], 404);
    }

    return response()->json($response);
  }

  public function update(Request $request, int $id): JsonResponse {
  
    $data = $request->validate([
      'usi_id' => 'required|integer|exists:usina,usi_id',
      'cli_id' => 'required|integer|exists:cliente,cli_id',
      'dgr_id' => 'required|integer|exists:dados_geracao_real,dgr_id',
      'ano' => 'required|integer',
    ]);

    $updated = $this->dadosGeracaoRealUsina->update($id, $data);

    if (!$updated) {
        return response()->json(['message' => 'Dados de Geração Real da Usina não encontrado ou não atualizado.'], 404);
    }

    return response()->json(['message' => 'Dados de Geração Real da Usina atualizado com sucesso.']);
  }

  public function destroy(int $id): JsonResponse {
    $deleted = $this->dadosGeracaoRealUsina->delete($id);

    if (!$deleted) {
        return response()->json(['message' => 'Dados de Geração Real da Usina não encontrado ou não pôde ser excluído.'], 404);
    }

    return response()->json(['message' => 'Dados de Geração Real da Usina excluído com sucesso.']);
  }

  public function byUsinaId(int $usi_id): JsonResponse {
    $dados = $this->dadosGeracaoRealUsina->findByUsinaId($usi_id);

    if (empty($dados)) {
      return response()->json(['message' => 'Nenhum dado de geração real encontrado para essa usina.'], 404);
    }

    return response()->json($dados);
  }
}
