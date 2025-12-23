<?php

namespace App\Http\Controllers;

use App\Exceptions\BillNotFoundException;
use App\Exceptions\CelescApiException;
use App\Exceptions\ContractNotFoundException;
use App\Exceptions\LoginFailedException;
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
            'installation' => ['required', 'string'],
            'contract_account' => ['nullable', 'string'],
            'invoiceId' => ['nullable', 'string'],
            'billingPeriod' => ['nullable', 'string'],
            'billing_period' => ['nullable', 'string'],
            'channelCode' => ['nullable', 'string'],
            'target' => ['nullable', 'string'],
        ]);

        try {
            $resultado = $this->celescApiService->gerarSegundaVia($dados);
        } catch (ContractNotFoundException|BillNotFoundException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (LoginFailedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        } catch (CelescApiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json($resultado);
    }
}