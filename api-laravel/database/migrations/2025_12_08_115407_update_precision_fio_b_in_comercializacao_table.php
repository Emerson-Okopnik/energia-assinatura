<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Sintaxe ALTER ... USING é específica do Postgres; em sqlite (testes) é no-op.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<SQL
            ALTER TABLE comercializacao
                ALTER COLUMN fio_b TYPE DECIMAL(12,5) USING fio_b::DECIMAL(12,5),
                ALTER COLUMN fio_b SET DEFAULT 0.00000,
                ALTER COLUMN fio_b SET NOT NULL
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<SQL
            ALTER TABLE comercializacao
                ALTER COLUMN fio_b TYPE DECIMAL(10,4) USING fio_b::DECIMAL(10,4),
                ALTER COLUMN fio_b SET DEFAULT 0.0000,
                ALTER COLUMN fio_b SET NOT NULL
        SQL);
    }
};