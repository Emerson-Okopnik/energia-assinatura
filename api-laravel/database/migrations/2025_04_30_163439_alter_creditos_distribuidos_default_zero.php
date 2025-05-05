<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos_distribuidos', function (Blueprint $table) {
            $table->float('janeiro')->default(0)->change();
            $table->float('fevereiro')->default(0)->change();
            $table->float('marco')->default(0)->change();
            $table->float('abril')->default(0)->change();
            $table->float('maio')->default(0)->change();
            $table->float('junho')->default(0)->change();
            $table->float('julho')->default(0)->change();
            $table->float('agosto')->default(0)->change();
            $table->float('setembro')->default(0)->change();
            $table->float('outubro')->default(0)->change();
            $table->float('novembro')->default(0)->change();
            $table->float('dezembro')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('creditos_distribuidos', function (Blueprint $table) {
            $table->float('janeiro')->default(null)->change();
            $table->float('fevereiro')->default(null)->change();
            $table->float('marco')->default(null)->change();
            $table->float('abril')->default(null)->change();
            $table->float('maio')->default(null)->change();
            $table->float('junho')->default(null)->change();
            $table->float('julho')->default(null)->change();
            $table->float('agosto')->default(null)->change();
            $table->float('setembro')->default(null)->change();
            $table->float('outubro')->default(null)->change();
            $table->float('novembro')->default(null)->change();
            $table->float('dezembro')->default(null)->change();
        });
    }
};
