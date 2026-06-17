<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;

class AuditoriaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria)
    {
    }

    public function usinas(): JsonResponse
    {
        return response()->json($this->auditoria->listaUsinas());
    }

    public function usina(int $usiId): JsonResponse
    {
        return response()->json($this->auditoria->detalheUsina($usiId));
    }
}
