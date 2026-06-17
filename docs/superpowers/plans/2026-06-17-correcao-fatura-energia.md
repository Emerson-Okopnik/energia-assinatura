# Correção da `fatura_energia` + Auditoria refeita (PAGA TUDO) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fazer o sistema registrar o Valor Final correto de cada mês (fatura real aplicada; expiração paga — PAGA TUDO), via um comando idempotente, e refazer a auditoria para refletir pago-antigo × correto.

**Architecture:** Reusa o motor único (`CalculadoraGeracaoLinear`) e o `FaturamentoService::calcularMes(persistir: true)`. A fatura real vem de uma **tabela-fonte** extraída do dump pré-correção. Um comando novo (`faturamento:corrigir-fatura`) re-materializa cada mês com a fatura por precedência (prod>0 → fonte → 0). O motor já paga a expiração — não se altera o cálculo. A auditoria (`reconstruir.php`) passa a pagar a expiração também.

**Tech Stack:** PHP 8.3, Laravel, PostgreSQL (prod), SQLite in-memory (testes), PHPUnit 11.

## Global Constraints

- Motor de cálculo é fonte única de verdade — NÃO reimplementar fórmula (`CalculadoraGeracaoLinear`).
- PAGA TUDO: expiração SEMPRE soma no Valor Final (motor já faz; auditoria deve passar a fazer).
- Precedência da fatura, por (usina, competência): `geracao_faturamento_pdf.fatura_energia` de prod se `> 0`; senão fatura da tabela-fonte; senão `0`.
- Idempotência obrigatória: re-rodar não duplica ledger nem muda valores.
- Guard de competência futura: não processar `ano/mês > corrente`.
- NUNCA sobrescrever lançamento manual (fatura de prod > 0 vence).
- Rodar testes: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter <Classe>` (a partir de `api-laravel/`). Os testes usam SQLite in-memory (`RefreshDatabase`).
- Dumps de produção ficam FORA do git (`*.dump` no `.gitignore`).

---

## File Structure

- `app/Console/Commands/CorrigirFaturaEnergia.php` — **novo** comando `faturamento:corrigir-fatura` (precedência, dry-run, idempotente, CSV antes×depois).
- `app/Models/FaturaFonte.php` — **novo** model da tabela-fonte da fatura.
- `database/migrations/2026_06_17_000000_create_fatura_fonte_table.php` — **nova** migration da tabela-fonte.
- `storage/reconstrucao/extrair_fatura_fonte.php` — **novo** script que lê o dump antigo restaurado e gera o CSV da fatura-fonte.
- `app/Console/Commands/ImportarFaturaFonte.php` — **novo** comando `faturamento:importar-fatura-fonte {csv}` que popula a tabela-fonte a partir do CSV.
- `storage/reconstrucao/reconstruir.php` — **modificar** (linha 248: pagar expiração; bug do 1º mês).
- `docs/calculo/REGRAS_DE_CALCULO.md` — **modificar** §7/§12 (PAGA TUDO).
- `tests/Feature/CorrigirFaturaEnergiaTest.php` — **novo** teste do comando.

---

## Task 1: Migration + Model da tabela-fonte da fatura

**Files:**
- Create: `api-laravel/database/migrations/2026_06_17_000000_create_fatura_fonte_table.php`
- Create: `api-laravel/app/Models/FaturaFonte.php`
- Test: `api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php`

**Interfaces:**
- Produces: tabela `fatura_fonte` com colunas `id`, `uc` (string), `competencia` (date), `fatura_energia` (decimal 12,2). Model `App\Models\FaturaFonte` com `$fillable = ['uc','competencia','fatura_energia']`, `$casts = ['competencia' => 'date', 'fatura_energia' => 'float']`, `$timestamps = false`.

- [ ] **Step 1: Escrever o teste que falha (tabela existe e aceita inserção)**

Criar `api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FaturaFonte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrigirFaturaEnergiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fatura_fonte_armazena_por_uc_e_competencia(): void
    {
        FaturaFonte::create([
            'uc' => '521206860',
            'competencia' => '2026-01-01',
            'fatura_energia' => 1663.71,
        ]);

        $registro = FaturaFonte::where('uc', '521206860')->first();

        $this->assertNotNull($registro);
        $this->assertEqualsWithDelta(1663.71, (float) $registro->fatura_energia, 0.001);
        $this->assertSame('2026-01', $registro->competencia->format('Y-m'));
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run (de `api-laravel/`): `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter CorrigirFaturaEnergiaTest`
Expected: FAIL — "Class FaturaFonte not found" ou tabela inexistente.

- [ ] **Step 3: Criar a migration**

Criar `api-laravel/database/migrations/2026_06_17_000000_create_fatura_fonte_table.php`:

```php
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
```

- [ ] **Step 4: Criar o model**

Criar `api-laravel/app/Models/FaturaFonte.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaturaFonte extends Model
{
    protected $table = 'fatura_fonte';

    public $timestamps = false;

    protected $fillable = ['uc', 'competencia', 'fatura_energia'];

    protected $casts = [
        'competencia' => 'date',
        'fatura_energia' => 'float',
    ];
}
```

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter CorrigirFaturaEnergiaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add api-laravel/database/migrations/2026_06_17_000000_create_fatura_fonte_table.php \
        api-laravel/app/Models/FaturaFonte.php \
        api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php
git commit -m "feat(faturamento): tabela-fonte fatura_fonte (uc, competencia, fatura)"
```

---

## Task 2: Comando `faturamento:importar-fatura-fonte`

**Files:**
- Create: `api-laravel/app/Console/Commands/ImportarFaturaFonte.php`
- Test: `api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php` (adicionar método)

**Interfaces:**
- Consumes: model `FaturaFonte` (Task 1).
- Produces: comando `faturamento:importar-fatura-fonte {arquivo}` — lê CSV com cabeçalho `uc,competencia,fatura_energia` (competência `YYYY-MM` ou `YYYY-MM-DD`) e faz `updateOrCreate` por (uc, competencia). Idempotente.

- [ ] **Step 1: Escrever o teste que falha**

Adicionar a `CorrigirFaturaEnergiaTest`:

```php
    public function test_importa_fatura_fonte_de_csv_idempotente(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'ff') . '.csv';
        file_put_contents($csv, "uc,competencia,fatura_energia\n521206860,2026-01,1663.71\n521206860,2026-02,1657.39\n");

        $this->artisan('faturamento:importar-fatura-fonte', ['arquivo' => $csv])->assertOk();
        $this->assertSame(2, \App\Models\FaturaFonte::count());

        // re-rodar não duplica
        $this->artisan('faturamento:importar-fatura-fonte', ['arquivo' => $csv])->assertOk();
        $this->assertSame(2, \App\Models\FaturaFonte::count());

        $jan = \App\Models\FaturaFonte::where('uc', '521206860')->where('competencia', '2026-01-01')->first();
        $this->assertEqualsWithDelta(1663.71, (float) $jan->fatura_energia, 0.001);

        @unlink($csv);
    }
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter test_importa_fatura_fonte_de_csv_idempotente`
Expected: FAIL — comando não existe ("Command "faturamento:importar-fatura-fonte" is not defined").

- [ ] **Step 3: Criar o comando**

Criar `api-laravel/app/Console/Commands/ImportarFaturaFonte.php`:

```php
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
```

- [ ] **Step 4: Rodar e confirmar passa**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter test_importa_fatura_fonte_de_csv_idempotente`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add api-laravel/app/Console/Commands/ImportarFaturaFonte.php api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php
git commit -m "feat(faturamento): comando importar-fatura-fonte (CSV -> tabela, idempotente)"
```

---

## Task 3: Comando `faturamento:corrigir-fatura` — precedência e correção

**Files:**
- Create: `api-laravel/app/Console/Commands/CorrigirFaturaEnergia.php`
- Test: `api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php` (adicionar métodos)

**Interfaces:**
- Consumes: `FaturamentoService::calcularMes(Usina $usina, int $ano, int $mes, array $input, bool $persistir, ?int $userId = null, ?string $idempotencyKey = null): RespostaCalculoMes`; model `FaturaFonte`; model `GeracaoFaturamentoPdf` (coluna `fatura_energia`, `competencia`, `usi_id`).
- Produces: comando `faturamento:corrigir-fatura {--usina=} {--dry-run}`. Para cada usina, em ordem cronológica, para cada mês com geração real, chama `calcularMes(persistir: !dryRun)` com a fatura por precedência. Em `--dry-run` não grava. Gera CSV `storage/reconstrucao/correcao-fatura-antes-depois.csv` com colunas `uc,competencia,valor_antes,valor_depois,delta,fatura_origem`.

- [ ] **Step 1: Escrever o teste de precedência (fatura de prod preservada)**

Adicionar a `CorrigirFaturaEnergiaTest`. Reusa os helpers de criação de usina (copiados de `ReconstruirLedgerReservaTest`; ver Step 1b). Cenário: usina média 1000; jan gera 800 (déficit, sem reserva → sem crédito); a fatura-fonte tem jan=50; o `geracao_faturamento_pdf` de prod já tem jan com fatura 0 (backfill). Após corrigir, o mês jan deve passar a usar fatura 50.

```php
    public function test_corrige_usa_fatura_fonte_quando_prod_zero(): void
    {
        // media 1000, jan gera 1500 (excedente, guarda 500), fev gera 800 (deficit 200, usa reserva)
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500, 'fevereiro' => 800],
        ]);
        // estado "pos-backfill": fatura 0 em ambos os meses
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        // fatura-fonte: jan e fev com fatura real
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-02-01', 'fatura_energia' => 40]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $pdf = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->get()
            ->keyBy(fn ($r) => \Illuminate\Support\Carbon::parse($r->competencia)->format('Y-m'));

        $this->assertEqualsWithDelta(30.0, (float) $pdf['2026-01']->fatura_energia, 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $pdf['2026-02']->fatura_energia, 0.001);
    }

    public function test_corrige_preserva_fatura_manual_de_prod(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500],
        ]);
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();

        // simula lançamento manual em prod: jan com fatura 99 (re-fatura com fatura informada)
        $usina = \App\Models\Usina::with(['comercializacao', 'dadoGeracao'])->where('uc', $uc)->first();
        app(\App\Application\Faturamento\FaturamentoService::class)->calcularMes(
            $usina, 2026, 1, ['geracao_bruta_kwh' => 1500, 'fatura_energia' => 99], persistir: true,
        );

        // fatura-fonte traz outro valor (deve ser IGNORADO, pois prod>0)
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $jan = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->first();
        $this->assertEqualsWithDelta(99.0, (float) $jan->fatura_energia, 0.001); // manual preservado
    }

    public function test_dry_run_nao_grava(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [2026 => ['janeiro' => 1500]]);
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc, '--dry-run' => true])->assertOk();

        $usiId = $this->usiId($uc);
        $jan = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->first();
        $this->assertEqualsWithDelta(0.0, (float) $jan->fatura_energia, 0.001); // dry-run: continua 0
    }
```

- [ ] **Step 1b: Adicionar os helpers de teste**

Copiar para `CorrigirFaturaEnergiaTest` os helpers `criarUsina`, `usiId`, `nomesMeses` exatamente como em `tests/Feature/ReconstruirLedgerReservaTest.php` (criação de endereco/cliente/dados_geracao/comercializacao/vendedor/usina/dados_geracao_real). Reproduzir o método `criarUsina` mostrado naquele arquivo, e:

```php
    private function usiId(string $uc): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('usina')->where('uc', $uc)->value('usi_id');
    }

    private function nomesMeses(): array
    {
        return [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'marco', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];
    }
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter CorrigirFaturaEnergiaTest`
Expected: FAIL — comando `faturamento:corrigir-fatura` não definido.

- [ ] **Step 3: Criar o comando**

Criar `api-laravel/app/Console/Commands/CorrigirFaturaEnergia.php`:

```php
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
```

- [ ] **Step 4: Rodar e confirmar passa**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter CorrigirFaturaEnergiaTest`
Expected: PASS (todos os métodos).

- [ ] **Step 5: Rodar a suíte completa de faturamento (regressão)**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter "Faturamento|Reconstruir|FaturamentoService|CorrigirFatura|CreditoLedger|PdfMotor"`
Expected: PASS (sem regressão nos testes existentes).

- [ ] **Step 6: Commit**

```bash
git add api-laravel/app/Console/Commands/CorrigirFaturaEnergia.php api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php
git commit -m "feat(faturamento): comando corrigir-fatura (precedência prod>fonte>0, dry-run, idempotente)"
```

---

## Task 4: Teste de idempotência do comando de correção

**Files:**
- Test: `api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php` (adicionar método)

**Interfaces:**
- Consumes: comando `faturamento:corrigir-fatura` (Task 3), `CreditoLedger`.

- [ ] **Step 1: Escrever o teste de idempotência**

```php
    public function test_corrigir_e_idempotente(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [
            2026 => ['janeiro' => 1500, 'fevereiro' => 800],
        ]);
        $this->artisan('ledger:reconstruir', ['--usina' => $uc])->assertOk();
        \App\Models\FaturaFonte::create(['uc' => $uc, 'competencia' => '2026-01-01', 'fatura_energia' => 30]);

        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $usiId = $this->usiId($uc);
        $ledgerAntes = \App\Models\CreditoLedger::doUsina($usiId)->count();
        $pdf1 = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->orderBy('competencia')
            ->pluck('valor_final')->map(fn ($v) => round((float) $v, 2))->all();

        // re-rodar
        $this->artisan('faturamento:corrigir-fatura', ['--usina' => $uc])->assertOk();

        $ledgerDepois = \App\Models\CreditoLedger::doUsina($usiId)->count();
        $pdf2 = \App\Models\GeracaoFaturamentoPdf::where('usi_id', $usiId)->orderBy('competencia')
            ->pluck('valor_final')->map(fn ($v) => round((float) $v, 2))->all();

        $this->assertSame($ledgerAntes, $ledgerDepois, 'ledger não pode duplicar ao re-rodar');
        $this->assertSame($pdf1, $pdf2, 'valores devem ser idênticos ao re-rodar');
    }
```

- [ ] **Step 2: Rodar e confirmar passa (já deve passar — idempotência herdada de calcularMes)**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter test_corrigir_e_idempotente`
Expected: PASS. (Se falhar, investigar a limpeza de evento em `gravarLedger` — não deve duplicar.)

- [ ] **Step 3: Commit**

```bash
git add api-laravel/tests/Feature/CorrigirFaturaEnergiaTest.php
git commit -m "test(faturamento): idempotência do corrigir-fatura (ledger não duplica)"
```

---

## Task 5: Script de extração da fatura-fonte do dump

**Files:**
- Create: `api-laravel/storage/reconstrucao/extrair_fatura_fonte.php`

**Interfaces:**
- Produces: script CLI que conecta no dump antigo restaurado (env `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS`) e escreve `storage/reconstrucao/fatura-fonte.csv` com cabeçalho `uc,competencia,fatura_energia`, derivando `fatura = max(cuo − geracao_kwh*fio_b*percentual_lei/100, 0)` por (usina, competência) de `geracao_faturamento_pdf`.

Este script NÃO tem teste automatizado (é utilitário operacional de leitura de um dump externo). É validado manualmente no Step 2.

- [ ] **Step 1: Criar o script**

Criar `api-laravel/storage/reconstrucao/extrair_fatura_fonte.php`:

```php
<?php
/**
 * Extrai a fatura-fonte (derivada do CUO do PDF antigo) do dump pré-correção.
 *   fatura = max(cuo − geracao_kwh × fio_b × percentual_lei / 100, 0)
 * Gera storage/reconstrucao/fatura-fonte.csv (uc,competencia,fatura_energia).
 *
 * Uso (contra um Postgres com o dump antigo restaurado):
 *   DB_HOST=... DB_PORT=... DB_NAME=... DB_USER=... DB_PASS=... \
 *     php storage/reconstrucao/extrair_fatura_fonte.php
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'dump_antigo';
$user = getenv('DB_USER') ?: 'app';
$pass = getenv('DB_PASS') ?: 'app';

$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = "SELECT u.uc,
               to_char(date_trunc('month', g.competencia), 'YYYY-MM') AS ym,
               GREATEST(g.cuo - g.geracao_kwh * c.fio_b * c.percentual_lei / 100.0, 0) AS fatura
        FROM geracao_faturamento_pdf g
        JOIN usina u ON u.usi_id = g.usi_id
        JOIN comercializacao c ON c.com_id = u.com_id
        ORDER BY u.uc, g.competencia";

$saida = __DIR__ . '/fatura-fonte.csv';
$f = fopen($saida, 'w');
fputcsv($f, ['uc', 'competencia', 'fatura_energia']);

$total = 0;
$comFatura = 0;
foreach ($pdo->query($sql) as $row) {
    $fatura = round((float) $row['fatura'], 2);
    fputcsv($f, [$row['uc'], $row['ym'], number_format($fatura, 2, '.', '')]);
    $total++;
    if ($fatura > 0) {
        $comFatura++;
    }
}
fclose($f);

echo "Geradas {$total} linhas ({$comFatura} com fatura > 0): {$saida}\n";
```

- [ ] **Step 2: Validar manualmente contra o dump antigo restaurado**

Restaurar o dump antigo num Postgres temporário e rodar:

```bash
# (de api-laravel/) — supõe dump antigo restaurado em infra-postgres-1 db dump_antigo
docker run --rm --add-host=host.docker.internal:host-gateway -v "$PWD":/app -w /app \
  -e DB_HOST=host.docker.internal -e DB_PORT=5433 -e DB_NAME=dump_antigo -e DB_USER=app -e DB_PASS=app \
  php-pgsql:latest php storage/reconstrucao/extrair_fatura_fonte.php
```

Expected: "Geradas 311 linhas (270 com fatura > 0)". Conferir no CSV que UC 521206860 (Romeu) Jan/2026 = 1663.71.

- [ ] **Step 3: Commit (só o script; o CSV é gitignored)**

```bash
git add api-laravel/storage/reconstrucao/extrair_fatura_fonte.php
git commit -m "feat(faturamento): script extrai fatura-fonte do dump antigo (CUO - fio_b)"
```

---

## Task 6: Auditoria refeita — pagar a expiração (PAGA TUDO)

**Files:**
- Modify: `api-laravel/storage/reconstrucao/reconstruir.php:248`

**Interfaces:**
- Consumes: `$res->valorFinal->emReais()`, `$res->receitaExpiracao->emReais()`.

Este arquivo é um relatório standalone (não tem teste de unidade). A validação é por inspeção do valor gerado para o caso âncora.

- [ ] **Step 1: Alterar a linha 248 para pagar a expiração**

Em `api-laravel/storage/reconstrucao/reconstruir.php`, trocar:

```php
        // Valor final SEM a receita de expiração retroativa: decisão de negócio é que
        // expiração retroativa NÃO é paga (o motor só a paga indo para frente).
        $finalD = $fa !== null ? $res->valorFinal->emReais() - $res->receitaExpiracao->emReais() : null;
```

por:

```php
        // PAGA TUDO (decisão 2026-06-17): expiração SEMPRE vira pagamento no mês do
        // vencimento, inclusive retroativo. O valor final correto é o cheio do motor.
        $finalD = $fa !== null ? $res->valorFinal->emReais() : null;
```

- [ ] **Step 2: Validar o caso âncora (Romeu Jan = 2.446,29)**

Rodar a auditoria contra o dump antigo restaurado (cópia temporária do script com a conexão do dump), filtrando o Romeu, e conferir que a coluna Valor Final D de Jan/2026 = 2.446,29 (não 1.058,75). Conferir também que o total "pagamos a mais"/"a menos" muda de acordo.

```bash
# cópia temporária com conexão do dump_antigo
cp storage/reconstrucao/reconstruir.php storage/reconstrucao/_audit_tmp.php
sed -i '' "s/const DB_NAME = 'energia_assinatura';/const DB_NAME = 'dump_antigo';/;s/const DB_USER = 'postgres';/const DB_USER = 'app';/;s/const DB_PASS = 'staging';/const DB_PASS = 'app';/" storage/reconstrucao/_audit_tmp.php
docker run --rm --add-host=host.docker.internal:host-gateway -v "$PWD":/app -w /app \
  -e DB_HOST=host.docker.internal -e DB_PORT=5433 php-pgsql:latest \
  php storage/reconstrucao/_audit_tmp.php --uc=521206860
rm -f storage/reconstrucao/_audit_tmp.php storage/reconstrucao/relatorio.html storage/reconstrucao/relatorio-antes-depois.csv
```

Expected: a linha de Jan/2026 do Romeu mostra credD 0 (sem déficit) e o valor final D reflete o cheio (com a expiração paga).

- [ ] **Step 3: Commit**

```bash
git add api-laravel/storage/reconstrucao/reconstruir.php
git commit -m "fix(auditoria): PAGA TUDO — valor final inclui expiração (revoga subtração da §12)"
```

---

## Task 7: Auditoria — corrigir o bug do 1º mês (fatura não aplicada)

**Files:**
- Modify: `api-laravel/storage/reconstrucao/reconstruir.php` (bloco de montagem do `faturaEnergia` / `mesesRaw`, ~linhas 168-194)

**Interfaces:**
- Nenhuma nova.

- [ ] **Step 1: Investigar a causa-raiz**

Rodar a auditoria filtrando UC 109983181 (caso detectado), comparando a fatura aplicada em Jan/2026 vs Fev–Mai. Identificar por que o 1º mês com PDF não recebe a fatura derivada (suspeita: borda na associação `fatAntes[$compKey]` vs ordenação, ou competência do 1º mês sem match por formato de data).

```bash
cp storage/reconstrucao/reconstruir.php storage/reconstrucao/_audit_tmp.php
sed -i '' "s/const DB_NAME = 'energia_assinatura';/const DB_NAME = 'dump_antigo';/;s/const DB_USER = 'postgres';/const DB_USER = 'app';/;s/const DB_PASS = 'staging';/const DB_PASS = 'app';/" storage/reconstrucao/_audit_tmp.php
docker run --rm --add-host=host.docker.internal:host-gateway -v "$PWD":/app -w /app \
  -e DB_HOST=host.docker.internal -e DB_PORT=5433 php-pgsql:latest \
  php storage/reconstrucao/_audit_tmp.php --uc=109983181
rm -f storage/reconstrucao/_audit_tmp.php storage/reconstrucao/relatorio.html storage/reconstrucao/relatorio-antes-depois.csv
```

Expected: identificar a linha onde a fatura do 1º mês fica 0 indevidamente.

- [ ] **Step 2: Corrigir a montagem da fatura para o 1º mês**

Aplicar a correção mínima no ponto identificado (garantir que `faturaEnergia` derivada do `cuo` ANTES seja aplicada a TODOS os meses com PDF, inclusive o primeiro). O comando de correção do sistema (Task 3) já está correto — esta correção é só para a auditoria bater com o sistema.

> Nota: a correção exata depende do achado do Step 1. Se for um problema de match de competência, normalizar a chave `$compKey` da mesma forma nos dois pontos (montagem de `mesesRaw` e cruzamento `fatAntes`). Documentar no commit o que era.

- [ ] **Step 3: Validar que UC 109983181 Jan/2026 passa a aplicar a fatura**

Rodar de novo o comando do Step 1 e confirmar que Jan/2026 agora tem a fatura derivada aplicada (valor final D reduzido pela fatura, batendo com o que o comando `corrigir-fatura` produz para essa usina).

- [ ] **Step 4: Commit**

```bash
git add api-laravel/storage/reconstrucao/reconstruir.php
git commit -m "fix(auditoria): aplica fatura derivada no 1º mês (bug UC 109983181)"
```

---

## Task 8: Atualizar a regra escrita (REGRAS_DE_CALCULO.md §7/§12)

**Files:**
- Modify: `docs/calculo/REGRAS_DE_CALCULO.md` (§7 Expiração; §12 backfill)

**Interfaces:**
- Nenhuma.

- [ ] **Step 1: Reescrever §7 (destino da expiração) para PAGA TUDO**

Em `docs/calculo/REGRAS_DE_CALCULO.md` §7, substituir o bullet "Exceção — backfill retroativo (§12)" e ajustar o "Destino" para deixar explícito:

```markdown
- **Destino (PAGA TUDO):** o crédito expirado **vira receita em dinheiro**
  (`kwh_expirado × tarifa`) no **mês do vencimento**, somado ao Valor a Receber —
  **inclusive retroativamente** (crédito que venceu antes do go-live também é pago).
  **Não** é contado em dobro (não soma ao termo Crédito e ao faturamento simultaneamente;
  o serviço de expiração só considera o que **sobrou** após o consumo FIFO do mês).
```

Remover o bullet da exceção retroativa.

- [ ] **Step 2: Atualizar a §12 (backfill) para refletir PAGA TUDO**

Em §12, remover/ajustar o trecho que dizia "crédito vencido no passado é apenas removido da reserva SEM pagamento". Substituir por:

```markdown
> **Decisão 2026-06-17 (PAGA TUDO):** o backfill paga a expiração no mês do vencimento,
> inclusive retroativo. O `valorFinal` persistido inclui a `receitaExpiracao` — o sistema
> reflete o que **deveria** ter sido pago. (O que já foi pago ao cliente no passado não é
> reajustado; a auditoria pago-antigo × correto é a prova histórica.)
```

- [ ] **Step 3: Commit**

```bash
git add docs/calculo/REGRAS_DE_CALCULO.md
git commit -m "docs(regras): §7/§12 PAGA TUDO — expiração sempre paga, inclusive retroativo"
```

---

## Task 9: Runbook de aplicação em produção

**Files:**
- Create: `docs/superpowers/plans/runbook-correcao-fatura.md`

**Interfaces:**
- Nenhuma. Documento operacional.

- [ ] **Step 1: Escrever o runbook**

Criar `docs/superpowers/plans/runbook-correcao-fatura.md` com os passos exatos (backup → extrair fatura-fonte do dump → importar → dry-run em prod → revisão do CSV → aplicar em transação → refazer auditoria → validar tela). Incluir os comandos `php artisan` reais e o critério de aceite (Romeu Jan = 2.446,29; 4 lançamentos manuais preservados; 222 meses em fatura 0 listados).

```markdown
# Runbook — Correção da fatura_energia em produção (PAGA TUDO)

1. **Backup do banco de produção.** (obrigatório, antes de tudo)
2. **Migrations:** `php artisan migrate --force` (cria `fatura_fonte`).
3. **Extrair fatura-fonte do dump antigo** (Postgres temporário com o dump restaurado):
   `DB_HOST=... DB_NAME=dump_antigo ... php storage/reconstrucao/extrair_fatura_fonte.php`
   → conferir `fatura-fonte.csv` (≈311 linhas, 270 com fatura; Romeu Jan = 1663.71).
4. **Importar fatura-fonte em produção:**
   `php artisan faturamento:importar-fatura-fonte storage/reconstrucao/fatura-fonte.csv`
5. **Dry-run em produção:**
   `php artisan faturamento:corrigir-fatura --dry-run`
   → revisar `correcao-fatura-antes-depois.csv`:
     - Romeu Jan: 4110,00 → 2446,29
     - 4 lançamentos manuais com origem `prod` e delta 0,00
     - meses origem `zero` = lista para revisão manual
6. **Aplicar:** `php artisan faturamento:corrigir-fatura` (roda em transação).
7. **Refazer a auditoria** (pago-antigo × correto) e arquivar como prova histórica.
8. **Validar a tela** do Romeu (forçar refresh): Janeiro = 2.446,29.

Rollback: restaurar o backup do passo 1.
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/plans/runbook-correcao-fatura.md
git commit -m "docs(faturamento): runbook de aplicação da correção em produção"
```

---

## Self-Review

**Spec coverage:**
- Tabela-fonte (spec §3.1) → Tasks 1, 2, 5. ✓
- Comando `corrigir-fatura` (§3.2: precedência, idempotente, dry-run, guard) → Tasks 3, 4. ✓
- Auditoria refeita (§3.3: pagar expiração + bug 1º mês) → Tasks 6, 7. ✓
- Regra escrita (§3.4) → Task 8. ✓
- Fluxo de aplicação (§4) → Task 9 (runbook). ✓
- "ANTES = pago antigo (dump)" na auditoria: a `finalA` já é `geracao_faturamento_pdf.valor_final` do dump — preservado (não alterado). ✓

**Placeholder scan:** Task 7 Step 2 depende do achado do Step 1 (investigação) — é uma correção cuja forma exata só se conhece após reproduzir o bug; documentado como tal, com a direção (normalizar a chave de competência) e o critério de aceite. Demais steps têm código completo.

**Type consistency:** `calcularMes(...persistir, userId, idempotencyKey)` e `RespostaCalculoMes->resultado->valorFinal->emReais()` conferidos no código real. `FaturaFonte` (uc/competencia/fatura_energia) consistente entre Tasks 1, 2, 3, 5. `GeracaoFaturamentoPdf.fatura_energia`/`valor_final`/`competencia`/`usi_id` conferidos. Comando `ledger:reconstruir` usado nos testes existe.

---

## Execution Handoff

Plano salvo. Próximo passo: escolher modo de execução.
