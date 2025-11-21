<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dados_consumo_usina', function (Blueprint $table) {
            $table->id('dcu_id');
            $table->unsignedBigInteger('usi_id');
            $table->unsignedBigInteger('cli_id');
            $table->unsignedBigInteger('dcon_id');
            $table->integer('ano');

            $table->timestamps();

            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
            $table->foreign('cli_id')->references('cli_id')->on('cliente')->onDelete('cascade');
            $table->foreign('dcon_id')->references('dcon_id')->on('dados_consumo')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dados_consumo_usina');
    }
};