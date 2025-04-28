<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('consumidor', function (Blueprint $table) {
            $table->renameColumn('andamento_processo', 'alocacao');
        });
    }

    public function down()
    {
        Schema::table('consumidor', function (Blueprint $table) {
            $table->renameColumn('alocacao', 'andamento_processo');
        });
    }
};
