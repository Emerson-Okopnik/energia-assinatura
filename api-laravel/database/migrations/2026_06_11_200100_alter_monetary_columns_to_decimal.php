<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converte colunas de energia/dinheiro de float para decimal (REGRAS_DE_CALCULO.md §11).
 *
 * Precisão por tipo:
 *   - kWh        -> decimal(14,4)
 *   - R$         -> decimal(12,2)
 *   - tarifa     -> decimal(12,6)
 *
 * Usa `change()`, nativo no Laravel 11/12 (não requer doctrine/dbal). No Postgres
 * a conversão float -> numeric é segura. No down() volta para float/double, mantendo
 * a migration reversível.
 */
return new class extends Migration
{
    /** @var string[] Os 12 meses, nomes de coluna usados em várias tabelas. */
    private const MESES = [
        'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    public function up(): void
    {
        // valor_acumulado_reserva: kWh (12 meses + total) -> decimal(14,4)
        Schema::table('valor_acumulado_reserva', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->decimal($mes, 14, 4)->default(0)->change();
            }
            // total é NOT NULL desde 2025_05_08_141617; manter sem nullable() para não regredir.
            $table->decimal('total', 14, 4)->default(0)->change();
        });

        // creditos_distribuidos: R$ (12 meses) -> decimal(12,2)
        Schema::table('creditos_distribuidos', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->decimal($mes, 12, 2)->default(0)->change();
            }
        });

        // faturamento_usina: R$ (12 meses) -> decimal(12,2)
        Schema::table('faturamento_usina', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->decimal($mes, 12, 2)->default(0)->change();
            }
        });

        // dados_geracao_real: kWh (12 meses, nullable) -> decimal(14,4)
        Schema::table('dados_geracao_real', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->decimal($mes, 14, 4)->nullable()->change();
            }
        });

        // dados_geracao: kWh (12 meses + media + menor_geracao) -> decimal(14,4)
        Schema::table('dados_geracao', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->decimal($mes, 14, 4)->change();
            }
            $table->decimal('media', 14, 4)->change();
            $table->decimal('menor_geracao', 14, 4)->change();
        });

        // comercializacao: tarifa e R$
        Schema::table('comercializacao', function (Blueprint $table) {
            $table->decimal('valor_kwh', 12, 6)->change();
            $table->decimal('valor_fixo', 12, 2)->change();
            $table->decimal('valor_final_media', 12, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('valor_acumulado_reserva', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->float($mes)->default(0)->change();
            }
            $table->float('total')->nullable()->default(0)->change();
        });

        Schema::table('creditos_distribuidos', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->float($mes)->default(0)->change();
            }
        });

        Schema::table('faturamento_usina', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->float($mes)->default(0)->change();
            }
        });

        Schema::table('dados_geracao_real', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->float($mes)->nullable()->change();
            }
        });

        Schema::table('dados_geracao', function (Blueprint $table) {
            foreach (self::MESES as $mes) {
                $table->float($mes)->change();
            }
            $table->float('media')->change();
            $table->float('menor_geracao')->change();
        });

        Schema::table('comercializacao', function (Blueprint $table) {
            $table->float('valor_kwh')->change();
            $table->float('valor_fixo')->change();
            $table->float('valor_final_media')->change();
        });
    }
};
