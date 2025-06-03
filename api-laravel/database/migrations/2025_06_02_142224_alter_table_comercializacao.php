<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercializacao', function (Blueprint $table) {
            $table->date('data_conexao')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comercializacao', function (Blueprint $table) {
            $table->date('data_conexao')->nullable(false)->change();
        });
    }
};
