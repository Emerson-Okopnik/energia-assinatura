<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        // 1. Atualiza os registros existentes para 0
        DB::table('valor_acumulado_reserva')->whereNull('total')->update(['total' => 0]);

        // 2. Altera a coluna para ter default e not null
        Schema::table('valor_acumulado_reserva', function (Blueprint $table) {
            $table->float('total')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void {
        Schema::table('valor_acumulado_reserva', function (Blueprint $table) {
            $table->float('total')->nullable()->default(null)->change();
        });
    }
};
