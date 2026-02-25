<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CalculoGeracaoRequest;
use App\Models\{IdempotencyKey, Usina};
use App\Services\CalculoGeracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CalculoGeracaoController extends Controller
{
    private CalculoGeracaoService $service;

    public function __construct(CalculoGeracaoService $service)
    {
        $this->service = $service;
    }

    public function calcular(CalculoGeracaoRequest $request, int $usi_id, int $ano, int $mes): JsonResponse
    {
        $user           = $request->user();
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json(['error' => 'Idempotency-Key header missing'], 400);
        }

        $payload = $request->validated();
        $hash    = hash('sha256', json_encode([$usi_id, $ano, $mes, $payload]));

        $existing = IdempotencyKey::where('key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->hash_payload !== $hash) {
                return response()->json(['error' => 'Payload conflict'], 409);
            }
            return response()->json(['success' => true, 'data' => $existing->response]);
        }

        $usina = Usina::find($usi_id);
        if (!$usina || ($user->cli_id ?? $usina->cli_id) !== $usina->cli_id) {
            return response()->json(['error' => 'Usina não encontrada'], 404);
        }

        try {
            $data = $this->service->process($usina, $ano, $mes, $payload, $user->id, $idempotencyKey);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao calcular geração', ['exception' => $e]);
            return response()->json(['error' => 'Erro interno'], 500);
        }

        IdempotencyKey::create([
            'key'          => $idempotencyKey,
            'hash_payload' => $hash,
            'user_id'      => $user->id,
            'response'     => $data,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
