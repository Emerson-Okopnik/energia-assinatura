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
        Schema::create('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usi_id');
            $table->unsignedBigInteger('cli_id');
            $table->unsignedBigInteger('con_id');
            $table->unsignedBigInteger('cd_id');
            $table->integer('ano');
            $table->timestamps();

            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
            $table->foreign('cli_id')->references('cli_id')->on('cliente')->onDelete('cascade');
            $table->foreign('con_id')->references('con_id')->on('consumidor')->onDelete('cascade');
            $table->foreign('cd_id')->references('cd_id')->on('creditos_distribuidos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditos_distribuidos_usina');
    }
};
