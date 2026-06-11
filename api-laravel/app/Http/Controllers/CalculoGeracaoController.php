<?php

namespace App\Http\Controllers;

use App\Application\Faturamento\FaturamentoService;
use App\Http\Requests\CalculoGeracaoRequest;
use App\Models\{IdempotencyKey, Usina};
use App\Services\CalculoGeracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CalculoGeracaoController extends Controller
{
    public function __construct(
        private CalculoGeracaoService $service,
        private FaturamentoService $faturamento,
    ) {
    }

    /**
     * Motor ANTIGO (transição). Mantido funcionando para não quebrar o front atual.
     */
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

    /**
     * PREVIEW (núcleo único, SEM persistir) — REGRAS_DE_CALCULO.md §8.
     *
     * Retorna TODOS os termos (fixo, variável, crédito, cuo, receita de expiração,
     * valor final em R$ e kWh onde aplicável) + detalhe do consumo FIFO + expiração
     * para auditoria. Não grava nada (ledger inalterado).
     */
    public function preview(CalculoGeracaoRequest $request, int $usi_id, int $ano, int $mes): JsonResponse
    {
        $user  = $request->user();
        $usina = Usina::with(['comercializacao', 'dadoGeracao'])->find($usi_id);

        if (!$usina || ($user->cli_id ?? $usina->cli_id) !== $usina->cli_id) {
            return response()->json(['error' => 'Usina não encontrada'], 404);
        }

        try {
            $resposta = $this->faturamento->calcularMes(
                $usina,
                $ano,
                $mes,
                $request->validated(),
                persistir: false,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $resposta->toArray()]);
    }

    /**
     * CÁLCULO ÚNICO + PERSISTÊNCIA (núcleo único) — Fase 4.
     *
     * Aceita o input atual do front (inclusive valorPago_mes), mas IGNORA
     * valorPago_mes (o valor final agora é CALCULADO pelo núcleo). Preserva a
     * idempotência via header Idempotency-Key e o contrato {success, data}.
     */
    public function processar(CalculoGeracaoRequest $request, int $usi_id, int $ano, int $mes): JsonResponse
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

        $usina = Usina::with(['comercializacao', 'dadoGeracao'])->find($usi_id);
        if (!$usina || ($user->cli_id ?? $usina->cli_id) !== $usina->cli_id) {
            return response()->json(['error' => 'Usina não encontrada'], 404);
        }

        try {
            $resposta = $this->faturamento->calcularMes(
                $usina,
                $ano,
                $mes,
                $payload,
                persistir: true,
                userId: $user->id,
                idempotencyKey: $idempotencyKey,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Erro ao calcular geração (núcleo único)', ['exception' => $e]);
            return response()->json(['error' => 'Erro interno'], 500);
        }

        $data = $resposta->toArray();

        IdempotencyKey::create([
            'key'          => $idempotencyKey,
            'hash_payload' => $hash,
            'user_id'      => $user->id,
            'response'     => $data,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
