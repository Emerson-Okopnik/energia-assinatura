<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // BTREE index otimiza filtros frequentes por usina e ano, além de ORDER BY ano.
            $table->index(['usi_id', 'ano'], 'cdu_usi_ano_index');
        });

        Schema::table('dados_geracao_real_usina', function (Blueprint $table) {
            // BTREE index acelera buscas por usina e ano durante cálculos de geração real.
            $table->index(['usi_id', 'ano'], 'dgru_usi_ano_index');
        });

        Schema::table('usina_consumidor', function (Blueprint $table) {
            // BTREE composto reduz custo de deleções/consultas por usina e consumidor vinculados.
            $table->index(['usi_id', 'con_id'], 'usic_usi_con_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->dropIndex('cdu_usi_ano_index');
        });

        Schema::table('dados_geracao_real_usina', function (Blueprint $table) {
            $table->dropIndex('dgru_usi_ano_index');
        });

        Schema::table('usina_consumidor', function (Blueprint $table) {
            $table->dropIndex('usic_usi_con_index');
        });
    }
};