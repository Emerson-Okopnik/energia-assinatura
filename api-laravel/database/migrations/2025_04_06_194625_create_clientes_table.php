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
        Schema::create('cliente', function (Blueprint $table) {
            $table->increments('cli_id');
            $table->string('nome');
            $table->string('cpf_cnpj');
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->integer('end_id');
            $table->foreign('end_id')->references('end_id')->on('endereco');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
