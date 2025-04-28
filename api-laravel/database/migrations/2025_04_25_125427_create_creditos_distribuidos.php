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
        Schema::create('creditos_distribuidos', function (Blueprint $table) {
            $table->id('cd_id');
            $table->float('janeiro')->notNull();
            $table->float('fevereiro')->notNull();
            $table->float('marco')->notNull();
            $table->float('abril')->notNull();
            $table->float('maio')->notNull();
            $table->float('junho')->notNull();
            $table->float('julho')->notNull();
            $table->float('agosto')->notNull();
            $table->float('setembro')->notNull();
            $table->float('outubro')->notNull();
            $table->float('novembro')->notNull();
            $table->float('dezembro')->notNull();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditos_distribuidos');
    }
};
