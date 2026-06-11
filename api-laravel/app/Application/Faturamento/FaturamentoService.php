<?php

declare(strict_types=1);

namespace App\Application\Faturamento;

use App\Application\Faturamento\DTO\RespostaCalculoMes;
use App\Domain\Faturamento\Calculo\CalculadoraGeracaoLinear;
use App\Domain\Faturamento\Calculo\DescontoRede;
use App\Domain\Faturamento\Contracts\LedgerRepository;
use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\DTO\ResultadoCalculoMes;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use App\Domain\Faturamento\ValueObject\Reais;
use App\Domain\Faturamento\ValueObject\Tarifa;
use App\Models\CreditoLedger;
use App\Models\CreditosDistribuidos;
use App\Models\CreditosDistribuidosUsina;
use App\Models\DadoConsumo;
use App\Models\DadoConsumoUsina;
use App\Models\DadosGeracaoReal;
use App\Models\DadosGeracaoRealUsina;
use App\Models\DemonstrativoCreditosPdf;
use App\Models\FaturamentoUsina;
use App\Models\GeracaoFaturamentoPdf;
use App\Models\HistoricoEstorno;
use App\Models\Usina;
use App\Models\ValorAcumuladoReserva;
use Illuminate\Support\Facades\DB;

/**
 * Camada de APLICAÇÃO do faturamento de geração (PLANO_REDESENHO.md Fase 4).
 *
 * Orquestra o NÚCLEO ÚNICO ({@see CalculadoraGeracaoLinear}) — NÃO recalcula
 * fórmula alguma (DRY: a fórmula vive só no domínio). Responsabilidades:
 *
 *   1. Derivar os parâmetros da usina (tarifa, média, menor_geração, fio_b,
 *      percentual_lei, rede) — REGRAS_DE_CALCULO.md §3-§5.
 *   2. Calcular a GERAÇÃO LÍQUIDA no backend (§9) via {@see DescontoRede}.
 *   3. Carregar os lotes em aberto da reserva via {@see LedgerRepository} (DIP).
 *   4. Chamar a Calculadora e devolver o resultado (preview) ou persistir.
 *
 * Persistência (§8, §10): grava os lançamentos imutáveis no `credito_ledger`
 * (CREDITO/CONSUMO/EXPIRACAO) numa transação, atualiza as colunas materializadas
 * (cache de leitura) e cria um snapshot em HistoricoEstorno para estorno.
 */
final class FaturamentoService
{
    /** @var array<int, string> */
    private const MESES = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    private const PRAZO_VENCIMENTO_DIAS = 180;

    public function __construct(
        private readonly LedgerRepository $ledger,
        private readonly CalculadoraGeracaoLinear $calculadora = new CalculadoraGeracaoLinear(),
    ) {
    }

    /**
     * Calcula (e opcionalmente persiste) o faturamento de um mês.
     *
     * @param array<string, mixed> $input  Entradas manuais do operador. Chaves:
     *        geracao_bruta_kwh|mesGeracao_kwh, consumo (opcional), fatura_energia,
     *        adicional_cuo. valorPago_mes (legado) é IGNORADO — agora é calculado.
     */
    public function calcularMes(
        Usina $usina,
        int $ano,
        int $mes,
        array $input,
        bool $persistir,
        ?int $userId = null,
        ?string $idempotencyKey = null,
    ): RespostaCalculoMes {
        $mesNome = self::MESES[$mes] ?? null;
        if ($mesNome === null) {
            throw new \InvalidArgumentException("Mês inválido: {$mes}.");
        }

        $competencia = Competencia::de($ano, $mes);

        [$entrada, $consumoKwh, $rede, $descontoKwh] =
            $this->montarEntrada($usina, $competencia, $mesNome, $input);

        $lotes = $this->ledger->lotesEmAbertoDaUsina((int) $usina->usi_id);
        $saldoAntes = $this->somarLotes($lotes);

        $resultado = $this->calculadora->calcular($entrada, $lotes);

        $saldoDepois = $this->saldoReservaDepois($saldoAntes, $resultado);

        if (! $persistir) {
            return new RespostaCalculoMes(
                usiId: (int) $usina->usi_id,
                ano: $ano,
                mes: $mes,
                mesNome: $mesNome,
                entrada: $entrada,
                resultado: $resultado,
                consumoKwh: $consumoKwh,
                rede: $rede,
                descontoRedeKwh: $descontoKwh,
                persistido: false,
                saldoReservaAntesKwh: $saldoAntes,
                saldoReservaDepoisKwh: $saldoDepois,
            );
        }

        $ids = $this->persistir(
            $usina,
            $competencia,
            $mesNome,
            $entrada,
            $resultado,
            $saldoDepois,
            $userId,
            $idempotencyKey,
        );

        return new RespostaCalculoMes(
            usiId: (int) $usina->usi_id,
            ano: $ano,
            mes: $mes,
            mesNome: $mesNome,
            entrada: $entrada,
            resultado: $resultado,
            consumoKwh: $consumoKwh,
            rede: $rede,
            descontoRedeKwh: $descontoKwh,
            persistido: true,
            ledgerLancamentoIds: $ids,
            saldoReservaAntesKwh: $saldoAntes,
            saldoReservaDepoisKwh: $saldoDepois,
        );
    }

    /**
     * Deriva os parâmetros da usina e monta a EntradaCalculoMes do núcleo.
     * A geração líquida (§9) é calculada AQUI (backend), não no frontend.
     *
     * @param array<string, mixed> $input
     *
     * @return array{0: EntradaCalculoMes, 1: float, 2: ?string, 3: float}
     */
    private function montarEntrada(
        Usina $usina,
        Competencia $competencia,
        string $mesNome,
        array $input,
    ): array {
        $comercializacao = $usina->comercializacao;
        $dadoGeracao = $usina->dadoGeracao;

        if ($comercializacao === null) {
            throw new \InvalidArgumentException('Usina sem comercialização vinculada.');
        }
        if ($dadoGeracao === null) {
            throw new \InvalidArgumentException('Usina sem dados de geração projetada (média/menor).');
        }

        $bruta = $this->geracaoBruta($input);
        $consumoKwh = $this->consumo($usina, $competencia->ano, $mesNome, $input);
        $rede = $usina->rede;

        $brutaVo = Kwh::de($bruta);
        $liquida = DescontoRede::liquida($brutaVo, Kwh::de($consumoKwh), $rede);
        $descontoKwh = max($consumoKwh - DescontoRede::kwhPorTipo($rede), 0.0);

        $entrada = new EntradaCalculoMes(
            geracaoLiquidaKwh: $liquida,
            mediaKwh: Kwh::de((float) $dadoGeracao->media),
            menorGeracaoKwh: Kwh::de((float) $dadoGeracao->menor_geracao),
            geracaoBrutaKwh: $brutaVo,
            tarifa: Tarifa::de((float) $comercializacao->valor_kwh),
            fioB: (float) $comercializacao->fio_b,
            percentualLei: (float) $comercializacao->percentual_lei,
            faturaEnergia: Reais::deReais((float) ($input['fatura_energia'] ?? 0)),
            adicionalCuo: Reais::deReais((float) ($input['adicional_cuo'] ?? 0)),
            competencia: $competencia,
        );

        return [$entrada, $consumoKwh, $rede, $descontoKwh];
    }

    /**
     * Geração bruta do mês: aceita o nome novo (geracao_bruta_kwh) ou o legado
     * (mesGeracao_kwh). Exigida — não pode virar 0 silenciosamente (§9).
     *
     * @param array<string, mixed> $input
     */
    private function geracaoBruta(array $input): float
    {
        $bruta = $input['geracao_bruta_kwh'] ?? $input['mesGeracao_kwh'] ?? null;

        if ($bruta === null) {
            throw new \InvalidArgumentException(
                'Geração bruta ausente (geracao_bruta_kwh ou mesGeracao_kwh).'
            );
        }

        return (float) $bruta;
    }

    /**
     * Consumo da própria usina no mês (§9). Precedência: valor explícito do input;
     * senão o registro de dados_consumo_usina do ano (dedup: o MAIS RECENTE por
     * usina/ano — há pares duplicados no banco, §9). Ausente => 0.
     *
     * @param array<string, mixed> $input
     */
    private function consumo(Usina $usina, int $ano, string $mesNome, array $input): float
    {
        if (array_key_exists('consumo', $input) && $input['consumo'] !== null) {
            return (float) $input['consumo'];
        }

        // Dedup §9: pega o vínculo mais recente (maior dcu_id) por (usina, ano).
        $vinculo = DadoConsumoUsina::where('usi_id', $usina->usi_id)
            ->where('ano', $ano)
            ->orderByDesc('dcu_id')
            ->first();

        if ($vinculo === null) {
            return 0.0;
        }

        $dadoConsumo = DadoConsumo::find($vinculo->dcon_id);

        return $dadoConsumo !== null ? (float) ($dadoConsumo->$mesNome ?? 0) : 0.0;
    }

    /**
     * Saldo da reserva após este mês: saldo anterior − consumido − expirado + guardado.
     * Espelha exatamente os deltas reportados pelo núcleo (não recalcula).
     */
    private function saldoReservaDepois(float $saldoAntes, ResultadoCalculoMes $resultado): float
    {
        $consumido = array_sum(array_map(
            static fn (array $c): float => $c['kwh']->valor(),
            $resultado->consumosFifo,
        ));
        $expirado = array_sum(array_map(
            static fn (array $e): float => $e['kwh']->valor(),
            $resultado->expiracoes,
        ));

        $saldo = $saldoAntes - $consumido - $expirado + $resultado->guardadoKwh->valor();

        return max($saldo, 0.0);
    }

    /** @param \App\Domain\Faturamento\Ledger\LoteReserva[] $lotes */
    private function somarLotes(array $lotes): float
    {
        $total = 0.0;
        foreach ($lotes as $lote) {
            $total += $lote->saldoKwh->valor();
        }

        return $total;
    }

    /**
     * Persiste o mês: ledger + colunas materializadas + snapshot, em transação (§10).
     *
     * @return int[] cl_id dos lançamentos criados/atualizados no ledger
     */
    private function persistir(
        Usina $usina,
        Competencia $competencia,
        string $mesNome,
        EntradaCalculoMes $entrada,
        ResultadoCalculoMes $resultado,
        float $saldoDepois,
        ?int $userId,
        ?string $idempotencyKey,
    ): array {
        return DB::transaction(function () use (
            $usina, $competencia, $mesNome, $entrada, $resultado, $saldoDepois, $userId, $idempotencyKey
        ): array {
            $usiId = (int) $usina->usi_id;
            $ano = $competencia->ano;

            [$vinculo, $dgrVinculo] = $this->pacoteAnual($usina, $ano);

            $credito = CreditosDistribuidos::findOrFail($vinculo->cd_id);
            $reserva = ValorAcumuladoReserva::findOrFail($vinculo->var_id);
            $faturamento = FaturamentoUsina::findOrFail($vinculo->fa_id);
            $geracao = DadosGeracaoReal::findOrFail($dgrVinculo->dgr_id);

            // Snapshot ANTES de qualquer mutação (reuse do padrão de estorno).
            HistoricoEstorno::create([
                'usi_id' => $usiId,
                'ano' => $ano,
                'mes' => $competencia->mes,
                'mes_nome' => $mesNome,
                'user_id' => $userId ?? 0,
                'idempotency_key' => $idempotencyKey,
                'snapshot_reserva_atual' => $reserva->attributesToArray(),
                'snapshot_reserva_anterior' => null,
                'snapshot_credito_mes' => (float) ($credito->$mesNome ?? 0),
                'snapshot_faturamento_mes' => (float) ($faturamento->$mesNome ?? 0),
                'snapshot_geracao_mes' => (float) ($geracao->$mesNome ?? 0),
            ]);

            $ids = $this->gravarLedger($usiId, $competencia, $entrada->tarifa, $resultado, $userId);

            // Colunas materializadas como CACHE de leitura (§8): a fonte de verdade
            // do saldo é o ledger; estas colunas só aceleram a exibição.
            $credito->$mesNome = $resultado->credito->emReais();
            $credito->save();

            $faturamento->$mesNome = $resultado->valorFinal->emReais();
            $faturamento->save();

            $geracao->$mesNome = $entrada->geracaoBrutaKwh->valor();
            $geracao->save();

            $reserva->$mesNome = $resultado->guardadoKwh->valor();
            $reserva->total = $saldoDepois;
            $reserva->save();

            // Cache de LEITURA do PDF (§8): o PDF lê daqui sem recalcular nada.
            // Idempotente por (usi_id, competencia) — espelha o que o motor calculou.
            $this->gravarCachePdf($usiId, $competencia, $entrada, $resultado);

            return $ids;
        });
    }

    /**
     * Grava os lançamentos imutáveis do mês no credito_ledger (§8).
     *
     * - CREDITO: excedente guardado (kwh positivo), vence em 180 dias.
     * - CONSUMO: por lote consumido (kwh negativo), referencia o CREDITO de origem.
     * - EXPIRACAO: por lote vencido (kwh negativo), referencia o CREDITO de origem.
     *
     * Idempotência determinística por (usi_id, tipo, origem, evento): re-rodar não
     * duplica (updateOrCreate + unique no banco). A receita de expiração compõe o
     * valor final (§7), mas o lançamento no ledger é o mesmo movimento de saída.
     *
     * @return int[] cl_id na ordem de criação
     */
    private function gravarLedger(
        int $usiId,
        Competencia $evento,
        Tarifa $tarifa,
        ResultadoCalculoMes $resultado,
        ?int $userId,
    ): array {
        $ids = [];

        // Limpa os lançamentos deste evento (mês) antes de regravar — evita ÓRFÃOS
        // quando um recálculo produz menos lançamentos (ex.: CREDITO vira CONSUMO),
        // o que deixaria a coluna materializada divergente do saldo real do ledger.
        // Re-rodar o mesmo mês fica idempotente: remove o anterior e grava o atual.
        CreditoLedger::where('usi_id', $usiId)
            ->where('competencia_evento', $this->data($evento))
            ->delete();

        // 1) CREDITO do mês (entrada) — gravado primeiro para que consumos/expirações
        //    de meses FUTUROS deste mesmo evento possam referenciá-lo. Aqui o evento
        //    é também a origem.
        if ($resultado->guardadoKwh->ehPositivo()) {
            $registro = $this->upsertLancamento($usiId, [
                'tipo' => CreditoLedger::TIPO_CREDITO,
                'competencia_origem' => $this->data($evento),
                'competencia_evento' => $this->data($evento),
                'kwh' => round($resultado->guardadoKwh->valor(), 4),
                'tarifa_kwh' => $tarifa->valor(),
                'valor_reais' => round($resultado->guardadoKwh->vezesTarifa($tarifa)->emReais(), 2),
                'vencimento' => $evento->vencimentoEmDias(self::PRAZO_VENCIMENTO_DIAS)->format('Y-m-d'),
                'ref_lancamento_id' => null,
                'user_id' => $userId,
            ]);
            $ids[] = (int) $registro->cl_id;
        }

        // 2) CONSUMO por origem (saída, kwh negativo), apontando para o CREDITO de origem.
        foreach ($resultado->consumosFifo as $consumo) {
            $origem = $consumo['origem'];
            $registro = $this->upsertLancamento($usiId, [
                'tipo' => CreditoLedger::TIPO_CONSUMO,
                'competencia_origem' => $this->dataDeChave((string) $origem),
                'competencia_evento' => $this->data($evento),
                'kwh' => -round($consumo['kwh']->valor(), 4),
                'tarifa_kwh' => $tarifa->valor(),
                'valor_reais' => -round($consumo['kwh']->vezesTarifa($tarifa)->emReais(), 2),
                'vencimento' => null,
                'ref_lancamento_id' => $this->idCreditoOrigem($usiId, (string) $origem),
                'user_id' => $userId,
            ]);
            $ids[] = (int) $registro->cl_id;
        }

        // 3) EXPIRACAO por lote vencido (saída, kwh negativo). Indo pra frente a
        //    expiração compõe a receita no valor final, mas o lançamento é o mesmo.
        foreach ($resultado->expiracoes as $expiracao) {
            $origem = $expiracao['origem'];
            $registro = $this->upsertLancamento($usiId, [
                'tipo' => CreditoLedger::TIPO_EXPIRACAO,
                'competencia_origem' => $this->dataDeChave((string) $origem),
                'competencia_evento' => $this->data($evento),
                'kwh' => -round($expiracao['kwh']->valor(), 4),
                'tarifa_kwh' => $tarifa->valor(),
                'valor_reais' => -round($expiracao['kwh']->vezesTarifa($tarifa)->emReais(), 2),
                'vencimento' => null,
                'ref_lancamento_id' => $this->idCreditoOrigem($usiId, (string) $origem),
                'user_id' => $userId,
            ]);
            $ids[] = (int) $registro->cl_id;
        }

        return $ids;
    }

    /**
     * Grava o CACHE de leitura do PDF (PLANO_REDESENHO.md Fase 6): o breakdown
     * mensal em geracao_faturamento_pdf e o demonstrativo em demonstrativo_creditos_pdf.
     *
     * O PDF passa a LER daqui (zero recálculo). Os valores são EXATAMENTE os do
     * motor único — nenhuma fórmula nova é aplicada. Idempotente por
     * (usi_id, competencia) via updateOrCreate.
     */
    private function gravarCachePdf(
        int $usiId,
        Competencia $competencia,
        EntradaCalculoMes $entrada,
        ResultadoCalculoMes $resultado,
    ): void {
        $competenciaData = $this->data($competencia);

        // 1) Breakdown de faturamento (geracao_faturamento_pdf).
        //    injetado = valor_variavel; creditado = credito (termos do motor, §2).
        $this->upsertPorCompetencia(
            GeracaoFaturamentoPdf::query(),
            $usiId,
            $competenciaData,
            [
                'geracao_kwh' => round($entrada->geracaoBrutaKwh->valor(), 4),
                'valor_fixo' => $resultado->valorFixo->emReais(),
                'injetado' => $resultado->valorVariavel->emReais(),
                'creditado' => $resultado->credito->emReais(),
                'cuo' => $resultado->cuo->emReais(),
                'valor_final' => $resultado->valorFinal->emReais(),
            ],
        );

        // 2) Demonstrativo de créditos (demonstrativo_creditos_pdf):
        //    guardado/creditado em kWh, vencimento e a origem FIFO (§6, §7, §8).
        $creditadoKwh = array_sum(array_map(
            static fn (array $c): float => $c['kwh']->valor(),
            $resultado->consumosFifo,
        ));

        // "meses utilizados" = origens consumidas via FIFO neste mês (auditoria §8).
        $mesesUtilizados = array_map(
            static fn (array $c): string => self::competenciaLabel((string) $c['origem']),
            $resultado->consumosFifo,
        );
        $mesesUtilizadosTexto = count($mesesUtilizados) ? implode(', ', $mesesUtilizados) : null;

        $vencimento = $competencia->vencimentoEmDias(self::PRAZO_VENCIMENTO_DIAS)->format('Y-m-d');

        $this->upsertPorCompetencia(
            DemonstrativoCreditosPdf::query(),
            $usiId,
            $competenciaData,
            [
                'vencimento' => $vencimento,
                'guardado_kwh' => round($resultado->guardadoKwh->valor(), 4),
                'creditado_kwh' => round($creditadoKwh, 4),
                'meses_utilizados' => $mesesUtilizadosTexto,
            ],
        );
    }

    /**
     * Upsert idempotente por (usi_id, competencia) tolerante ao cast `date`:
     * casa o registro existente via whereDate (a coluna pode estar gravada como
     * datetime), atualizando-o; senão cria. Evita violar o unique quando a
     * comparação string vs datetime falharia num updateOrCreate cru.
     *
     * @param array<string, mixed> $valores
     */
    private function upsertPorCompetencia(
        \Illuminate\Database\Eloquent\Builder $query,
        int $usiId,
        string $competenciaData,
        array $valores,
    ): void {
        $existente = (clone $query)
            ->where('usi_id', $usiId)
            ->whereDate('competencia', $competenciaData)
            ->first();

        if ($existente !== null) {
            $existente->fill($valores)->save();

            return;
        }

        (clone $query)->create(array_merge($valores, [
            'usi_id' => $usiId,
            'competencia' => $competenciaData,
        ]));
    }

    /** Converte a chave "YYYY-MM" de uma origem em rótulo "Mês/AA" (auditoria). */
    private static function competenciaLabel(string $chave): string
    {
        [$ano, $mes] = array_map('intval', explode('-', $chave));
        $nome = self::MESES[$mes] ?? $chave;

        return ucfirst($nome) . '/' . substr((string) $ano, -2);
    }

    /**
     * upsert idempotente: re-rodar (mesmo usi/tipo/origem/evento) atualiza a linha
     * existente em vez de duplicar (unique idempotency_key no banco).
     *
     * @param array<string, mixed> $atributos
     */
    private function upsertLancamento(int $usiId, array $atributos): CreditoLedger
    {
        $chave = sprintf(
            'mes:%d:%s:%s:%s',
            $usiId,
            $atributos['tipo'],
            $atributos['competencia_origem'],
            $atributos['competencia_evento'],
        );

        return CreditoLedger::updateOrCreate(
            ['idempotency_key' => $chave],
            array_merge($atributos, [
                'usi_id' => $usiId,
                'idempotency_key' => $chave,
            ]),
        );
    }

    /**
     * cl_id do CREDITO/SALDO_INICIAL não-estornado da origem informada, para
     * preencher ref_lancamento_id da saída (rastreabilidade FIFO §8).
     */
    private function idCreditoOrigem(int $usiId, string $origemChave): ?int
    {
        $registro = CreditoLedger::query()
            ->doUsina($usiId)
            ->naoEstornado()
            ->whereIn('tipo', [CreditoLedger::TIPO_CREDITO, CreditoLedger::TIPO_SALDO_INICIAL])
            ->whereDate('competencia_origem', $this->dataDeChave($origemChave))
            ->orderBy('cl_id')
            ->first();

        return $registro !== null ? (int) $registro->cl_id : null;
    }

    /**
     * Carrega (ou cria) o pacote anual de cache (créditos/reserva/faturamento/geração).
     * Carrega o saldo total do ano anterior só na criação de um pacote novo.
     *
     * @return array{0: CreditosDistribuidosUsina, 1: DadosGeracaoRealUsina}
     */
    private function pacoteAnual(Usina $usina, int $ano): array
    {
        $vinculo = CreditosDistribuidosUsina::where('usi_id', $usina->usi_id)
            ->where('ano', $ano)
            ->first();

        $dgrVinculo = DadosGeracaoRealUsina::where('usi_id', $usina->usi_id)
            ->where('ano', $ano)
            ->first();

        if ($vinculo !== null && $dgrVinculo !== null) {
            return [$vinculo, $dgrVinculo];
        }

        $cd = CreditosDistribuidos::create();
        $var = ValorAcumuladoReserva::create(['total' => 0]);
        $fa = FaturamentoUsina::create();
        $dgr = DadosGeracaoReal::create();

        $vinculo = CreditosDistribuidosUsina::create([
            'usi_id' => $usina->usi_id,
            'cli_id' => $usina->cli_id,
            'cd_id' => $cd->cd_id,
            'fa_id' => $fa->fa_id,
            'var_id' => $var->var_id,
            'ano' => $ano,
        ]);

        $dgrVinculo = DadosGeracaoRealUsina::create([
            'usi_id' => $usina->usi_id,
            'cli_id' => $usina->cli_id,
            'dgr_id' => $dgr->dgr_id,
            'ano' => $ano,
        ]);

        return [$vinculo, $dgrVinculo];
    }

    private function data(Competencia $competencia): string
    {
        return sprintf('%04d-%02d-01', $competencia->ano, $competencia->mes);
    }

    /** Converte a chave "YYYY-MM" de uma origem em data de competência "YYYY-MM-01". */
    private function dataDeChave(string $chave): string
    {
        return $chave . '-01';
    }
}
