<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('consumidor', function (Blueprint $table) {
            if (Schema::hasColumn('consumidor', 'vendedor')) {
                $table->dropColumn('vendedor');
            }
        });
    }

    public function down(): void {
        Schema::table('consumidor', function (Blueprint $table) {
            $table->string('vendedor')->nullable();
        });
    }
};
