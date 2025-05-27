<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->unsignedBigInteger('cli_id')->after('usi_id');

            $table->foreign('cli_id')
                ->references('cli_id')
                ->on('cliente')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('creditos_distribuidos_usina', function (Blueprint $table) {
            $table->dropForeign(['cli_id']);
            $table->dropColumn('cli_id');
        });
    }
};
