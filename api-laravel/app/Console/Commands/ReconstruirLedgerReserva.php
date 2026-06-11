<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Faturamento\FaturamentoService;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Models\CreditoLedger;
use App\Models\Usina;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 — BACKFILL do ledger de reserva (REGRAS_DE_CALCULO.md §6, §7, §8, §12).
 *
 * RE-MATERIALIZA, por usina e por mês em ordem CRONOLÓGICA, o estado completo de
 * faturamento a partir da GERAÇÃO REAL (não dos saldos atuais, que estão
 * corrompidos — §12), delegando ao MESMO motor que a tela usa em preview/save:
 * {@see FaturamentoService::calcularMes} com `persistir: true`.
 *
 * Por que delegar (DRY, P1): o backfill antigo escrevia SÓ no `credito_ledger`,
 * deixando as colunas materializadas que a tela lê (`creditos_distribuidos.<mes>`,
 * `faturamento_usina.<mes>`, `valor_acumulado_reserva.<mes>`/`.total`) com o dado
 * antigo/buggado de produção — 65% das usinas divergiam do que o motor calcula.
 * Como `calcularMes` grava, na MESMA transação, o ledger + as colunas materializadas
 * + o cache de PDF a partir do resultado único do motor, reconstruir por aqui faz
 * as colunas ficarem IDÊNTICAS ao preview/projeção, eliminando a divergência.
 *
 * Garantias de correção:
 *   - Ordem cronológica + `lotesEmAbertoNoInicioDe` (reserva ponto-no-tempo lida do
 *     ledger): cada mês é calculado contra o estado correto da reserva, montado pelos
 *     meses anteriores já persistidos nesta mesma rodada.
 *   - Idempotência: `calcularMes` limpa os lançamentos do evento e faz updateOrCreate
 *     por competência nas colunas/cache — re-rodar produz o mesmo estado.
 *   - Mês sem geração (0/null) NÃO escreve nada (não entra na timeline).
 *
 * Entradas por mês: geracao_bruta de `dados_geracao_real` (timeline); consumo de
 * `dados_consumo_usina` (dedup mais recente, resolvido dentro do FaturamentoService);
 * fatura_energia = 0 — o histórico não tem fatura real, então a parcela de fatura é
 * neutra no backfill (a tela permite reabrir o mês e informá-la depois, §9).
 *
 * Esta classe é a camada de APLICAÇÃO: orquestra Eloquent + serviço de aplicação.
 */
final class ReconstruirLedgerReserva extends Command
{
    protected $signature = 'ledger:reconstruir
        {--usina= : UC específica}
        {--dry-run : não grava, só relatório}
        {--truncate : limpa o ledger antes}';

    protected $description = 'Reconstrói o ledger de reserva a partir da geração real (backfill FIFO cross-ano).';

    private const PRAZO_VENCIMENTO_DIAS = 180;

    /** @var array<int, string> */
    private const MESES = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    public function __construct(
        private readonly FaturamentoService $faturamento,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ucFiltro = $this->option('usina');
        $truncate = (bool) $this->option('truncate');

        $usinas = $this->carregarUsinas($ucFiltro);

        if ($usinas->isEmpty()) {
            $this->warn('Nenhuma usina encontrada para os filtros informados.');

            return self::SUCCESS;
        }

        $reconciliar = function () use ($usinas, $dryRun, $truncate, $ucFiltro): array {
            // Truncate dentro da MESMA transação da gravação: um crash não deixa
            // o ledger vazio sem repovoamento (rollback restaura tudo).
            if ($truncate && ! $dryRun) {
                $this->aplicarTruncate($ucFiltro);
            }

            $linhas = [];
            foreach ($usinas as $usina) {
                $linhas[] = $this->reconstruirUsina($usina, $dryRun);
            }

            return $linhas;
        };

        $reconciliacao = $dryRun
            ? $reconciliar()
            : DB::transaction($reconciliar);

        $this->emitirRelatorio($reconciliacao, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Reconstrói uma usina RE-MATERIALIZANDO ledger + colunas via FaturamentoService.
     *
     * Itera a timeline em ordem cronológica e, para cada mês com geração, chama
     * {@see FaturamentoService::calcularMes} com `persistir: true`. Cada chamada lê a
     * reserva ponto-no-tempo do ledger (montada pelos meses já processados nesta
     * rodada) e grava, na mesma transação, ledger + colunas materializadas + cache
     * PDF — exatamente o que a tela lê. Assim as colunas ficam idênticas ao motor.
     *
     * @param object $usina linha com usi_id, uc, cliente, valor_kwh, media, menor_geracao
     *
     * @return array<string, mixed>
     */
    private function reconstruirUsina(object $usina, bool $dryRun): array
    {
        $usiId = (int) $usina->usi_id;

        $timeline = $this->montarTimeline($usiId);

        if ($timeline === []) {
            return $this->linhaReconciliacao($usina, 0.0, 0.0, 0, false);
        }

        // Em dry-run nada é gravado: calcularMes(persistir:false) por mês não altera
        // a reserva ponto-no-tempo (não escreve no ledger), então não simulamos o
        // encadeamento — apenas reportamos que a usina tem timeline a reconstruir.
        if ($dryRun) {
            return $this->linhaReconciliacao(
                $usina,
                0.0,
                $this->saldoLegado($usiId),
                count($timeline),
                false,
            );
        }

        $modelo = Usina::with(['comercializacao', 'dadoGeracao'])->findOrFail($usiId);

        $eventos = 0;
        foreach ($timeline as $mes) {
            $competencia = $mes['competencia'];

            // fatura_energia = 0: o histórico não tem fatura real (documentado no
            // cabeçalho). consumo NÃO é passado: o FaturamentoService resolve o
            // dados_consumo_usina dedup (mais recente) do ano internamente (§9).
            $this->faturamento->calcularMes(
                $modelo,
                $competencia->ano,
                $competencia->mes,
                ['geracao_bruta_kwh' => $mes['geracao'], 'fatura_energia' => 0.0],
                persistir: true,
                idempotencyKey: $this->idempotencyMes($usiId, $competencia),
            );

            $eventos++;
        }

        // Saldo final do ledger reconstruído (soma dos kwh não-estornados) e o legado,
        // para a reconciliação do relatório (§12).
        $saldoFinalLedger = round(
            (float) CreditoLedger::doUsina($usiId)->naoEstornado()->sum('kwh'),
            4,
        );

        return $this->linhaReconciliacao(
            $usina,
            $saldoFinalLedger,
            $this->saldoLegado($usiId),
            $eventos,
            false,
        );
    }

    /**
     * Idempotency-key determinística do snapshot de estorno por (usi_id, competência).
     * Re-rodar o backfill referencia a mesma chave (sem semântica de conflito aqui —
     * o snapshot é só auditoria; o estado é regravado de forma idempotente).
     */
    private function idempotencyMes(int $usiId, Competencia $competencia): string
    {
        return sprintf('backfill:%d:%04d-%02d', $usiId, $competencia->ano, $competencia->mes);
    }

    /**
     * Timeline cronológica (ano, mês, geração) da geração real, ASC.
     *
     * @return array<int, array{competencia: Competencia, geracao: float}>
     */
    private function montarTimeline(int $usiId): array
    {
        $linhas = DB::table('dados_geracao_real_usina as dgru')
            ->join('dados_geracao_real as dgr', 'dgr.dgr_id', '=', 'dgru.dgr_id')
            ->where('dgru.usi_id', $usiId)
            ->orderBy('dgru.ano')
            ->get(['dgru.ano', 'dgr.*']);

        $timeline = [];

        // Não processar competências futuras como geração realizada (ex.: jun/2027
        // lançado por engano hoje). Limita ao ano/mês corrente.
        $hoje = now();
        $anoCorrente = (int) $hoje->year;
        $mesCorrente = (int) $hoje->month;

        foreach ($linhas as $linha) {
            $ano = (int) $linha->ano;

            foreach (self::MESES as $numero => $nome) {
                $geracao = $linha->{$nome};

                if ($geracao === null || (float) $geracao == 0.0) {
                    continue;
                }

                if ($ano > $anoCorrente || ($ano === $anoCorrente && $numero > $mesCorrente)) {
                    continue;
                }

                $timeline[] = [
                    'competencia' => Competencia::de($ano, $numero),
                    'geracao' => (float) $geracao,
                ];
            }
        }

        usort(
            $timeline,
            static fn (array $a, array $b): int => $a['competencia']->comparar($b['competencia']),
        );

        return $timeline;
    }

    /**
     * Saldo legado para reconciliação (§12): soma o valor_acumulado_reserva.total
     * de TODOS os anos da usina. Pegar só o ano mais recente subestima o saldo
     * (a reserva legada não carrega o saldo entre anos de forma confiável — está
     * corrompida pelo desconto destrutivo), por isso somamos todos os pacotes.
     */
    private function saldoLegado(int $usiId): float
    {
        return (float) DB::table('creditos_distribuidos_usina as cdu')
            ->join('valor_acumulado_reserva as var', 'var.var_id', '=', 'cdu.var_id')
            ->where('cdu.usi_id', $usiId)
            ->whereNotNull('cdu.var_id')
            ->sum('var.total');
    }

    private function carregarUsinas(?string $ucFiltro): \Illuminate\Support\Collection
    {
        $query = DB::table('usina as u')
            ->join('comercializacao as c', 'c.com_id', '=', 'u.com_id')
            ->join('dados_geracao as d', 'd.dger_id', '=', 'u.dger_id')
            ->leftJoin('cliente as cli', 'cli.cli_id', '=', 'u.cli_id')
            ->select(
                'u.usi_id', 'u.uc', 'cli.nome as cliente',
                'c.valor_kwh', 'd.media', 'd.menor_geracao',
            )
            ->orderBy('u.usi_id');

        if ($ucFiltro !== null && $ucFiltro !== '') {
            $query->where('u.uc', $ucFiltro);
        }

        return $query->get();
    }

    private function aplicarTruncate(?string $ucFiltro): void
    {
        if ($ucFiltro !== null && $ucFiltro !== '') {
            $usiId = DB::table('usina')->where('uc', $ucFiltro)->value('usi_id');
            if ($usiId !== null) {
                CreditoLedger::query()->where('usi_id', $usiId)->delete();
            }

            return;
        }

        CreditoLedger::query()->delete();
    }

    /**
     * @param array<int, array<string, mixed>> $reconciliacao
     */
    private function emitirRelatorio(array $reconciliacao, bool $dryRun): void
    {
        $dir = storage_path('reconstrucao');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $caminho = $dir . '/reconciliacao-ledger.csv';

        $handle = fopen($caminho, 'w');
        fputcsv($handle, [
            'UC', 'Cliente', 'Saldo_Final_Ledger_kWh', 'Saldo_Legado_kWh',
            'Diferenca_kWh', 'Bate', 'Teve_Deficit_Pago', 'Lancamentos',
        ]);

        $batem = 0;
        $divergem = 0;
        $divergencias = [];

        foreach ($reconciliacao as $linha) {
            $bate = abs($linha['diferenca']) < 0.5;
            $bate ? $batem++ : $divergem++;

            if (! $bate) {
                $divergencias[] = $linha;
            }

            fputcsv($handle, [
                $linha['uc'],
                $linha['cliente'],
                number_format($linha['saldo_ledger'], 4, '.', ''),
                number_format($linha['saldo_legado'], 4, '.', ''),
                number_format($linha['diferenca'], 4, '.', ''),
                $bate ? 'SIM' : 'NAO',
                $linha['tem_saldo_inicial'] ? 'SIM' : 'NAO',
                $linha['lancamentos'],
            ]);
        }

        fclose($handle);

        usort(
            $divergencias,
            static fn (array $a, array $b): int => abs($b['diferenca']) <=> abs($a['diferenca']),
        );

        $this->newLine();
        $this->info('=== RECONCILIAÇÃO DO LEDGER ' . ($dryRun ? '(DRY-RUN — nada gravado)' : '(GRAVADO)') . ' ===');
        $this->line('Usinas processadas: ' . count($reconciliacao));
        $this->line('Batem com o legado: ' . $batem);
        $this->line('Divergem do legado: ' . $divergem);
        $this->line('Tiveram déficit pago: ' . count(array_filter($reconciliacao, static fn ($l) => $l['tem_saldo_inicial'])));

        if ($divergencias !== []) {
            $this->newLine();
            $this->line('Maiores divergências (saldo ledger vs legado, kWh):');
            foreach (array_slice($divergencias, 0, 15) as $d) {
                $this->line(sprintf(
                    '  UC %-14s %-22s ledger %12.2f  legado %12.2f  dif %12.2f',
                    $d['uc'],
                    mb_substr((string) $d['cliente'], 0, 22),
                    $d['saldo_ledger'],
                    $d['saldo_legado'],
                    $d['diferenca'],
                ));
            }
        }

        $this->newLine();
        $this->info('CSV: ' . $caminho);
    }

    /**
     * @return array<string, mixed>
     */
    private function linhaReconciliacao(
        object $usina,
        float $saldoLedger,
        float $saldoLegado,
        int $lancamentos,
        bool $temSaldoInicial,
    ): array {
        return [
            'uc' => (string) $usina->uc,
            'cliente' => $usina->cliente ?? '-',
            'saldo_ledger' => $saldoLedger,
            'saldo_legado' => $saldoLegado,
            'diferenca' => round($saldoLedger - $saldoLegado, 4),
            'lancamentos' => $lancamentos,
            'tem_saldo_inicial' => $temSaldoInicial,
        ];
    }
}
