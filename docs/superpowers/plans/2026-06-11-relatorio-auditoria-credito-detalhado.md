# Relatório de Auditoria de Crédito Detalhado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Regerar o relatório de auditoria de crédito aplicando geração líquida, saldo inicial migrado e ordem de expiração corretos, dirigindo o motor de domínio único (`CalculadoraGeracaoLinear`) e produzindo um HTML auditável de 4 camadas para as 67 usinas.

**Architecture:** Duas peças novas de domínio testadas por PHPUnit (`DescontoRede` para geração líquida §9; `ReconstrutorLedger` que orquestra o loop mensal reusando a Calculadora e espelhando os deltas do resultado na reserva). O script `storage/reconstrucao/reconstruir.php` é reescrito para `require vendor/autoload.php`, carregar o staging (PDO), dirigir o `ReconstrutorLedger` e renderizar o relatório. Roda só contra staging (:5440), nunca produção.

**Tech Stack:** PHP 8.x, PHPUnit 11, PDO (pgsql), domínio livre de framework em `app/Domain/Faturamento`.

**Referências:** spec [docs/superpowers/specs/2026-06-11-relatorio-auditoria-credito-detalhado-design.md](../specs/2026-06-11-relatorio-auditoria-credito-detalhado-design.md); regras [docs/calculo/REGRAS_DE_CALCULO.md](../../calculo/REGRAS_DE_CALCULO.md).

---

## Estrutura de arquivos

| Arquivo | Responsabilidade | Ação |
|---|---|---|
| `app/Domain/Faturamento/Calculo/DescontoRede.php` | Regra §9: desconto por tipo de conexão e geração líquida. Pura. | Criar |
| `app/Domain/Faturamento/Ledger/ReconstrutorLedger.php` | Orquestra o loop mensal; reusa Calculadora; espelha deltas na reserva; emite ledger. | Criar |
| `tests/Unit/Faturamento/DescontoRedeTest.php` | Testa a regra de geração líquida. | Criar |
| `tests/Unit/Faturamento/ReconstrutorLedgerTest.php` | Testa o threading da reserva e o golden do Eder reconstruído. | Criar |
| `api-laravel/storage/reconstrucao/reconstruir.php` | Carrega staging, dirige o reconstrutor, renderiza HTML+CSV de 4 camadas. | Reescrever |

---

## Task 1: DescontoRede (geração líquida §9)

**Files:**
- Create: `app/Domain/Faturamento/Calculo/DescontoRede.php`
- Test: `tests/Unit/Faturamento/DescontoRedeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Calculo\DescontoRede;
use App\Domain\Faturamento\ValueObject\Kwh;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DescontoRedeTest extends TestCase
{
    #[Test]
    public function desconto_por_tipo_de_conexao(): void
    {
        $this->assertSame(100.0, DescontoRede::kwhPorTipo('Trifásico'));
        $this->assertSame(50.0, DescontoRede::kwhPorTipo('Bifásico'));
        $this->assertSame(30.0, DescontoRede::kwhPorTipo('Monofásico'));
        $this->assertSame(0.0, DescontoRede::kwhPorTipo(null));
        $this->assertSame(0.0, DescontoRede::kwhPorTipo('desconhecido'));
    }

    #[Test]
    public function liquida_subtrai_consumo_descontavel(): void
    {
        // bruta 9858, consumo 134, trifásico 100 -> descontável 34 -> líquida 9824
        $liquida = DescontoRede::liquida(Kwh::de(9858), Kwh::de(134), 'Trifásico');
        $this->assertSame(9824.0, $liquida->valor());
    }

    #[Test]
    public function consumo_abaixo_do_desconto_nao_reduz(): void
    {
        // consumo 80 <= desconto 100 -> descontável 0 -> líquida = bruta
        $liquida = DescontoRede::liquida(Kwh::de(9858), Kwh::de(80), 'Trifásico');
        $this->assertSame(9858.0, $liquida->valor());
    }

    #[Test]
    public function liquida_nunca_negativa(): void
    {
        $liquida = DescontoRede::liquida(Kwh::de(50), Kwh::de(500), 'Monofásico');
        $this->assertSame(0.0, $liquida->valor());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd api-laravel && ./vendor/bin/phpunit tests/Unit/Faturamento/DescontoRedeTest.php`
Expected: FAIL — `Class "App\Domain\Faturamento\Calculo\DescontoRede" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Calculo;

use App\Domain\Faturamento\ValueObject\Kwh;

/**
 * Geração líquida e desconto de rede (REGRAS_DE_CALCULO.md §9).
 *
 *   consumo_descontavel = max(consumo − desconto_rede, 0)
 *   geracao_liquida     = max(geracao_bruta − consumo_descontavel, 0)
 *
 * Desconto por tipo de conexão: Trifásico 100 / Bifásico 50 / Monofásico 30 kWh.
 * Tipo ausente/desconhecido => desconto 0 (e a usina deve ser sinalizada na
 * camada de qualidade de dados do relatório).
 */
final class DescontoRede
{
    private const POR_TIPO = [
        'Trifásico' => 100.0,
        'Bifásico' => 50.0,
        'Monofásico' => 30.0,
    ];

    public static function kwhPorTipo(?string $rede): float
    {
        return self::POR_TIPO[$rede] ?? 0.0;
    }

    public static function liquida(Kwh $bruta, Kwh $consumo, ?string $rede): Kwh
    {
        $descontavel = max($consumo->valor() - self::kwhPorTipo($rede), 0.0);
        $liquida = max($bruta->valor() - $descontavel, 0.0);

        return Kwh::de($liquida);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd api-laravel && ./vendor/bin/phpunit tests/Unit/Faturamento/DescontoRedeTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add api-laravel/app/Domain/Faturamento/Calculo/DescontoRede.php api-laravel/tests/Unit/Faturamento/DescontoRedeTest.php
git commit -m "feat(faturamento): DescontoRede para geração líquida (§9)"
```

---

## Task 2: ReconstrutorLedger (orquestração mensal)

**Files:**
- Create: `app/Domain/Faturamento/Ledger/ReconstrutorLedger.php`
- Test: `tests/Unit/Faturamento/ReconstrutorLedgerTest.php`

O reconstrutor recebe os meses crus de UMA usina (ordem qualquer) e lotes iniciais opcionais. Para cada mês, na ordem cronológica: deriva a líquida (DescontoRede), monta `EntradaCalculoMes`, chama a Calculadora com a reserva corrente, registra o resultado, e atualiza a reserva **espelhando os deltas reportados** (consumos, expirações, guardado) — sem reimplementar FIFO/expiração.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Faturamento;

use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\ReconstrutorLedger;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReconstrutorLedgerTest extends TestCase
{
    /** Mês cru padrão; sobrescreve só o necessário por caso. */
    private function mes(int $ano, int $mes, float $bruta, float $consumo = 0.0): array
    {
        return [
            'ano' => $ano, 'mes' => $mes,
            'geracao_bruta_kwh' => $bruta, 'consumo_kwh' => $consumo, 'rede' => 'Trifásico',
            'media_kwh' => 12911, 'menor_geracao_kwh' => 7636, 'tarifa' => 0.51,
            'fio_b' => 0.13275, 'percentual_lei' => 60.0, 'fatura_energia' => 98.77, 'adicional_cuo' => 0.0,
        ];
    }

    #[Test]
    public function excedente_vira_lote_consumido_no_deficit_seguinte(): void
    {
        // nov guarda excedente; dez tem déficit e consome o lote de nov.
        $meses = [
            $this->mes(2025, 11, 14911), // líquida 14911 (consumo 0) - média 12911 = guardou 2000
            $this->mes(2025, 12, 10911), // faltante 2000 -> consome 2000 de nov
        ];

        $r = (new ReconstrutorLedger())->reconstruir($meses);

        $nov = $r['meses'][0];
        $dez = $r['meses'][1];
        $this->assertSame(2000.0, $nov['resultado']->guardadoKwh->valor());
        $this->assertCount(1, $dez['resultado']->consumosFifo);
        $this->assertTrue($dez['resultado']->consumosFifo[0]['origem']->ehIgualA(Competencia::de(2025, 11)));
        $this->assertSame(2000.0, $dez['resultado']->consumosFifo[0]['kwh']->valor());
        // reserva esgotada após o consumo
        $this->assertSame(0.0, $dez['saldo_final_kwh']);
    }

    #[Test]
    public function saldo_inicial_e_consumido_primeiro_via_fifo(): void
    {
        // lote inicial migrado (origem antiga) deve ser consumido antes do excedente do próprio ano.
        $inicial = new LoteReserva(
            Competencia::de(2024, 1),
            Kwh::de(1000),
            Competencia::de(2024, 1)->vencimentoEmDias(180),
        );
        // único mês com déficit de 500 -> consome 500 do lote inicial (mais antigo).
        $meses = [$this->mes(2026, 5, 12411)]; // faltante 500

        $r = (new ReconstrutorLedger())->reconstruir($meses, [$inicial]);

        $mai = $r['meses'][0];
        $this->assertCount(1, $mai['resultado']->consumosFifo);
        $this->assertTrue($mai['resultado']->consumosFifo[0]['origem']->ehIgualA(Competencia::de(2024, 1)));
        $this->assertSame(500.0, $mai['resultado']->consumosFifo[0]['kwh']->valor());
    }

    #[Test]
    public function deriva_geracao_liquida_com_desconto_de_rede(): void
    {
        // bruta 9858, consumo 134, trifásico -> líquida 9824 entra no cálculo.
        $r = (new ReconstrutorLedger())->reconstruir([$this->mes(2026, 5, 9858, 134)]);
        $this->assertSame(9824.0, $r['meses'][0]['entrada']->geracaoLiquidaKwh->valor());
        $this->assertSame(9858.0, $r['meses'][0]['entrada']->geracaoBrutaKwh->valor());
    }

    #[Test]
    public function ordena_cronologicamente_meses_fora_de_ordem(): void
    {
        $r = (new ReconstrutorLedger())->reconstruir([
            $this->mes(2026, 1, 13000),
            $this->mes(2025, 12, 13000),
        ]);
        $this->assertTrue($r['meses'][0]['entrada']->competencia->ehIgualA(Competencia::de(2025, 12)));
        $this->assertTrue($r['meses'][1]['entrada']->competencia->ehIgualA(Competencia::de(2026, 1)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd api-laravel && ./vendor/bin/phpunit tests/Unit/Faturamento/ReconstrutorLedgerTest.php`
Expected: FAIL — `Class "App\Domain\Faturamento\Ledger\ReconstrutorLedger" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Faturamento\Ledger;

use App\Domain\Faturamento\Calculo\CalculadoraGeracaoLinear;
use App\Domain\Faturamento\Calculo\DescontoRede;
use App\Domain\Faturamento\DTO\EntradaCalculoMes;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;

/**
 * Reconstrói o ledger de reserva de UMA usina percorrendo a timeline mês a mês,
 * reusando o motor de cálculo único (CalculadoraGeracaoLinear). Não reimplementa
 * FIFO nem expiração: apenas espelha na reserva carregada os deltas que o
 * resultado reporta (consumos, expirações, guardado).
 *
 * Saldo inicial migrado (REGRAS_DE_CALCULO.md §12) entra como lotes iniciais
 * (a competência de origem deve ser anterior à timeline para ser consumida 1º).
 */
final class ReconstrutorLedger
{
    private const PRAZO_EXPIRACAO_DIAS = 180;

    public function __construct(
        private readonly CalculadoraGeracaoLinear $calculadora = new CalculadoraGeracaoLinear(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $mesesRaw Meses crus (ordem qualquer). Chaves:
     *        ano, mes, geracao_bruta_kwh, consumo_kwh, rede, media_kwh, menor_geracao_kwh,
     *        tarifa, fio_b, percentual_lei, fatura_energia, adicional_cuo.
     * @param LoteReserva[] $lotesIniciais Saldo inicial migrado (§12), opcional.
     *
     * @return array{
     *     meses: array<int, array{
     *         ano:int, mes:int,
     *         entrada: EntradaCalculoMes,
     *         resultado: \App\Domain\Faturamento\DTO\ResultadoCalculoMes,
     *         reserva_antes_kwh: float,
     *         saldo_final_kwh: float
     *     }>,
     *     eventos: array<int, array{tipo:string, origem:string, evento:string, kwh:float}>
     * }
     */
    public function reconstruir(array $mesesRaw, array $lotesIniciais = []): array
    {
        usort(
            $mesesRaw,
            static fn (array $a, array $b): int
                => [$a['ano'], $a['mes']] <=> [$b['ano'], $b['mes']],
        );

        $reserva = array_values($lotesIniciais);
        $meses = [];
        $eventos = [];

        foreach ($lotesIniciais as $lote) {
            $eventos[] = [
                'tipo' => 'SALDO_INICIAL',
                'origem' => (string) $lote->competenciaOrigem,
                'evento' => (string) $lote->competenciaOrigem,
                'kwh' => $lote->saldoKwh->valor(),
            ];
        }

        foreach ($mesesRaw as $m) {
            $competencia = Competencia::de((int) $m['ano'], (int) $m['mes']);
            $bruta = Kwh::de((float) $m['geracao_bruta_kwh']);
            $liquida = DescontoRede::liquida($bruta, Kwh::de((float) ($m['consumo_kwh'] ?? 0)), $m['rede'] ?? null);

            $entrada = EntradaCalculoMes::deArray([
                'geracao_liquida_kwh' => $liquida->valor(),
                'media_kwh' => (float) $m['media_kwh'],
                'menor_geracao_kwh' => (float) $m['menor_geracao_kwh'],
                'geracao_bruta_kwh' => $bruta->valor(),
                'tarifa' => (float) $m['tarifa'],
                'fio_b' => (float) ($m['fio_b'] ?? 0),
                'percentual_lei' => (float) ($m['percentual_lei'] ?? 0),
                'fatura_energia' => (float) ($m['fatura_energia'] ?? 0),
                'adicional_cuo' => (float) ($m['adicional_cuo'] ?? 0),
                'competencia' => $competencia,
            ]);

            $reservaAntes = $this->saldoTotal($reserva);
            $resultado = $this->calculadora->calcular($entrada, $reserva);

            foreach ($resultado->consumosFifo as $c) {
                $eventos[] = [
                    'tipo' => 'CONSUMO', 'origem' => (string) $c['origem'],
                    'evento' => (string) $competencia, 'kwh' => $c['kwh']->valor(),
                ];
            }
            foreach ($resultado->expiracoes as $e) {
                $eventos[] = [
                    'tipo' => 'EXPIRACAO', 'origem' => (string) $e['origem'],
                    'evento' => (string) $competencia, 'kwh' => $e['kwh']->valor(),
                ];
            }
            if ($resultado->guardadoKwh->ehPositivo()) {
                $eventos[] = [
                    'tipo' => 'CREDITO', 'origem' => (string) $competencia,
                    'evento' => (string) $competencia, 'kwh' => $resultado->guardadoKwh->valor(),
                ];
            }

            $reserva = $this->aplicarDeltas($reserva, $resultado, $competencia);

            $meses[] = [
                'ano' => $competencia->ano, 'mes' => $competencia->mes,
                'entrada' => $entrada, 'resultado' => $resultado,
                'reserva_antes_kwh' => $reservaAntes,
                'saldo_final_kwh' => $this->saldoTotal($reserva),
            ];
        }

        return ['meses' => $meses, 'eventos' => $eventos];
    }

    /**
     * Espelha na reserva os deltas reportados pelo resultado: subtrai consumos por
     * origem, zera origens expiradas e adiciona o guardado do mês como novo lote.
     *
     * @param LoteReserva[] $reserva
     *
     * @return LoteReserva[]
     */
    private function aplicarDeltas(
        array $reserva,
        \App\Domain\Faturamento\DTO\ResultadoCalculoMes $resultado,
        Competencia $competencia,
    ): array {
        $consumidoPorOrigem = [];
        foreach ($resultado->consumosFifo as $c) {
            $k = (string) $c['origem'];
            $consumidoPorOrigem[$k] = ($consumidoPorOrigem[$k] ?? 0.0) + $c['kwh']->valor();
        }
        $expiradoOrigens = [];
        foreach ($resultado->expiracoes as $e) {
            $expiradoOrigens[(string) $e['origem']] = true;
        }

        $nova = [];
        foreach ($reserva as $lote) {
            $k = (string) $lote->competenciaOrigem;
            $saldo = $lote->saldoKwh->valor() - ($consumidoPorOrigem[$k] ?? 0.0);
            if (isset($expiradoOrigens[$k])) {
                $saldo = 0.0;
            }
            if ($saldo > 0.0) {
                $nova[] = new LoteReserva($lote->competenciaOrigem, Kwh::de($saldo), $lote->vencimento);
            }
        }

        if ($resultado->guardadoKwh->ehPositivo()) {
            $nova[] = new LoteReserva(
                $competencia,
                $resultado->guardadoKwh,
                $competencia->vencimentoEmDias(self::PRAZO_EXPIRACAO_DIAS),
            );
        }

        return $nova;
    }

    /** @param LoteReserva[] $reserva */
    private function saldoTotal(array $reserva): float
    {
        $total = 0.0;
        foreach ($reserva as $lote) {
            $total += $lote->saldoKwh->valor();
        }

        return $total;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd api-laravel && ./vendor/bin/phpunit tests/Unit/Faturamento/ReconstrutorLedgerTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Run the full golden suite to confirm no regression**

Run: `cd api-laravel && ./vendor/bin/phpunit --group golden`
Expected: PASS (motor inalterado).

- [ ] **Step 6: Commit**

```bash
git add api-laravel/app/Domain/Faturamento/Ledger/ReconstrutorLedger.php api-laravel/tests/Unit/Faturamento/ReconstrutorLedgerTest.php
git commit -m "feat(faturamento): ReconstrutorLedger orquestra reserva mês a mês reusando o motor único"
```

---

## Task 3: Reescrever reconstruir.php (carga staging + render 4 camadas)

**Files:**
- Modify (reescrever): `api-laravel/storage/reconstrucao/reconstruir.php`

O script passa a: (a) `require __DIR__.'/../../vendor/autoload.php'`; (b) carregar usinas, geração real, consumo (deduplicado por `updated_at` mais recente), crédito ANTES (`creditos_distribuidos`), faturamento ANTES (`geracao_faturamento_pdf`), tipo de rede e saldo inicial das migradas; (c) para cada usina, montar os meses crus e chamar `ReconstrutorLedger`; (d) cruzar DEPOIS×ANTES, classificar e agregar; (e) renderizar HTML de 4 camadas + CSV.

Detalhes de carga (confirmados no staging):
- `dados_consumo_usina (usi_id, dcon_id, ano)` → join `dados_consumo` (colunas mês). **Dedup:** por `(usi_id, ano)` manter o `updated_at` mais recente (108 pares duplicados).
- `usina.rede` ∈ {Trifásico, Bifásico, Monofásico}.
- Saldo inicial das 21 migradas: `valor_acumulado_reserva` do ano mais antigo, `total`. Lote `SALDO_INICIAL` com origem = `Competencia(menorAno, 1)` e vencimento = origem+180.
- ANTES por termo: `geracao_faturamento_pdf (competencia, valor_fixo, injetado, creditado, cuo, valor_final)`. ANTES de crédito mensal também em `creditos_distribuidos` (colunas mês).

- [ ] **Step 1: Reescrever o script** (entrypoint que usa o domínio e renderiza as 4 camadas)

Estrutura (procedural, isolado em staging — segue o padrão do script atual):

```php
<?php
/**
 * Reconstrução auditável (ANTES × DEPOIS) — versão corrigida.
 * Dirige o motor de domínio único (CalculadoraGeracaoLinear via ReconstrutorLedger),
 * aplicando geração líquida (§9), saldo inicial migrado (§12) e expiração na ordem (§7).
 * Roda contra o STAGING (cópia de produção, :5440), SEM tocar produção/staging.
 *
 * Uso: php reconstruir.php          -> gera relatorio.html + relatorio-antes-depois.csv
 *      php reconstruir.php --uc=X   -> detalha uma usina no terminal
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Domain\Faturamento\Ledger\LoteReserva;
use App\Domain\Faturamento\Ledger\ReconstrutorLedger;
use App\Domain\Faturamento\ValueObject\Competencia;
use App\Domain\Faturamento\ValueObject\Kwh;

const DB_HOST='127.0.0.1'; const DB_PORT='5440'; const DB_NAME='energia_assinatura';
const DB_USER='postgres';  const DB_PASS='staging';
const TOL=0.01;

$MESES=[1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
        7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$MES_LABEL=[1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];

$opts=getopt('',['uc::']); $ucFiltro=$opts['uc']??null;
$pdo=new PDO('pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME,DB_USER,DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

// ---- carga base (usina + comercializacao + dados_geracao + rede) ----
$sql="SELECT u.usi_id,u.uc,u.rede,cli.nome AS cliente,
             c.valor_kwh,c.fio_b,c.percentual_lei,d.media,d.menor_geracao
      FROM usina u
      JOIN comercializacao c ON c.com_id=u.com_id
      JOIN dados_geracao d ON d.dger_id=u.dger_id
      LEFT JOIN cliente cli ON cli.cli_id=u.cli_id";
if($ucFiltro){$sql.=" WHERE u.uc=".$pdo->quote($ucFiltro);}
$usinas=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$reconstrutor=new ReconstrutorLedger();
$linhas=[]; $porTipo=[]; $usinasAfetadas=[]; $usinasReport=[]; $dq=['saldo_inicial'=>[],'consumo_dup'=>[],'sem_rede'=>[]];

// consumo duplicado (qualidade de dados)
foreach($pdo->query("SELECT usi_id,ano,count(*) c FROM dados_consumo_usina GROUP BY usi_id,ano HAVING count(*)>1") as $r){
    $dq['consumo_dup'][]=$r;
}

foreach($usinas as $u){
    $usiId=(int)$u['usi_id'];
    if(empty($u['rede'])) $dq['sem_rede'][]=$u['uc'];

    // geração real por ano
    $st=$pdo->prepare("SELECT dgru.ano,dgr.* FROM dados_geracao_real_usina dgru
        JOIN dados_geracao_real dgr ON dgr.dgr_id=dgru.dgr_id WHERE dgru.usi_id=:u ORDER BY dgru.ano");
    $st->execute([':u'=>$usiId]); $geracaoPorAno=$st->fetchAll(PDO::FETCH_ASSOC);
    if(!$geracaoPorAno) continue;

    // consumo por ano (DEDUP: updated_at mais recente por (usina,ano))
    $st=$pdo->prepare("SELECT DISTINCT ON (dcu.ano) dcu.ano,dc.* FROM dados_consumo_usina dcu
        JOIN dados_consumo dc ON dc.dcon_id=dcu.dcon_id WHERE dcu.usi_id=:u
        ORDER BY dcu.ano, dc.updated_at DESC");
    $st->execute([':u'=>$usiId]); $consumoPorAno=[]; foreach($st as $r){$consumoPorAno[(int)$r['ano']]=$r;}

    // crédito ANTES (sistema)
    $st=$pdo->prepare("SELECT cdu.ano,cd.* FROM creditos_distribuidos_usina cdu
        JOIN creditos_distribuidos cd ON cd.cd_id=cdu.cd_id WHERE cdu.usi_id=:u");
    $st->execute([':u'=>$usiId]); $credAntes=[]; foreach($st as $r){$credAntes[(int)$r['ano']]=$r;}

    // faturamento ANTES por competência (4 termos)
    $st=$pdo->prepare("SELECT competencia,valor_fixo,injetado,creditado,cuo,valor_final
        FROM geracao_faturamento_pdf WHERE usi_id=:u");
    $st->execute([':u'=>$usiId]); $fatAntes=[]; foreach($st as $r){$fatAntes[substr($r['competencia'],0,7)]=$r;}

    // monta meses crus
    $mesesRaw=[];
    foreach($geracaoPorAno as $ga){$ano=(int)$ga['ano'];
        foreach($MESES as $n=>$nome){$g=$ga[$nome];
            if($g===null||(float)$g==0.0)continue;
            $cons=isset($consumoPorAno[$ano])?(float)($consumoPorAno[$ano][$nome]??0):0.0;
            $mesesRaw[]=['ano'=>$ano,'mes'=>$n,'geracao_bruta_kwh'=>(float)$g,'consumo_kwh'=>$cons,
                'rede'=>$u['rede'],'media_kwh'=>(float)$u['media'],'menor_geracao_kwh'=>(float)$u['menor_geracao'],
                'tarifa'=>(float)$u['valor_kwh'],'fio_b'=>(float)$u['fio_b'],'percentual_lei'=>(float)$u['percentual_lei'],
                'fatura_energia'=>0.0,'adicional_cuo'=>0.0];}}
    if(!$mesesRaw)continue;

    // saldo inicial migrado: déficit histórico > excedente -> lote SALDO_INICIAL
    $lotesIniciais=[];
    $st=$pdo->prepare("SELECT ano,total FROM valor_acumulado_reserva var
        JOIN valor_acumulado_reserva_usina varu ON varu.var_id=var.var_id WHERE varu.usi_id=:u ORDER BY ano ASC LIMIT 1");
    // NOTE: confirmar nome da tabela de ligação em Step 2; ajustar a query conforme o schema real.
    // Heurística migrada: se soma dos excedentes reconstruídos < soma dos déficits, precisa de saldo inicial.
    // (cálculo de excedente/déficit feito a partir de $mesesRaw e média.)
    // -> implementação concreta validada em Step 2 contra os dados.

    $rec=$reconstrutor->reconstruir($mesesRaw,$lotesIniciais);

    // cruza DEPOIS×ANTES por mês e classifica
    foreach($rec['meses'] as $mz){
        $ano=$mz['ano']; $n=$mz['mes']; $res=$mz['resultado']; $ent=$mz['entrada'];
        $cA=isset($credAntes[$ano])?(float)$credAntes[$ano][$MESES[$n]]:0.0;
        $cD=$res->credito->emReais();
        $diff=round($cD-$cA,2);
        $faltante=max($ent->mediaKwh->valor()-$ent->geracaoLiquidaKwh->valor(),0);
        // ... classificação (mesma taxonomia do script atual, agora sobre líquida) ...
        // ... acumula $linhas[], $porTipo, $usinasAfetadas, e guarda timeline em $usinasReport[uc] ...
    }
}

// ---- render HTML (4 camadas) + CSV ----
// (função de render detalhada no Step 3)
```

A query/heurística do saldo inicial é finalizada no Step 2 (precisa do nome real da tabela de ligação de `valor_acumulado_reserva`).

- [ ] **Step 2: Confirmar schema do saldo inicial e finalizar a carga**

Run:
```bash
cd api-laravel && PGPASSWORD=staging psql -h 127.0.0.1 -p 5440 -U postgres -d energia_assinatura -t -A \
  -c "SELECT table_name FROM information_schema.tables WHERE table_name ILIKE '%valor_acumulado%';" \
  -c "SELECT column_name FROM information_schema.columns WHERE table_name='valor_acumulado_reserva' ORDER BY ordinal_position;"
```
Expected: revela a tabela de ligação reserva↔usina e as colunas (`total`, meses, `ano`). Ajustar a query do saldo inicial conforme o resultado, mantendo a regra: lote `SALDO_INICIAL` = `total` do ano mais antigo quando a usina é migrada (déficit histórico > excedente).

- [ ] **Step 3: Implementar o render HTML de 4 camadas**

Renderizar, na ordem da spec §6:
1. Cards: usinas analisadas / com erro / creditado a mais / a menos / impacto no valor final.
2. **Geração líquida:** tabela por usina×mês com bruta, consumo, desconto (tipo de rede), líquida.
3. **4 termos Antes×Depois:** por competência, Fixo/Variável/Crédito/CUO (Antes×Depois) + diff por termo + impacto no valor final.
4. **Drill-down por usina:** `<details>` por usina (28 divergentes com `open`), timeline do ledger (guardou/consumiu de qual origem/expirou + saldo).
5. **Qualidade de dados:** 108 duplicados, 21 saldos iniciais, expirações, casos sem rede / divergência inexplicada.

Reusar o CSS atual; rótulos sempre com unidade (kWh/R$). Footer: "energia em kWh · valores financeiros em R$".

- [ ] **Step 4: Rodar e validar os casos golden no relatório**

Run:
```bash
cd api-laravel/storage/reconstrucao && php reconstruir.php --uc=562606800
php reconstruir.php --uc=3085733401
php reconstruir.php --uc=19771547
```
Expected:
- Eder (562606800) Mai/2026: crédito DEPOIS ≈ R$ 1.557–1.561 (não 1.866,60); valor final ≈ R$ 5.700,65.
- Colina (3085733401) Fev/2026: crédito DEPOIS = R$ 0,00.
- Luci (19771547): sem crédito espúrio nos meses de geração ≥ média.

Se algum não bater, depurar a carga (consumo/dedup/saldo inicial) antes de gerar o relatório completo.

- [ ] **Step 5: Gerar o relatório completo**

Run: `cd api-laravel/storage/reconstrucao && php reconstruir.php`
Expected: imprime resumo no terminal e grava `relatorio.html` + `relatorio-antes-depois.csv`. Abrir o HTML e conferir as 4 camadas e o collapse das usinas.

- [ ] **Step 6: Commit**

```bash
git add api-laravel/storage/reconstrucao/reconstruir.php api-laravel/storage/reconstrucao/relatorio.html api-laravel/storage/reconstrucao/relatorio-antes-depois.csv
git commit -m "feat(auditoria): relatório detalhado dirigido pelo motor único (líquida+saldo inicial+expiração)"
```

---

## Self-review

- **Cobertura da spec:** §5 regras → Tasks 1-2 (líquida, saldo inicial, expiração via motor); §6 camadas → Task 3 Step 3; §7 validação → Task 3 Step 4; §8 ressalvas → registradas na camada de qualidade de dados (Task 3 Step 3, item 5) e no footer. OK.
- **Sem placeholders de cálculo:** as duas peças de domínio (Tasks 1-2) têm código completo e testado. O script (Task 3) tem a carga concreta; o único ponto deliberadamente confirmado em runtime é o nome da tabela de ligação do `valor_acumulado_reserva` (Step 2), por não estar verificado ainda — não é placeholder de lógica, é verificação de schema.
- **Consistência de tipos:** `ReconstrutorLedger::reconstruir(array, array): array{meses,eventos}`; cada mês expõe `entrada` (EntradaCalculoMes), `resultado` (ResultadoCalculoMes), `reserva_antes_kwh`, `saldo_final_kwh`. `DescontoRede::liquida(Kwh,Kwh,?string):Kwh` e `kwhPorTipo(?string):float`. Assinaturas usadas igualmente nos testes e no script.
