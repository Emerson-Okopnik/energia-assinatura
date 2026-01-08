<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up() {
        Schema::create('geracao_faturamento_pdf', function (Blueprint $table) {
            $table->increments('gfp_id');
            $table->unsignedInteger('usi_id');
            $table->date('competencia');
            $table->float('geracao_kwh')->default(0);
            $table->float('valor_fixo')->default(0);
            $table->float('injetado')->default(0);
            $table->float('creditado')->default(0);
            $table->float('cuo')->default(0);
            $table->float('valor_final')->default(0);
            $table->timestamps();

            $table->unique(['usi_id', 'competencia']);
            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
        });
    }

    public function down() {
        Schema::dropIfExists('geracao_faturamento_pdf');
    }
};
