<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usina', function (Blueprint $table) {
            $table->string('rede')->nullable()->after('uc');
        });
    }

    public function down(): void
    {
        Schema::table('usina', function (Blueprint $table) {
            $table->dropColumn('rede');
        });
    }
};