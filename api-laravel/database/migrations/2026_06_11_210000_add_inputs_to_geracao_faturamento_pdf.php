<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda os INPUTS manuais do mês (fatura de energia e consumo) junto do breakdown
 * persistido, para a tela poder PRÉ-PREENCHER o que foi salvo ao reabrir um mês —
 * senão o preview recalcula com fatura 0 e diverge do que foi gravado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('geracao_faturamento_pdf', function (Blueprint $table) {
            $table->decimal('fatura_energia', 12, 2)->default(0)->after('valor_final');
        });
    }

    public function down(): void
    {
        Schema::table('geracao_faturamento_pdf', function (Blueprint $table) {
            $table->dropColumn('fatura_energia');
        });
    }
};
