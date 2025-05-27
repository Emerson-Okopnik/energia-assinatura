<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('usina', function (Blueprint $table) {
            // Remove a foreign key e a coluna antiga se ela existir
            $table->dropForeign(['cdu_id']);
            $table->dropColumn('cdu_id');
        });
    }

    public function down(): void {
        Schema::table('usina', function (Blueprint $table) {
            $table->unsignedBigInteger('cdu_id')->nullable();
            $table->foreign('cdu_id')->references('cdu_id')->on('creditos_distribuidos_usina')->onDelete('cascade');
        });
    }
};
