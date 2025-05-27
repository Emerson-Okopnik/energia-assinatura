<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dados_geracao_real', function (Blueprint $table) {
            $table->id('dgr_id');
            $table->float('janeiro');
            $table->float('fevereiro');
            $table->float('marco');
            $table->float('abril');
            $table->float('maio');
            $table->float('junho');
            $table->float('julho');
            $table->float('agosto');
            $table->float('setembro');
            $table->float('outubro');
            $table->float('novembro');
            $table->float('dezembro');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_dados_geracao_real');
    }
};
