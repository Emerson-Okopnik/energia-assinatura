<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedor', function (Blueprint $table) {
            if (!Schema::hasColumn('vendedor', 'email')) {
                $table->string('email')->nullable()->after('nome');
            }

            if (!Schema::hasColumn('vendedor', 'telefone')) {
                $table->string('telefone')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendedor', function (Blueprint $table) {
            if (Schema::hasColumn('vendedor', 'telefone')) {
                $table->dropColumn('telefone');
            }

            if (Schema::hasColumn('vendedor', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};