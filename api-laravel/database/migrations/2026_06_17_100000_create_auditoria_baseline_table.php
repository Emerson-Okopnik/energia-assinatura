<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_baseline', function (Blueprint $table) {
            $table->increments('ab_id');
            $table->unsignedInteger('usi_id');
            $table->date('competencia');
            $table->decimal('valor_sistema_antes', 12, 2)->nullable();
            $table->decimal('valor_pago', 12, 2)->nullable();
            $table->decimal('fatura_informada', 12, 2)->nullable();
            $table->decimal('consumo_informado', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['usi_id', 'competencia']);
            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_baseline');
    }
};
