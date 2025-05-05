<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove relação antiga de usina → creditos_distribuidos_usina
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->dropForeign(['usi_id']);
            $table->dropColumn('usi_id');
        });

        // Adiciona cdu_id em usina como nova FK
        Schema::table('usina', function (Blueprint $table) {
            $table->unsignedBigInteger('cdu_id')->nullable()->after('com_id');

            $table->foreign('cdu_id')
                  ->references('cdu_id')
                  ->on('creditos_distribuidos_usina')
                  ->onDelete('set null'); // ou cascade, conforme regra de negócio
        });
    }

    public function down(): void
    {
        // Reverte: remove cdu_id de usina
        Schema::table('usina', function (Blueprint $table) {
            $table->dropForeign(['cdu_id']);
            $table->dropColumn('cdu_id');
        });

        // Reinsere usi_id em creditos_distribuidos_usina
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->unsignedBigInteger('usi_id')->after('ano');

            $table->foreign('usi_id')
                  ->references('usi_id')
                  ->on('usina')
                  ->onDelete('cascade');
        });
    }
};
