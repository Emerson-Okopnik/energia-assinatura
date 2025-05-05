<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->unsignedBigInteger('fa_id')->after('cd_id');
            $table->unsignedBigInteger('var_id')->after('fa_id');

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

    public function down(): void {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->dropForeign(['fa_id']);
            $table->dropForeign(['var_id']);
            $table->dropColumn(['fa_id', 'var_id']);
        });
    }
};
