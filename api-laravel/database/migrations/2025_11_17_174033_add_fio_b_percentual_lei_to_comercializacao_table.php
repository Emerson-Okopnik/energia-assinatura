<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercializacao', function (Blueprint $table) {
            $table->decimal('fio_b', 10, 4)->default(0.00)->after('data_conexao');
            $table->decimal('percentual_lei', 5, 2)->default(0.00)->after('fio_b');
        });
    }

    public function down(): void
    {
        Schema::table('comercializacao', function (Blueprint $table) {
            $table->dropColumn(['fio_b', 'percentual_lei']);
        });
    }
};