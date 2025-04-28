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
        Schema::create('usina', function (Blueprint $table) {
            $table->increments('usi_id');
            $table->integer('cli_id');
            $table->integer('dger_id');
            $table->integer('com_id');
            $table->foreign('cli_id')->references('cli_id')->on('cliente');
            $table->foreign('com_id')->references('com_id')->on('comercializacao');
            $table->foreign('dger_id')->references('dger_id')->on('dados_geracao');
            $table->timestamps();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usinas');
    }
};
