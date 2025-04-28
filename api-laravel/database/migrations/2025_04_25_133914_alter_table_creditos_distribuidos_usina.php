<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // Remove a foreign key e a coluna con_id
            $table->dropForeign(['con_id']);
            $table->dropColumn('con_id');
        });

        // Renomeia a chave primária 'id' para 'cdu_id'
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->renameColumn('id', 'cdu_id');
        });
    }

    public function down(): void
    {
        // Reverte a renomeação de cdu_id para id
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->renameColumn('cdu_id', 'id');
        });

        // Reinsere a coluna con_id com a foreign key
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->unsignedBigInteger('con_id')->after('cli_id');

            $table->foreign('con_id')
                  ->references('con_id')
                  ->on('consumidor')
                  ->onDelete('cascade');
        });
    }
};
