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
        Schema::table('consumidor', function (Blueprint $table) {
            $table->string('rede')->default('monofasico')->after('uc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumidor', function (Blueprint $table) {
            $table->dropColumn('rede');
        });
    }
};
