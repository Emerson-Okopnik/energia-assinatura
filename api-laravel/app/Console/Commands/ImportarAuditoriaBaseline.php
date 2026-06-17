<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditoriaBaseline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa o baseline da auditoria: "Antes" (1º dump) e "Pago" (planilha) por usina/mês.
 * CSV com cabeçalho uc,competencia,valor. --antes grava valor_sistema_antes; --pago grava
 * valor_pago. Idempotente (updateOrCreate por usi_id+competencia). UC sem usina = ignorada.
 */
final class ImportarAuditoriaBaseline extends Command
{
    protected $signature = 'auditoria:importar-baseline {--antes= : CSV uc,competencia,valor (sistema antes)} {--pago= : CSV uc,competencia,valor (efetivamente pago)}';

    protected $description = 'Importa o baseline da auditoria (Antes do 1º dump + Pago da planilha).';

    public function handle(): int
    {
        $antes = $this->option('antes');
        $pago = $this->option('pago');

        if (! $antes && ! $pago) {
            $this->error('Informe ao menos --antes ou --pago.');

            return self::FAILURE;
        }

        if ($antes) {
            $this->importar((string) $antes, 'valor_sistema_antes');
        }
        if ($pago) {
            $this->importar((string) $pago, 'valor_pago');
        }

        return self::SUCCESS;
    }

    private function importar(string $arquivo, string $campo): void
    {
        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return;
        }

        $handle = fopen($arquivo, 'r');
        if ($handle === false) {
            $this->error("Não foi possível abrir: {$arquivo}");

            return;
        }

        $cabecalho = fgetcsv($handle);
        $idx = array_flip(array_map('trim', $cabecalho ?: []));
        foreach (['uc', 'competencia', 'valor'] as $c) {
            if (! isset($idx[$c])) {
                $this->error("Coluna obrigatória ausente ({$c}) em {$arquivo}.");
                fclose($handle);

                return;
            }
        }

        // mapa uc -> usi_id (uma query)
        $mapa = DB::table('usina')->pluck('usi_id', 'uc');

        $gravados = 0;
        $ignorados = 0;
        while (($linha = fgetcsv($handle)) !== false) {
            $uc = trim((string) ($linha[$idx['uc']] ?? ''));
            if ($uc === '') {
                continue;
            }
            $usiId = $mapa[$uc] ?? null;
            if ($usiId === null) {
                $ignorados++;
                continue;
            }
            $competencia = $this->competencia((string) $linha[$idx['competencia']]);
            $valor = (float) str_replace(',', '.', (string) $linha[$idx['valor']]);

            AuditoriaBaseline::updateOrCreate(
                ['usi_id' => (int) $usiId, 'competencia' => $competencia],
                [$campo => $valor],
            );
            $gravados++;
        }
        fclose($handle);

        $this->info("{$campo}: {$gravados} gravados, {$ignorados} ignorados (UC sem usina) — {$arquivo}");
    }

    private function competencia(string $valor): string
    {
        $valor = trim($valor);

        return strlen($valor) === 7 ? $valor . '-01' : $valor;
    }
}
