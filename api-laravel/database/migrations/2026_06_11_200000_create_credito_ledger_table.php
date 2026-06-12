<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Livro de lançamentos imutáveis da reserva (REGRAS_DE_CALCULO.md §8).
 *
 * Cada movimento de crédito vira uma linha: SALDO_INICIAL, CREDITO (guardou),
 * CONSUMO (resgatou, kwh negativo) ou EXPIRACAO (venceu, kwh negativo).
 * O saldo de uma origem é a soma dos `kwh` não-estornados daquela origem.
 * Consumir/expirar nunca edita o crédito original — insere uma saída que aponta
 * para o CREDITO de origem via `ref_lancamento_id` (rastreabilidade FIFO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credito_ledger', function (Blueprint $table) {
            $table->bigIncrements('cl_id');

            $table->unsignedBigInteger('usi_id');

            // Mês que gerou o crédito (define o vencimento) e mês do evento.
            $table->date('competencia_origem');
            $table->date('competencia_evento');

            // SALDO_INICIAL, CREDITO, CONSUMO, EXPIRACAO.
            $table->string('tipo', 20);

            // Energia do lançamento: positiva (entrada) ou negativa (saída).
            $table->decimal('kwh', 14, 4);

            // Tarifa e R$ históricos para reconstruir o dinheiro do lançamento.
            $table->decimal('tarifa_kwh', 12, 6)->default(0);
            $table->decimal('valor_reais', 12, 2)->default(0);

            // competencia_origem + 180 dias (nulo para alguns SALDO_INICIAL).
            $table->date('vencimento')->nullable();

            // Saída (CONSUMO/EXPIRACAO) aponta para o CREDITO de origem.
            $table->unsignedBigInteger('ref_lancamento_id')->nullable();

            $table->string('idempotency_key', 64)->nullable();
            $table->timestamp('estornado_em')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();

            $table->foreign('usi_id')
                ->references('usi_id')->on('usina')
                ->onDelete('cascade');

            $table->foreign('ref_lancamento_id')
                ->references('cl_id')->on('credito_ledger')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->index(['usi_id', 'competencia_origem'], 'credito_ledger_usi_origem_idx');
            $table->index(['usi_id', 'tipo', 'estornado_em'], 'credito_ledger_usi_tipo_estorno_idx');
            $table->index(['usi_id', 'vencimento'], 'credito_ledger_usi_vencimento_idx');
            // UNIQUE: garante idempotência a nível de banco (re-rodar o backfill não duplica).
            $table->unique('idempotency_key', 'credito_ledger_idempotency_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credito_ledger');
    }
};
