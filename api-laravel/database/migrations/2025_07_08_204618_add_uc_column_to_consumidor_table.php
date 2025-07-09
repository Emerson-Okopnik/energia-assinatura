<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumidor', function (Blueprint $table) {
            $table->string('uc')->nullable()->after('cia_energia');
        });
    }

    public function down(): void
    {
        Schema::table('consumidor', function (Blueprint $table) {
            $table->dropColumn('uc');
        });
    }
};
