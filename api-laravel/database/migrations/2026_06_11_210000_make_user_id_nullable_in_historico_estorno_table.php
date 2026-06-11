<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Torna `historico_estorno.user_id` NULLABLE.
 *
 * O snapshot de estorno passou a ser gravado também pelo BACKFILL
 * (ledger:reconstruir -> FaturamentoService::calcularMes), que é uma operação de
 * SISTEMA sem usuário autenticado. Antes o backfill caía no FK constraint ao tentar
 * gravar user_id = 0. Agora um snapshot de origem sistêmica grava user_id NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE historico_estorno ALTER COLUMN user_id DROP NOT NULL');

            return;
        }

        // SQLite (testes) e demais drivers: rebuild via schema builder.
        Schema::table('historico_estorno', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE historico_estorno ALTER COLUMN user_id SET NOT NULL');

            return;
        }

        Schema::table('historico_estorno', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
