<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('valor_acumulado_reserva', function (Blueprint $table) {
            $table->increments('var_id');
            $table->float('janeiro')->default(0);
            $table->float('fevereiro')->default(0);
            $table->float('marco')->default(0);
            $table->float('abril')->default(0);
            $table->float('maio')->default(0);
            $table->float('junho')->default(0);
            $table->float('julho')->default(0);
            $table->float('agosto')->default(0);
            $table->float('setembro')->default(0);
            $table->float('outubro')->default(0);
            $table->float('novembro')->default(0);
            $table->float('dezembro')->default(0);
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('valor_acumulado_reserva');
    }
};
