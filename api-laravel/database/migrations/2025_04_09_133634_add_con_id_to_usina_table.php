<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usina', function (Blueprint $table) {
            $table->unsignedInteger('con_id')->after('com_id');

            $table->foreign('con_id')
                  ->references('con_id')
                  ->on('consumidor')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('usina', function (Blueprint $table) {
            $table->dropForeign(['con_id']);
            $table->dropColumn('con_id');
        });
    }
};
