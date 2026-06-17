<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Faturamento\FaturamentoService;
use App\Models\FaturaFonte;
use App\Models\GeracaoFaturamentoPdf;
use App\Models\Usina;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrige a fatura_energia dos meses faturados com fatura 0 pelo backfill.
 *
 * Precedência da fatura por (usina, competência):
 *   1) geracao_faturamento_pdf.fatura_energia de produção, se > 0 (preserva lançamento manual);
 *   2) fatura_fonte (derivada do dump antigo);
 *   3) 0 (sem fonte).
 *
 * Recalcula via FaturamentoService::calcularMes (motor único; expiração PAGA — PAGA TUDO).
 * Idempotente, guard de competência futura, --dry-run.
 */
final class CorrigirFaturaEnergia extends Command
{
    protected $signature = 'faturamento:corrigir-fatura
        {--usina= : UC específica}
        {--dry-run : não grava, só relatório}';

    protected $description = 'Re-materializa os meses com a fatura real (precedência prod>fonte>0). PAGA TUDO.';

    /** @var array<int, string> */
    private const MESES = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    public function __construct(private readonly FaturamentoService $faturamento)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ucFiltro = $this->option('usina');

        $usinas = DB::table('usina')
            ->when($ucFiltro, fn ($q) => $q->where('uc', $ucFiltro))
            ->orderBy('usi_id')
            ->get(['usi_id', 'uc']);

        $hoje = now();
        $anoCorrente = (int) $hoje->year;
        $mesCorrente = (int) $hoje->month;

        $linhas = [];

        $processar = function () use ($usinas, $dryRun, $anoCorrente, $mesCorrente, &$linhas): void {
            foreach ($usinas as $u) {
                $usiId = (int) $u->usi_id;
                $modelo = Usina::with(['comercializacao', 'dadoGeracao'])->find($usiId);
                if ($modelo === null) {
                    continue;
                }

                $faturaProd = $this->faturasDeProd($usiId);
                $faturaFonte = $this->faturasDeFonte((string) $u->uc);
                $valorAntes = $this->valoresAntes($usiId);

                foreach ($this->timeline($usiId) as $mes) {
                    [$ano, $num, $geracao] = [$mes['ano'], $mes['mes'], $mes['geracao']];
                    if ($ano > $anoCorrente || ($ano === $anoCorrente && $num > $mesCorrente)) {
                        continue;
                    }

                    $ym = sprintf('%04d-%02d', $ano, $num);
                    $fp = $faturaProd[$ym] ?? 0.0;
                    if ($fp > 0.0) {
                        $fatura = $fp;
                        $origem = 'prod';
                    } elseif (($faturaFonte[$ym] ?? 0.0) > 0.0) {
                        $fatura = $faturaFonte[$ym];
                        $origem = 'dump';
                    } else {
                        $fatura = 0.0;
                        $origem = 'zero';
                    }

                    $resp = $this->faturamento->calcularMes(
                        $modelo,
                        $ano,
                        $num,
                        ['geracao_bruta_kwh' => $geracao, 'fatura_energia' => $fatura],
                        persistir: ! $dryRun,
                        idempotencyKey: sprintf('corrigir-fatura:%d:%s', $usiId, $ym),
                    );

                    $linhas[] = [
                        'uc' => (string) $u->uc,
                        'competencia' => $ym,
                        'valor_antes' => $valorAntes[$ym] ?? 0.0,
                        'valor_depois' => $resp->resultado->valorFinal->emReais(),
                        'fatura_origem' => $origem,
                    ];
                }
            }
        };

        $dryRun ? $processar() : DB::transaction($processar);

        $this->emitirCsv($linhas, $dryRun);

        return self::SUCCESS;
    }

    /** @return array<string, float> ym => geracao_faturamento_pdf.valor_final atual (antes) */
    private function valoresAntes(int $usiId): array
    {
        $out = [];
        foreach (GeracaoFaturamentoPdf::where('usi_id', $usiId)->get() as $r) {
            $out[Carbon::parse($r->competencia)->format('Y-m')] = (float) $r->valor_final;
        }

        return $out;
    }

    /** @return array<string, float> ym => fatura_energia de prod */
    private function faturasDeProd(int $usiId): array
    {
        $out = [];
        foreach (GeracaoFaturamentoPdf::where('usi_id', $usiId)->get() as $r) {
            $out[Carbon::parse($r->competencia)->format('Y-m')] = (float) $r->fatura_energia;
        }

        return $out;
    }

    /** @return array<string, float> ym => fatura derivada do dump */
    private function faturasDeFonte(string $uc): array
    {
        $out = [];
        foreach (FaturaFonte::where('uc', $uc)->get() as $r) {
            $out[Carbon::parse($r->competencia)->format('Y-m')] = (float) $r->fatura_energia;
        }

        return $out;
    }

    /** @return array<int, array{ano:int, mes:int, geracao:float}> cronológico ASC */
    private function timeline(int $usiId): array
    {
        $linhas = DB::table('dados_geracao_real_usina as dgru')
            ->join('dados_geracao_real as dgr', 'dgr.dgr_id', '=', 'dgru.dgr_id')
            ->where('dgru.usi_id', $usiId)
            ->orderBy('dgru.ano')
            ->get(['dgru.ano', 'dgr.*']);

        $timeline = [];
        foreach ($linhas as $linha) {
            foreach (self::MESES as $num => $nome) {
                $g = $linha->{$nome};
                if ($g === null || (float) $g == 0.0) {
                    continue;
                }
                $timeline[] = ['ano' => (int) $linha->ano, 'mes' => $num, 'geracao' => (float) $g];
            }
        }

        usort($timeline, static fn ($a, $b) => [$a['ano'], $a['mes']] <=> [$b['ano'], $b['mes']]);

        return $timeline;
    }

    /** @param array<int, array<string, mixed>> $linhas */
    private function emitirCsv(array $linhas, bool $dryRun): void
    {
        $dir = storage_path('reconstrucao');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $caminho = $dir . '/correcao-fatura-antes-depois.csv';
        $handle = fopen($caminho, 'w');
        fputcsv($handle, ['uc', 'competencia', 'valor_antes', 'valor_depois', 'delta', 'fatura_origem']);

        $totalDelta = 0.0;
        $mudaram = 0;
        foreach ($linhas as $l) {
            $delta = round($l['valor_depois'] - $l['valor_antes'], 2);
            $totalDelta += $delta;
            if (abs($delta) >= 0.01) {
                $mudaram++;
            }
            fputcsv($handle, [
                $l['uc'], $l['competencia'],
                number_format($l['valor_antes'], 2, '.', ''),
                number_format($l['valor_depois'], 2, '.', ''),
                number_format($delta, 2, '.', ''),
                $l['fatura_origem'],
            ]);
        }
        fclose($handle);

        $this->info('=== CORREÇÃO DE FATURA ' . ($dryRun ? '(DRY-RUN — nada gravado)' : '(GRAVADO)') . ' ===');
        $this->line('Competências processadas: ' . count($linhas));
        $this->line('Competências que mudaram de valor: ' . $mudaram);
        $this->line('Delta total (depois - antes): R$ ' . number_format($totalDelta, 2, ',', '.'));
        $this->info('CSV: ' . $caminho);
    }
}
