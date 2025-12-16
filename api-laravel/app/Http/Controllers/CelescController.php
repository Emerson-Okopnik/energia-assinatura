<?php

namespace App\Http\Controllers;

use App\Services\CelescApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CelescController extends Controller
{
    public function __construct(private CelescApiService $celescApiService)
    {
    }

    public function emitirFatura(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'contract_account' => ['nullable', 'string'],
            'access_id' => ['nullable', 'string'],
            'partner' => ['nullable', 'string'],
            'invoice_id' => ['nullable', 'string'],
            'bill' => ['nullable', 'string'],
            'channel' => ['nullable', 'string'],
            'profile_type' => ['nullable', 'string'],
            'installation' => ['nullable', 'string'],
            'owner' => ['nullable', 'string'],
            'zip_code' => ['nullable', 'string'],
        ]);

        try {
            $resultado = $this->celescApiService->executarFluxoFatura($dados);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'auth' => $resultado['auth'],
            'contracts' => $resultado['contracts'],
            'invoice' => [
                'channel' => $resultado['invoice']['channel'] ?? null,
                'partner' => $resultado['invoice']['partner'] ?? null,
                'contract_account' => $resultado['invoice']['contractAccount'] ?? null,
                'access_id' => $resultado['invoice']['accessId'] ?? null,
                'invoice_id' => $resultado['invoice']['invoiceId'] ?? null,
                'invoice_base64' => $resultado['invoice']['invoiceBase64'] ?? null,
                '__typename' => $resultado['invoice']['__typename'] ?? null,
            ],
        ]);
    }
}