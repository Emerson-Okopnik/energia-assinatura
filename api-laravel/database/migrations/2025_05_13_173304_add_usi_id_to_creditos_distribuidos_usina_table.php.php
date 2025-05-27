<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // Adiciona a coluna usi_id como chave estrangeira
            $table->unsignedBigInteger('usi_id')->after('cdu_id');

            // Cria a foreign key referenciando a tabela usina
            $table->foreign('usi_id')
                  ->references('usi_id')
                  ->on('usina')
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // Remove a foreign key e a coluna usi_id
            $table->dropForeign(['usi_id']);
            $table->dropColumn('usi_id');
        });
    }
};
