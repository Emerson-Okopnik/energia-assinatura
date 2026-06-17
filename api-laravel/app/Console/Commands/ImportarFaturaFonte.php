<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FaturaFonte;
use Illuminate\Console\Command;

final class ImportarFaturaFonte extends Command
{
    protected $signature = 'faturamento:importar-fatura-fonte {arquivo : caminho do CSV (uc,competencia,fatura_energia)}';

    protected $description = 'Importa a fatura-fonte (derivada do dump antigo) para a tabela fatura_fonte.';

    public function handle(): int
    {
        $arquivo = (string) $this->argument('arquivo');

        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $handle = fopen($arquivo, 'r');
        if ($handle === false) {
            $this->error("Não foi possível abrir o arquivo: {$arquivo}");

            return self::FAILURE;
        }
        $cabecalho = fgetcsv($handle);
        $idx = array_flip(array_map('trim', $cabecalho));

        foreach (['uc', 'competencia', 'fatura_energia'] as $coluna) {
            if (! isset($idx[$coluna])) {
                $this->error("Coluna obrigatória ausente no CSV: {$coluna}");
                fclose($handle);

                return self::FAILURE;
            }
        }

        $total = 0;
        while (($linha = fgetcsv($handle)) !== false) {
            $uc = trim((string) $linha[$idx['uc']]);
            if ($uc === '') {
                continue;
            }
            $competencia = $this->normalizarCompetencia((string) $linha[$idx['competencia']]);
            $fatura = (float) $linha[$idx['fatura_energia']];

            FaturaFonte::updateOrCreate(
                ['uc' => $uc, 'competencia' => $competencia],
                ['fatura_energia' => $fatura],
            );
            $total++;
        }
        fclose($handle);

        $this->info("Importadas {$total} linhas para fatura_fonte.");

        return self::SUCCESS;
    }

    private function normalizarCompetencia(string $valor): string
    {
        $valor = trim($valor);

        return strlen($valor) === 7 ? $valor . '-01' : $valor;
    }
}
