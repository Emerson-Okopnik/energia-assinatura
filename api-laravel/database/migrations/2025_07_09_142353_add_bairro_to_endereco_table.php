<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endereco', function (Blueprint $table) {
            $table->string('bairro')->nullable()->after('cidade');
        });
    }

    public function down(): void
    {
        Schema::table('endereco', function (Blueprint $table) {
            $table->dropColumn('bairro');
        });
    }
};
