<?php

namespace App\Http\Controllers;

use App\Application\Faturamento\FaturamentoService;
use App\Http\Requests\CalculoGeracaoRequest;
use App\Models\{IdempotencyKey, Usina};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CalculoGeracaoController extends Controller
{
    public function __construct(
        private FaturamentoService $faturamento,
    ) {
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
     * UPSERT do consumo de UM mês, preservando os outros meses do ano. Evita o bug
     * das duplicatas (cada save criava um novo registro com só 1 mês preenchido).
     */
    public function upsertConsumoMes(\Illuminate\Http\Request $request, int $usi_id, int $ano, int $mes): JsonResponse
    {
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];
        $mesNome = $meses[$mes] ?? null;
        if ($mesNome === null) {
            return response()->json(['error' => 'Mês inválido'], 422);
        }

        $consumo = (float) $request->input('consumo', 0);
        $usina = Usina::find($usi_id);
        if (!$usina) {
            return response()->json(['error' => 'Usina não encontrada'], 404);
        }

        return response()->json(['success' => true, 'data' => \Illuminate\Support\Facades\DB::transaction(function () use ($usina, $usi_id, $ano, $mesNome, $consumo) {
            // Reusa o vínculo mais recente do ano (ou cria um). Atualiza só o mês.
            $vinculo = \App\Models\DadoConsumoUsina::where('usi_id', $usi_id)
                ->where('ano', $ano)->orderByDesc('dcu_id')->first();

            if ($vinculo !== null) {
                $dadoConsumo = \App\Models\DadoConsumo::find($vinculo->dcon_id);
            } else {
                $dadoConsumo = \App\Models\DadoConsumo::create(array_merge(
                    array_fill_keys(['janeiro','fevereiro','marco','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'], 0),
                    ['media' => 0]
                ));
                $vinculo = \App\Models\DadoConsumoUsina::create([
                    'usi_id' => $usi_id,
                    'cli_id' => $usina->cli_id,
                    'dcon_id' => $dadoConsumo->dcon_id,
                    'ano' => $ano,
                ]);
            }

            $dadoConsumo->{$mesNome} = $consumo;
            $dadoConsumo->save();

            return ['dcu_id' => $vinculo->dcu_id, 'mes' => $mesNome, 'consumo' => $consumo];
        })]);
    }

    /**
     * INPUTS SALVOS por mês (fatura de energia + consumo) para um ano, para a tela
     * PRÉ-PREENCHER o que foi gravado ao reabrir um mês. Retorna mapa mes_nome => {...}.
     */
    public function inputsSalvos(\Illuminate\Http\Request $request, int $usi_id, int $ano): JsonResponse
    {
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];

        // Fatura salva por competência (geracao_faturamento_pdf).
        $faturas = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usi_id)
            ->whereYear('competencia', $ano)
            ->get()
            ->keyBy(fn ($r) => (int) \Illuminate\Support\Carbon::parse($r->competencia)->month);

        // Consumo salvo por mês (dados_consumo_usina -> dados_consumo).
        $consumo = \Illuminate\Support\Facades\DB::table('dados_consumo_usina as dcu')
            ->join('dados_consumo as dc', 'dc.dcon_id', '=', 'dcu.dcon_id')
            ->where('dcu.usi_id', $usi_id)
            ->where('dcu.ano', $ano)
            ->orderByDesc('dcu.dcu_id')
            ->first();

        $resultado = [];
        foreach ($meses as $num => $nome) {
            $resultado[$nome] = [
                'fatura_energia' => isset($faturas[$num]) ? (float) $faturas[$num]->fatura_energia : null,
                'consumo' => $consumo ? (float) ($consumo->$nome ?? 0) : null,
            ];
        }

        return response()->json(['success' => true, 'data' => $resultado]);
    }

    /**
     * PROJEÇÃO ANUAL (Expectativa) — os 12 meses com a geração PROJETADA da usina,
     * simulados pelo motor único (reserva construída do zero). Não toca o ledger real.
     */
    public function projecao(\Illuminate\Http\Request $request, int $usi_id, int $ano): JsonResponse
    {
        $user  = $request->user();
        $usina = Usina::with(['comercializacao', 'dadoGeracao'])->find($usi_id);

        if (!$usina || ($user->cli_id ?? $usina->cli_id) !== $usina->cli_id) {
            return response()->json(['error' => 'Usina não encontrada'], 404);
        }

        $fatura = (float) $request->query('fatura_energia', 0);

        try {
            $meses = $this->faturamento->projetarAno($usina, $ano, $fatura);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $meses]);
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
