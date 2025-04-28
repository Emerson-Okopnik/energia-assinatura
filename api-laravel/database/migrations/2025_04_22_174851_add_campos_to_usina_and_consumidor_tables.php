<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usina', function (Blueprint $table) {
            $table->date('data_limite_troca_titularidade')->nullable()->after('com_id');
            $table->date('data_ass_contrato')->nullable()->after('data_limite_troca_titularidade');
            $table->string('status')->default('ativo')->after('data_ass_contrato');
            $table->string('andamento_processo')->nullable()->after('status');
        });

        Schema::table('consumidor', function (Blueprint $table) {
            $table->string('vendedor')->nullable()->after('cia_energia');
            $table->date('data_entrega')->nullable()->after('vendedor');
            $table->string('status')->default('ativo')->after('data_entrega');
            $table->string('andamento_processo')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('usina', function (Blueprint $table) {
            $table->dropColumn([
                'data_limite_troca_titularidade',
                'data_ass_contrato',
                'status',
                'andamento_processo'
            ]);
        });

        Schema::table('consumidor', function (Blueprint $table) {
            $table->dropColumn([
                'vendedor',
                'data_entrega',
                'status',
                'andamento_processo'
            ]);
        });
    }
};
