<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UsinaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UsinaController extends Controller {

  public function __construct(UsinaService $usinaService) {
    $this->usinaService = $usinaService;
  }

  public function index(Request $request): JsonResponse {
    $shouldPaginate = $request->boolean('paginate')
      || $request->has('page')
      || $request->has('per_page');

    if (!$shouldPaginate) {
      $usinas = $this->usinaService->findAll();
      return response()->json($usinas);
    }

    $validated = $request->validate([
      'page' => 'sometimes|integer|min:1',
      'per_page' => 'sometimes|integer|min:1|max:100',
    ]);

    $page = (int) ($validated['page'] ?? 1);
    $perPage = (int) ($validated['per_page'] ?? 10);

    $usinas = $this->usinaService->findPaginated($perPage, $page);
    return $this->paginatedResponse($usinas);
  }

  public function indexPaginated(Request $request): JsonResponse {
    $validated = $request->validate([
      'page' => 'sometimes|integer|min:1',
      'per_page' => 'sometimes|integer|min:1|max:100',
    ]);

    $page = (int) ($validated['page'] ?? 1);
    $perPage = (int) ($validated['per_page'] ?? 10);

    $usinas = $this->usinaService->findPaginated($perPage, $page);
    return $this->paginatedResponse($usinas);
  }

  public function indexListagem(Request $request): JsonResponse {
    $validated = $request->validate([
      'page' => 'sometimes|integer|min:1',
      'per_page' => 'sometimes|integer|min:1|max:100',
    ]);

    $page = (int) ($validated['page'] ?? 1);
    $perPage = (int) ($validated['per_page'] ?? 10);

    $usinas = $this->usinaService->findListagemPaginated($perPage, $page);
    return $this->paginatedResponse($usinas);
  }

  public function buscarPorNomeCliente(Request $request): JsonResponse {
    $validated = $request->validate([
      'nome_cliente' => 'required|string|min:2|max:255',
      'page' => 'sometimes|integer|min:1',
      'per_page' => 'sometimes|integer|min:1|max:100',
    ]);

    $nomeCliente = trim($validated['nome_cliente']);
    $page = (int) ($validated['page'] ?? 1);
    $perPage = (int) ($validated['per_page'] ?? 10);

    $usinas = $this->usinaService->searchByClienteNomePaginated(
      $nomeCliente,
      $perPage,
      $page
    );

    return $this->paginatedResponse($usinas);
  }

  public function store(Request $request): JsonResponse {
    $data = $request->validate([
      'cli_id' => 'required|integer|exists:cliente,cli_id',
      'dger_id' => 'required|integer|exists:dados_geracao,dger_id',
      'com_id' => 'required|integer|exists:comercializacao,com_id',
      'ven_id' => 'required|integer|exists:vendedor,ven_id',
      'uc' => 'nullable|string',
      'rede' => 'required|string|in:Trifásico,Bifásico,Monofásico',
      'data_limite_troca_titularidade' => 'nullable|date',
      'data_ass_contrato' => 'nullable|date',
      'status' => 'nullable|string|max:255',
      'andamento_processo' => 'nullable|string|max:255',      
    ]);
    $id = $this->usinaService->create($data);
    return response()->json(['id' => $id], 201);
  }

  public function show(int $id): JsonResponse {
    $usina = $this->usinaService->findById($id);

    if (!$usina) {
      return response()->json(['message' => 'Usina não encontrada.'], 404);
    }
    return response()->json($usina);
  }

  public function update(Request $request, int $id): JsonResponse {
    $data = $request->validate([
      'cli_id' => 'required|integer|exists:cliente,cli_id',
      'dger_id' => 'required|integer|exists:dados_geracao,dger_id',
      'com_id' => 'required|integer|exists:comercializacao,com_id',
      'ven_id' => 'required|integer|exists:vendedor,ven_id',
      'uc' => 'nullable|string',
      'rede' => 'required|string|in:Trifásico,Bifásico,Monofásico',
      'data_limite_troca_titularidade' => 'nullable|date',
      'data_ass_contrato' => 'nullable|date',
      'status' => 'nullable|string|max:255',
      'andamento_processo' => 'nullable|string|max:255',
    ]);

    $updated = $this->usinaService->update($id, $data);

    if (!$updated) {
      return response()->json(['message' => 'Usina não encontrada ou não atualizada.'], 404);
    }

    return response()->json(['message' => 'Usina atualizada com sucesso.']);
  }

  public function destroy(int $id): JsonResponse {
    $deleted = $this->usinaService->delete($id);

    if (!$deleted) {
      return response()->json(['message' => 'Usina não encontrada ou não pôde ser excluída.'], 404);
    }

    return response()->json(['message' => 'Usina excluída com sucesso.']);
  }

  public function usinasNaoVinculadas(): JsonResponse {
    $usinas = $this->usinaService->buscarNaoVinculados();
    return response()->json($usinas);
  }

  public function listarAnos(int $id): JsonResponse {
    $anos = $this->usinaService->listarAnosPorUsina($id);
    
    if (empty($anos)) {
      return response()->json(['message' => 'Nenhum ano encontrado para esta usina.'], 404);
    }

    return response()->json(['anos' => $anos]);
  }

  private function paginatedResponse(LengthAwarePaginator $paginator): JsonResponse {
    return response()->json([
      'data' => $paginator->items(),
      'current_page' => $paginator->currentPage(),
      'last_page' => $paginator->lastPage(),
      'total' => $paginator->total(),
    ]);
  }

}
