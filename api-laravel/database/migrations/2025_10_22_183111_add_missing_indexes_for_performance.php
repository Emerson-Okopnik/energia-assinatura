<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // === consumidor ===
        Schema::table('consumidor', function (Blueprint $table) {
            if (!$this->indexExists('consumidor_cli_id_index')) {
                $table->index('cli_id', 'consumidor_cli_id_index');
            }
            if (!$this->indexExists('consumidor_dcon_id_index')) {
                $table->index('dcon_id', 'consumidor_dcon_id_index');
            }
            if (!$this->indexExists('consumidor_ven_id_index')) {
                $table->index('ven_id', 'consumidor_ven_id_index');
            }
            if (!$this->indexExists('consumidor_status_index')) {
                $table->index('status', 'consumidor_status_index');
            }
        });

        // === usina ===
        Schema::table('usina', function (Blueprint $table) {
            if (!$this->indexExists('usina_cli_id_index')) {
                $table->index('cli_id', 'usina_cli_id_index');
            }
            if (!$this->indexExists('usina_dger_id_index')) {
                $table->index('dger_id', 'usina_dger_id_index');
            }
            if (!$this->indexExists('usina_com_id_index')) {
                $table->index('com_id', 'usina_com_id_index');
            }
            if (!$this->indexExists('usina_ven_id_index')) {
                $table->index('ven_id', 'usina_ven_id_index');
            }
            if (!$this->indexExists('usina_status_index')) {
                $table->index('status', 'usina_status_index');
            }
        });

        // === cliente ===
        Schema::table('cliente', function (Blueprint $table) {
            if (!$this->indexExists('cliente_end_id_index')) {
                $table->index('end_id', 'cliente_end_id_index');
            }
            if (!$this->indexExists('cliente_cpf_cnpj_index')) {
                $table->index('cpf_cnpj', 'cliente_cpf_cnpj_index');
            }
        });

        // === creditos_distribuidos_usina ===
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            if (!$this->indexExists('cdu_cd_id_index')) {
                $table->index('cd_id', 'cdu_cd_id_index');
            }
            if (!$this->indexExists('cdu_cli_id_index')) {
                $table->index('cli_id', 'cdu_cli_id_index');
            }
            if (!$this->indexExists('cdu_fa_id_index')) {
                $table->index('fa_id', 'cdu_fa_id_index');
            }
            if (!$this->indexExists('cdu_var_id_index')) {
                $table->index('var_id', 'cdu_var_id_index');
            }
        });

        // === dados_geracao_real_usina ===
        Schema::table('dados_geracao_real_usina', function (Blueprint $table) {
            if (!$this->indexExists('dgru_dgr_id_index')) {
                $table->index('dgr_id', 'dgru_dgr_id_index');
            }
            if (!$this->indexExists('dgru_cli_id_index')) {
                $table->index('cli_id', 'dgru_cli_id_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumidor', function (Blueprint $table) {
            $table->dropIndex('consumidor_cli_id_index');
            $table->dropIndex('consumidor_dcon_id_index');
            $table->dropIndex('consumidor_ven_id_index');
            $table->dropIndex('consumidor_status_index');
        });

        Schema::table('usina', function (Blueprint $table) {
            $table->dropIndex('usina_cli_id_index');
            $table->dropIndex('usina_dger_id_index');
            $table->dropIndex('usina_com_id_index');
            $table->dropIndex('usina_ven_id_index');
            $table->dropIndex('usina_status_index');
        });

        Schema::table('cliente', function (Blueprint $table) {
            $table->dropIndex('cliente_end_id_index');
            $table->dropIndex('cliente_cpf_cnpj_index');
        });

        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->dropIndex('cdu_cd_id_index');
            $table->dropIndex('cdu_cli_id_index');
            $table->dropIndex('cdu_fa_id_index');
            $table->dropIndex('cdu_var_id_index');
        });

        Schema::table('dados_geracao_real_usina', function (Blueprint $table) {
            $table->dropIndex('dgru_dgr_id_index');
            $table->dropIndex('dgru_cli_id_index');
        });
    }

    /**
     * Check if an index exists in Postgres (without Doctrine)
     */
    private function indexExists(string $indexName): bool
    {
        $result = DB::selectOne("
            SELECT 1
            FROM pg_indexes
            WHERE indexname = ?
            LIMIT 1
        ", [$indexName]);

        return $result !== null;
    }
};
