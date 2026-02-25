<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_estorno', function (Blueprint $table) {
            $table->increments('he_id');
            $table->unsignedInteger('usi_id');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->string('mes_nome', 20);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_id_estorno')->nullable();
            $table->string('idempotency_key', 36)->nullable();
            $table->json('snapshot_reserva_atual');
            $table->json('snapshot_reserva_anterior')->nullable();
            $table->float('snapshot_credito_mes')->default(0);
            $table->float('snapshot_faturamento_mes')->default(0);
            $table->float('snapshot_geracao_mes')->default(0);
            $table->timestamp('revertido_em')->nullable();
            $table->timestamps();

            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('user_id_estorno')->references('id')->on('users');

            $table->index(['usi_id', 'revertido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_estorno');
    }
};
