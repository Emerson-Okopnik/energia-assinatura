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
        Schema::create('dados_geracao', function (Blueprint $table) {
            $table->increments('dger_id');
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
            $table->float('media');
            $table->float('menor_geracao');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dados_geracao');
    }
};
