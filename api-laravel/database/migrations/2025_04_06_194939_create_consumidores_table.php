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
        Schema::create('consumidor', function (Blueprint $table) {
            $table->increments('con_id');
            $table->Integer('cli_id');
            $table->Integer('dcon_id');
            $table->string('cia_energia');
            $table->foreign('cli_id')->references('cli_id')->on('cliente');
            $table->foreign('dcon_id')->references('dcon_id')->on('dados_consumo');
            $table->timestamps();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumidores');
    }
};
