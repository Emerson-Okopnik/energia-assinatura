<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dados_geracao_real_usina', function (Blueprint $table) {
            $table->id('dgru_id');
            $table->unsignedBigInteger('usi_id');
            $table->unsignedBigInteger('cli_id');
            $table->unsignedBigInteger('dgr_id');
            $table->integer('ano');

            $table->timestamps();

            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
            $table->foreign('cli_id')->references('cli_id')->on('cliente')->onDelete('cascade');
            $table->foreign('dgr_id')->references('dgr_id')->on('dados_geracao_real')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_dados_geracao_real_usina');
    }
};
