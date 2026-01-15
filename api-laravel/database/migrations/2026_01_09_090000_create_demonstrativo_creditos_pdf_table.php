<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('demonstrativo_creditos_pdf', function (Blueprint $table) {
            $table->increments('dcp_id');
            $table->unsignedInteger('usi_id');
            $table->date('competencia');
            $table->date('vencimento')->nullable();
            $table->float('guardado_kwh')->default(0);
            $table->float('creditado_kwh')->default(0);
            $table->text('meses_utilizados')->nullable();
            $table->timestamps();

            $table->unique(['usi_id', 'competencia']);
            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('demonstrativo_creditos_pdf');
    }
};
