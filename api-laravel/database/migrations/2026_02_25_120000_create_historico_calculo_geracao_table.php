<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_calculo_geracao', function (Blueprint $table) {
            $table->id('hcg_id');
            $table->unsignedBigInteger('usi_id');
            $table->integer('ano');
            $table->unsignedTinyInteger('mes');
            $table->json('snapshot');
            $table->unsignedBigInteger('dcon_id')->nullable();
            $table->unsignedBigInteger('dcu_id')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->unique(['usi_id', 'ano', 'mes'], 'hcg_usi_ano_mes_unique');
            $table->index(['usi_id', 'ano'], 'hcg_usi_ano_index');

            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
            $table->foreign('dcon_id')->references('dcon_id')->on('dados_consumo')->nullOnDelete();
            $table->foreign('dcu_id')->references('dcu_id')->on('dados_consumo_usina')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_calculo_geracao');
    }
};
