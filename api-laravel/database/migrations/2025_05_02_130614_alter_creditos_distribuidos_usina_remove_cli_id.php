<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            // Remove a foreign key antes de remover a coluna
            $table->dropForeign(['cli_id']);
            $table->dropColumn('cli_id');
        });
    }

    public function down(): void
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->unsignedBigInteger('cli_id')->after('fa_id');

            $table->foreign('cli_id')
                  ->references('cli_id')
                  ->on('cliente')
                  ->onDelete('cascade');
        });
    }
};
