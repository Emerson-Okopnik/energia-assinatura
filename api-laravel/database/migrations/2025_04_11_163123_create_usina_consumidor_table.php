<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('usina_consumidor', function (Blueprint $table) {
            $table->id('usic_id');
            $table->unsignedBigInteger('usi_id');
            $table->unsignedBigInteger('cli_id');
            $table->unsignedBigInteger('con_id');

            $table->timestamps();

            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
            $table->foreign('cli_id')->references('cli_id')->on('cliente')->onDelete('cascade');
            $table->foreign('con_id')->references('con_id')->on('consumidor')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('usina_consumidor');
    }
};
