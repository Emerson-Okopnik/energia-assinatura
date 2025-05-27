<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dados_geracao_real', function (Blueprint $table) {
            $table->float('janeiro')->nullable()->change();
            $table->float('fevereiro')->nullable()->change();
            $table->float('marco')->nullable()->change();
            $table->float('abril')->nullable()->change();
            $table->float('maio')->nullable()->change();
            $table->float('junho')->nullable()->change();
            $table->float('julho')->nullable()->change();
            $table->float('agosto')->nullable()->change();
            $table->float('setembro')->nullable()->change();
            $table->float('outubro')->nullable()->change();
            $table->float('novembro')->nullable()->change();
            $table->float('dezembro')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dados_geracao_real', function (Blueprint $table) {
            $table->float('janeiro')->nullable(false)->change();
            $table->float('fevereiro')->nullable(false)->change();
            $table->float('marco')->nullable(false)->change();
            $table->float('abril')->nullable(false)->change();
            $table->float('maio')->nullable(false)->change();
            $table->float('junho')->nullable(false)->change();
            $table->float('julho')->nullable(false)->change();
            $table->float('agosto')->nullable(false)->change();
            $table->float('setembro')->nullable(false)->change();
            $table->float('outubro')->nullable(false)->change();
            $table->float('novembro')->nullable(false)->change();
            $table->float('dezembro')->nullable(false)->change();
        });
    }
};
