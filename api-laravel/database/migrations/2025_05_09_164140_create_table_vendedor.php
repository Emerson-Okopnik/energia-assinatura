<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('vendedor', function (Blueprint $table) {
            $table->id('ven_id');
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('patente');
            $table->timestamps();
        });

        Schema::table('consumidor', function (Blueprint $table) {
            $table->unsignedBigInteger('ven_id')->after('alocacao');

            $table->foreign('ven_id')
                  ->references('ven_id')
                  ->on('vendedor')
                  ->onDelete('cascade');
        });

        Schema::table('usina', function (Blueprint $table) {
            $table->unsignedBigInteger('ven_id')->after('dger_id');

            $table->foreign('ven_id')
                  ->references('ven_id')
                  ->on('vendedor')
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::table('usina', function (Blueprint $table) {
            $table->dropForeign(['ven_id']);
            $table->dropColumn('ven_id');
        });

        Schema::table('consumidor', function (Blueprint $table) {
            $table->dropForeign(['ven_id']);
            $table->dropColumn('ven_id');
        });

        Schema::dropIfExists('vendedor');
    }
};
