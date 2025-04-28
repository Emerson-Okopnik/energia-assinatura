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
        Schema::create('comercializacao', function (Blueprint $table) {
            $table->increments('com_id');
            $table->float('valor_kwh');
            $table->float('valor_fixo');
            $table->string('cia_energia');
            $table->float('valor_final_media');
            $table->date('previsao_conexao');
            $table->date('data_conexao');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comercializacoes');
    }
};
