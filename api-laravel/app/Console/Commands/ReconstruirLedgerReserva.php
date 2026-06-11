<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\MotorFifo;
use App\Domain\Faturamento\Ledger\ServicoExpiracao;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Tarifa;
use App\Models\CreditoLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 — BACKFILL do ledger de reserva (REGRAS_DE_CALCULO.md §6, §7, §8, §12).
 *
 * Reconstrói o {@see CreditoLedger} de cada usina a partir da GERAÇÃO REAL mês a
 * mês (não dos saldos atuais, que estão corrompidos — §12), replicando a regra
 * canônica: excedente vira CREDITO; déficit consome a reserva via FIFO cross-ano
 * ({@see MotorFifo}); o que sobra e vence vira EXPIRACAO ({@see ServicoExpiracao}).
 *
 * As 21 usinas com déficit histórico maior que o excedente (saldo migrado, §12)
 * recebem um lançamento SALDO_INICIAL de abertura, detectado em dois passes: o
 * primeiro mede o déficit não atendido; o segundo re-roda o replay já com o lote
 * de abertura, para que os CONSUMO referenciem corretamente esse saldo.
 *
 * Esta classe é a camada de APLICAÇÃO: orquestra Eloquent + núcleo de domínio.
 * O domínio (app/Domain) permanece PURO — aqui ele é só consumido.
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
        private readonly MotorFifo $motorFifo = new MotorFifo(),
        private readonly ServicoExpiracao $servicoExpiracao = new ServicoExpiracao(),
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
     * Reconstrói uma usina e devolve a linha de reconciliação.
     *
     * @param object $usina linha com usi_id, uc, cliente, valor_kwh, media, menor_geracao
     *
     * @return array<string, mixed>
     */
    private function reconstruirUsina(object $usina, bool $dryRun): array
    {
        $usiId = (int) $usina->usi_id;
        $media = (float) $usina->media;
        $tarifa = Tarifa::de((float) $usina->valor_kwh);

        $timeline = $this->montarTimeline($usiId);

        if ($timeline === []) {
            return $this->linhaReconciliacao($usina, 0.0, 0.0, 0, false);
        }

        // Reserva começa em ZERO — fiel ao cadastro (não há saldo inicial: a reserva
        // sempre nasce em 0, ver CalculoGeracaoService::criarPacoteAnual). Um déficit
        // sem reserva é PAGO à concessionária (não compensado), então o "não atendido"
        // não gera lançamento de crédito — não existe SALDO_INICIAL/crédito migrado.
        $resultado = $this->replay($timeline, $media, $tarifa, Kwh::zero());

        $lancamentos = $resultado['lancamentos'];
        $saldoFinalLedger = $this->saldoFinal($lancamentos);

        if (! $dryRun) {
            $this->persistir($usiId, $lancamentos);
        }

        $legado = $this->saldoLegado($usiId);
        $deficitPagoKwh = $resultado['naoAtendidoTotal']->valor();

        return $this->linhaReconciliacao(
            $usina,
            $saldoFinalLedger,
            $legado,
            count($lancamentos),
            $deficitPagoKwh > 1e-6,
        );
    }

    /**
     * Replay cronológico mês a mês reusando o núcleo de domínio.
     *
     * Mantém o saldo vivo de cada origem num mapa mutável (LoteReserva é
     * imutável). A cada mês monta os LoteReserva a partir do saldo vivo, delega
     * o consumo ao MotorFifo e a expiração ao ServicoExpiracao, e registra os
     * lançamentos do ledger correspondentes.
     *
     * @param array<int, array{competencia: Competencia, geracao: float}> $timeline
     * @param Competencia|null $compAbertura competência do SALDO_INICIAL (mês anterior à 1ª geração)
     *
     * @return array{lancamentos: array<int, array<string, mixed>>, naoAtendidoTotal: Kwh}
     */
    private function replay(
        array $timeline,
        float $media,
        Tarifa $tarifa,
        Kwh $saldoAbertura,
        ?Competencia $compAbertura = null,
    ): array {
        /** @var array<string, array{competencia: Competencia, saldo: float, vencimento: \DateTimeImmutable, origemTipo: string}> $lotesVivos */
        $lotesVivos = [];
        $lancamentos = [];
        $naoAtendidoTotal = 0.0;

        // Lote de abertura (SALDO_INICIAL): mês anterior à primeira geração,
        // sem vencimento (não expira — preserva o crédito migrado, §12).
        if ($saldoAbertura->valor() > 1e-6 && $compAbertura !== null) {
            $abertura = $this->competenciaAnterior($compAbertura);
            $chave = (string) $abertura;
            $lotesVivos[$chave] = [
                'competencia' => $abertura,
                'saldo' => $saldoAbertura->valor(),
                'vencimento' => $this->vencimentoDistante(),
                'origemTipo' => CreditoLedger::TIPO_SALDO_INICIAL,
            ];
            $lancamentos[] = $this->lancamentoEntrada(
                CreditoLedger::TIPO_SALDO_INICIAL,
                $abertura,
                $abertura,
                $saldoAbertura,
                $tarifa,
                null,
            );
        }

        foreach ($timeline as $mes) {
            $evento = $mes['competencia'];
            $geracao = $mes['geracao'];

            if ($geracao >= $media) {
                $excedente = $geracao - $media;
                if ($excedente > 1e-6) {
                    $chave = (string) $evento;
                    $vencimento = $evento->vencimentoEmDias(self::PRAZO_VENCIMENTO_DIAS);
                    $lotesVivos[$chave] = [
                        'competencia' => $evento,
                        'saldo' => $excedente,
                        'vencimento' => $vencimento,
                        'origemTipo' => CreditoLedger::TIPO_CREDITO,
                    ];
                    $lancamentos[] = $this->lancamentoEntrada(
                        CreditoLedger::TIPO_CREDITO,
                        $evento,
                        $evento,
                        Kwh::de($excedente),
                        $tarifa,
                        $vencimento,
                    );
                }
            } else {
                $faltante = Kwh::de($media - $geracao);
                $lotes = $this->lotesReserva($lotesVivos);

                $consumo = $this->motorFifo->consumir($lotes, $faltante, $evento);
                $naoAtendidoTotal += $consumo['naoAtendidoKwh']->valor();

                foreach ($consumo['consumos'] as $c) {
                    $chave = (string) $c['origem'];
                    $lotesVivos[$chave]['saldo'] -= $c['kwh']->valor();

                    $lancamentos[] = $this->lancamentoSaida(
                        CreditoLedger::TIPO_CONSUMO,
                        $c['origem'],
                        $evento,
                        $c['kwh'],
                        $tarifa,
                    );
                }
            }

            // Expiração: aplicada DEPOIS do consumo (§7) sobre o saldo remanescente.
            $expiracao = $this->servicoExpiracao->aplicar(
                $this->lotesReserva($lotesVivos),
                $evento,
                $tarifa,
            );

            foreach ($expiracao['expirados'] as $e) {
                $chave = (string) $e['origem'];
                $lotesVivos[$chave]['saldo'] = 0.0;

                $lancamentos[] = $this->lancamentoSaida(
                    CreditoLedger::TIPO_EXPIRACAO,
                    $e['origem'],
                    $evento,
                    $e['kwh'],
                    $tarifa,
                );
            }
        }

        return [
            'lancamentos' => $lancamentos,
            'naoAtendidoTotal' => Kwh::de($naoAtendidoTotal),
        ];
    }

    /**
     * Converte o mapa de saldos vivos em LoteReserva (entrada do domínio),
     * descartando origens sem saldo positivo (invariante §8: saldo nunca < 0).
     *
     * @param array<string, array{competencia: Competencia, saldo: float, vencimento: \DateTimeImmutable, origemTipo: string}> $lotesVivos
     *
     * @return LoteReserva[]
     */
    private function lotesReserva(array $lotesVivos): array
    {
        $lotes = [];

        foreach ($lotesVivos as $lote) {
            if ($lote['saldo'] <= 1e-6) {
                continue;
            }

            $lotes[] = LoteReserva::de(
                $lote['competencia'],
                Kwh::de($lote['saldo']),
                $lote['vencimento'],
            );
        }

        return $lotes;
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
     * Persiste os lançamentos numa transação, de forma IDEMPOTENTE.
     *
     * Resolve `ref_lancamento_id` (rastreabilidade FIFO) após gravar as ENTRADAS
     * (CREDITO/SALDO_INICIAL): as SAÍDAS (CONSUMO/EXPIRACAO) apontam para o cl_id
     * da entrada de mesma origem. updateOrCreate por idempotency_key garante que
     * re-rodar não duplica.
     *
     * @param array<int, array<string, mixed>> $lancamentos
     */
    private function persistir(int $usiId, array $lancamentos): void
    {
        DB::transaction(function () use ($usiId, $lancamentos): void {
            $idsPorOrigem = [];

            // 1) ENTRADAS primeiro, para resolver as referências das saídas.
            foreach ($lancamentos as $lancamento) {
                if (! in_array($lancamento['tipo'], [
                    CreditoLedger::TIPO_CREDITO,
                    CreditoLedger::TIPO_SALDO_INICIAL,
                ], true)) {
                    continue;
                }

                $registro = $this->upsert($usiId, $lancamento);
                $idsPorOrigem[$lancamento['competencia_origem']] = (int) $registro->cl_id;
            }

            // 2) SAÍDAS referenciando a entrada de origem.
            foreach ($lancamentos as $lancamento) {
                if (! in_array($lancamento['tipo'], [
                    CreditoLedger::TIPO_CONSUMO,
                    CreditoLedger::TIPO_EXPIRACAO,
                ], true)) {
                    continue;
                }

                $lancamento['ref_lancamento_id'] = $idsPorOrigem[$lancamento['competencia_origem']] ?? null;
                $this->upsert($usiId, $lancamento);
            }
        });
    }

    /**
     * @param array<string, mixed> $lancamento
     */
    private function upsert(int $usiId, array $lancamento): CreditoLedger
    {
        $chave = $this->idempotencyKey($usiId, $lancamento);

        return CreditoLedger::updateOrCreate(
            ['idempotency_key' => $chave],
            array_merge($lancamento, [
                'usi_id' => $usiId,
                'idempotency_key' => $chave,
            ]),
        );
    }

    /**
     * Idempotency-key determinística por (usi_id, tipo, origem, evento).
     *
     * Garante que re-rodar o backfill produz exatamente o mesmo estado. Para o
     * par CONSUMO/EXPIRACAO de mesma origem×evento o tipo distingue as linhas.
     *
     * @param array<string, mixed> $lancamento
     */
    private function idempotencyKey(int $usiId, array $lancamento): string
    {
        return sprintf(
            'backfill:%d:%s:%s:%s',
            $usiId,
            $lancamento['tipo'],
            $lancamento['competencia_origem'],
            $lancamento['competencia_evento'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function lancamentoEntrada(
        string $tipo,
        Competencia $origem,
        Competencia $evento,
        Kwh $kwh,
        Tarifa $tarifa,
        ?\DateTimeImmutable $vencimento,
    ): array {
        return [
            'tipo' => $tipo,
            'competencia_origem' => $this->dataCompetencia($origem),
            'competencia_evento' => $this->dataCompetencia($evento),
            'kwh' => round($kwh->valor(), 4),
            'tarifa_kwh' => $tarifa->valor(),
            'valor_reais' => round($kwh->vezesTarifa($tarifa)->emReais(), 2),
            'vencimento' => $vencimento?->format('Y-m-d'),
            'ref_lancamento_id' => null,
        ];
    }

    /**
     * Saída: kwh NEGATIVO (§8), aponta para o CREDITO de origem (ref resolvida na persistência).
     *
     * @return array<string, mixed>
     */
    private function lancamentoSaida(
        string $tipo,
        Competencia $origem,
        Competencia $evento,
        Kwh $kwh,
        Tarifa $tarifa,
    ): array {
        return [
            'tipo' => $tipo,
            'competencia_origem' => $this->dataCompetencia($origem),
            'competencia_evento' => $this->dataCompetencia($evento),
            'kwh' => -round($kwh->valor(), 4),
            'tarifa_kwh' => $tarifa->valor(),
            'valor_reais' => -round($kwh->vezesTarifa($tarifa)->emReais(), 2),
            'vencimento' => null,
            'ref_lancamento_id' => null,
        ];
    }

    /**
     * Saldo final do ledger reconstruído = soma dos kwh (entradas − saídas),
     * que é exatamente o saldo remanescente da reserva.
     *
     * @param array<int, array<string, mixed>> $lancamentos
     */
    private function saldoFinal(array $lancamentos): float
    {
        $total = 0.0;

        foreach ($lancamentos as $lancamento) {
            $total += (float) $lancamento['kwh'];
        }

        return round($total, 4);
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

    private function competenciaAnterior(Competencia $competencia): Competencia
    {
        $ano = $competencia->ano;
        $mes = $competencia->mes - 1;

        if ($mes < 1) {
            $mes = 12;
            $ano--;
        }

        return Competencia::de($ano, $mes);
    }

    private function dataCompetencia(Competencia $competencia): string
    {
        return sprintf('%04d-%02d-01', $competencia->ano, $competencia->mes);
    }

    /**
     * Vencimento "distante" para o SALDO_INICIAL: não deve expirar (§12 — o crédito
     * migrado é preservado). 100 anos à frente cobre todo horizonte de replay.
     */
    private function vencimentoDistante(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2200-01-01 00:00:00');
    }
}
