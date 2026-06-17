<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatura_fonte', function (Blueprint $table) {
            $table->id();
            $table->string('uc');
            $table->date('competencia');
            $table->decimal('fatura_energia', 12, 2)->default(0);
            $table->unique(['uc', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_fonte');
    }
};
