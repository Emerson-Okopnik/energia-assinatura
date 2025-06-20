<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // Drop foreign key if exists (for safety)
            $table->dropForeign(['usi_id']);
            $table->dropForeign(['cd_id']);
            $table->dropForeign(['fa_id']);
            $table->dropForeign(['var_id']);
        });

        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // Re-add with cascade
            $table->foreign('usi_id')
                ->references('usi_id')
                ->on('usina')
                ->onDelete('cascade');

            $table->foreign('cd_id')
                ->references('cd_id')
                ->on('creditos_distribuidos')
                ->onDelete('cascade');

            $table->foreign('fa_id')
                ->references('fa_id')
                ->on('faturamento_usina')
                ->onDelete('cascade');

            $table->foreign('var_id')
                ->references('var_id')
                ->on('valor_acumulado_reserva')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // Remove cascade constraints
            $table->dropForeign(['usi_id']);
            $table->dropForeign(['cd_id']);
            $table->dropForeign(['fa_id']);
            $table->dropForeign(['var_id']);

            // Re-add without cascade (default behavior)
            $table->foreign('usi_id')->references('usi_id')->on('usina');
            $table->foreign('cd_id')->references('cd_id')->on('creditos_distribuidos');
            $table->foreign('fa_id')->references('fa_id')->on('faturamento_usina');
            $table->foreign('var_id')->references('var_id')->on('valor_acumulado_reserva');
        });
    }
};
