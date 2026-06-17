# Auditoria Fase 1 — Baseline no banco (Antes + Pago) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persistir no banco, por usina/mês, o "valor que estava no sistema" (1º dump) e o "valor efetivamente pago" (planilha), via uma tabela `auditoria_baseline` + extratores offline (CSV) + um comando de import idempotente.

**Architecture:** Tabela `auditoria_baseline` (uma linha por usina/competência). Dois extratores offline geram CSVs (Antes do dump `energia_antes`; Pago da planilha, com mapa de UC + swap de maio). Um comando Laravel `auditoria:importar-baseline` carrega os CSVs na tabela (CSV→tabela, idempotente, casando por UC). O "Atual" NÃO entra nesta fase (vem do motor, na Fase 2).

**Tech Stack:** PHP 8.3, Laravel, PostgreSQL (prod), SQLite in-memory (testes), PHPUnit 11; Python 3 + psql (extratores offline).

## Global Constraints

- Tabela `auditoria_baseline`: `ab_id, usi_id (unsignedInteger, FK usina.usi_id cascade), competencia (date dia 1), valor_sistema_antes (decimal 12,2 nullable), valor_pago (decimal 12,2 nullable), fatura_informada (decimal 12,2 nullable), consumo_informado (decimal 12,2 nullable), timestamps, unique(usi_id, competencia)`.
- Import **idempotente**: `updateOrCreate` por (usi_id, competencia); re-rodar não duplica.
- Comando casa UC→usi_id via `usina.uc`; UC sem usina = ignorada e **contada/reportada**, nunca aborta.
- Mapa UC planilha→banco (6 casos, no extrator do pago): 43044→521206860, 47180→562606800, 2208→113906836, 59098332→6656137, 4189733→41897333, 59244413→9244413.
- Swap de maio (extrator do pago): por par, a coluna ≈ fatura de referência É a fatura; a outra é o pago (3 swaps: Romeu, Eder, Edo).
- Caso âncora: Romeu (UC 521206860) 2026-01 → antes ≈ 1.059,21, pago = 1.058,75; 2026-05 pago = 3.850,86 (swap resolvido).
- Testes: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter <X>` (de `api-laravel/`).
- Extratores rodam offline contra dump/planilha; sem teste automatizado, validados por inspeção.

---

## File Structure

- `api-laravel/database/migrations/2026_06_17_100000_create_auditoria_baseline_table.php` — **novo** schema.
- `api-laravel/app/Models/AuditoriaBaseline.php` — **novo** model.
- `api-laravel/app/Console/Commands/ImportarAuditoriaBaseline.php` — **novo** comando `auditoria:importar-baseline`.
- `api-laravel/tests/Feature/AuditoriaBaselineTest.php` — **novo** teste.
- `api-laravel/storage/reconstrucao/extrair_antes.php` — **novo** extrator do Antes (dump).
- `api-laravel/storage/reconstrucao/extrair_pago_planilha.py` — **novo** extrator do Pago (planilha).

---

## Task 1: Migration + Model `auditoria_baseline`

**Files:**
- Create: `api-laravel/database/migrations/2026_06_17_100000_create_auditoria_baseline_table.php`
- Create: `api-laravel/app/Models/AuditoriaBaseline.php`
- Test: `api-laravel/tests/Feature/AuditoriaBaselineTest.php`

**Interfaces:**
- Produces: tabela `auditoria_baseline` e model `App\Models\AuditoriaBaseline` com `$fillable=['usi_id','competencia','valor_sistema_antes','valor_pago','fatura_informada','consumo_informado']`, casts (`competencia`=>`date:Y-m-d`, os 4 valores=>`float`), mutator de `competencia` (normaliza `Y-m-d`), `$primaryKey='ab_id'`.

- [ ] **Step 1: Escrever o teste que falha**

Criar `api-laravel/tests/Feature/AuditoriaBaselineTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditoriaBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_armazena_por_usina_competencia(): void
    {
        AuditoriaBaseline::create([
            'usi_id' => 24,
            'competencia' => '2026-01',
            'valor_sistema_antes' => 1059.21,
            'valor_pago' => 1058.75,
        ]);

        $r = AuditoriaBaseline::where('usi_id', 24)->first();

        $this->assertNotNull($r);
        $this->assertSame('2026-01', $r->competencia->format('Y-m'));
        $this->assertEqualsWithDelta(1059.21, (float) $r->valor_sistema_antes, 0.001);
        $this->assertEqualsWithDelta(1058.75, (float) $r->valor_pago, 0.001);
        $this->assertNull($r->fatura_informada);
    }
}
```

- [ ] **Step 2: Rodar e confirmar falha**

Run (de `api-laravel/`): `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter AuditoriaBaselineTest`
Expected: FAIL — "Class AuditoriaBaseline not found".

- [ ] **Step 3: Criar a migration**

Criar `api-laravel/database/migrations/2026_06_17_100000_create_auditoria_baseline_table.php`:

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
        Schema::create('auditoria_baseline', function (Blueprint $table) {
            $table->increments('ab_id');
            $table->unsignedInteger('usi_id');
            $table->date('competencia');
            $table->decimal('valor_sistema_antes', 12, 2)->nullable();
            $table->decimal('valor_pago', 12, 2)->nullable();
            $table->decimal('fatura_informada', 12, 2)->nullable();
            $table->decimal('consumo_informado', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['usi_id', 'competencia']);
            $table->foreign('usi_id')->references('usi_id')->on('usina')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_baseline');
    }
};
```

- [ ] **Step 4: Criar o model**

Criar `api-laravel/app/Models/AuditoriaBaseline.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaBaseline extends Model
{
    protected $table = 'auditoria_baseline';

    protected $primaryKey = 'ab_id';

    protected $fillable = [
        'usi_id', 'competencia', 'valor_sistema_antes', 'valor_pago',
        'fatura_informada', 'consumo_informado',
    ];

    protected $casts = [
        'competencia' => 'date:Y-m-d',
        'valor_sistema_antes' => 'float',
        'valor_pago' => 'float',
        'fatura_informada' => 'float',
        'consumo_informado' => 'float',
    ];

    protected function setCompetenciaAttribute($value): void
    {
        $this->attributes['competencia'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
    }
}
```

- [ ] **Step 5: Rodar e confirmar passa**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter AuditoriaBaselineTest`
Expected: PASS.

> Nota: o teste usa `usi_id => 24` sem criar a usina. Em SQLite a FK não é forçada por padrão (consistente com os outros testes do projeto que inserem usi_id direto). Se o ambiente forçar FK e o teste falhar por isso, criar uma usina mínima antes (padrão `criarUsina` de `ReconstruirLedgerReservaTest`). Verificar no Step 5; se falhar por FK, ajustar adicionando a usina.

- [ ] **Step 6: Commit**

```bash
git add api-laravel/database/migrations/2026_06_17_100000_create_auditoria_baseline_table.php \
        api-laravel/app/Models/AuditoriaBaseline.php \
        api-laravel/tests/Feature/AuditoriaBaselineTest.php
git commit -m "feat(auditoria): tabela e model auditoria_baseline (antes + pago por usina/mês)"
```

---

## Task 2: Comando `auditoria:importar-baseline`

**Files:**
- Create: `api-laravel/app/Console/Commands/ImportarAuditoriaBaseline.php`
- Test: `api-laravel/tests/Feature/AuditoriaBaselineTest.php` (adicionar métodos)

**Interfaces:**
- Consumes: model `AuditoriaBaseline` (Task 1); tabela `usina` (coluna `uc`, `usi_id`).
- Produces: comando `auditoria:importar-baseline {--antes=} {--pago=}`. Cada opção é o caminho de um CSV com cabeçalho `uc,competencia,valor`. `--antes` grava em `valor_sistema_antes`; `--pago` grava em `valor_pago`. updateOrCreate por (usi_id, competencia). UC sem usina = ignorada (contada). Competência aceita `YYYY-MM` ou `YYYY-MM-DD`.

- [ ] **Step 1: Escrever os testes que falham**

Adicionar a `AuditoriaBaselineTest` (usa os helpers `criarUsina`/`usiId` copiados de `ReconstruirLedgerReservaTest` — ver Step 1b):

```php
    public function test_importa_antes_e_pago_idempotente(): void
    {
        $uc = $this->criarUsina(media: 1000, geracaoPorAno: [2026 => ['janeiro' => 1200]]);
        $usiId = $this->usiId($uc);

        $antes = tempnam(sys_get_temp_dir(), 'a') . '.csv';
        file_put_contents($antes, "uc,competencia,valor\n{$uc},2026-01,1059.21\n");
        $pago = tempnam(sys_get_temp_dir(), 'p') . '.csv';
        file_put_contents($pago, "uc,competencia,valor\n{$uc},2026-01,1058.75\n");

        $this->artisan('auditoria:importar-baseline', ['--antes' => $antes, '--pago' => $pago])->assertOk();

        $r = \App\Models\AuditoriaBaseline::where('usi_id', $usiId)->where('competencia', '2026-01-01')->first();
        $this->assertEqualsWithDelta(1059.21, (float) $r->valor_sistema_antes, 0.001);
        $this->assertEqualsWithDelta(1058.75, (float) $r->valor_pago, 0.001);

        // re-rodar não duplica
        $this->artisan('auditoria:importar-baseline', ['--antes' => $antes, '--pago' => $pago])->assertOk();
        $this->assertSame(1, \App\Models\AuditoriaBaseline::where('usi_id', $usiId)->count());

        @unlink($antes); @unlink($pago);
    }

    public function test_uc_sem_usina_e_ignorada_sem_quebrar(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'x') . '.csv';
        file_put_contents($csv, "uc,competencia,valor\n00000000,2026-01,500.00\n");

        $this->artisan('auditoria:importar-baseline', ['--pago' => $csv])->assertOk();
        $this->assertSame(0, \App\Models\AuditoriaBaseline::count());

        @unlink($csv);
    }
```

- [ ] **Step 1b: Adicionar helpers de teste**

Copiar para `AuditoriaBaselineTest` os helpers `criarUsina` e `usiId` exatamente como em `api-laravel/tests/Feature/ReconstruirLedgerReservaTest.php` (o método `criarUsina(float $media, array $geracaoPorAno): string` que insere endereco/cliente/dados_geracao/comercializacao/vendedor/usina/dados_geracao_real e retorna a UC), e:

```php
    private function usiId(string $uc): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('usina')->where('uc', $uc)->value('usi_id');
    }

    private function nomesMeses(): array
    {
        return [1=>'janeiro',2=>'fevereiro',3=>'marco',4=>'abril',5=>'maio',6=>'junho',
                7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    }
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter AuditoriaBaselineTest`
Expected: FAIL — comando `auditoria:importar-baseline` não definido.

- [ ] **Step 3: Criar o comando**

Criar `api-laravel/app/Console/Commands/ImportarAuditoriaBaseline.php`:

```php
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
```

- [ ] **Step 4: Rodar e confirmar passa**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter AuditoriaBaselineTest`
Expected: PASS (todos os métodos).

- [ ] **Step 5: Regressão da suíte de faturamento**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter "Auditoria|Faturamento|Reconstruir|CorrigirFatura|CreditoLedger|PdfMotor"`
Expected: PASS (sem regressão).

- [ ] **Step 6: Commit**

```bash
git add api-laravel/app/Console/Commands/ImportarAuditoriaBaseline.php api-laravel/tests/Feature/AuditoriaBaselineTest.php
git commit -m "feat(auditoria): comando importar-baseline (CSV antes/pago -> tabela, idempotente)"
```

---

## Task 3: Extrator do "Antes" (do 1º dump)

**Files:**
- Create: `api-laravel/storage/reconstrucao/extrair_antes.php`

**Interfaces:**
- Produces: script CLI que conecta no dump `energia_antes` restaurado (env DB_*) e escreve `storage/reconstrucao/auditoria-antes.csv` com `uc,competencia,valor` (valor = `geracao_faturamento_pdf.valor_final`).

Utilitário offline — sem teste automatizado; validado no Step 2.

- [ ] **Step 1: Criar o script**

Criar `api-laravel/storage/reconstrucao/extrair_antes.php`:

```php
<?php
/**
 * Extrai o "Antes" (valor que estava no sistema original) do 1º dump (energia_antes):
 * geracao_faturamento_pdf.valor_final por (uc, competência).
 * Gera storage/reconstrucao/auditoria-antes.csv (uc,competencia,valor).
 *
 * Uso (contra o dump energia_antes restaurado num Postgres):
 *   DB_HOST=... DB_PORT=... DB_NAME=energia_antes DB_USER=... DB_PASS=... \
 *     php storage/reconstrucao/extrair_antes.php
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'energia_antes';
$user = getenv('DB_USER') ?: 'app';
$pass = getenv('DB_PASS') ?: 'app';

$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = "SELECT u.uc,
               to_char(date_trunc('month', g.competencia), 'YYYY-MM') AS ym,
               round(g.valor_final::numeric, 2) AS valor
        FROM geracao_faturamento_pdf g
        JOIN usina u ON u.usi_id = g.usi_id
        ORDER BY u.uc, g.competencia";

$saida = __DIR__ . '/auditoria-antes.csv';
$f = fopen($saida, 'w');
fputcsv($f, ['uc', 'competencia', 'valor']);
$n = 0;
foreach ($pdo->query($sql) as $row) {
    fputcsv($f, [$row['uc'], $row['ym'], number_format((float) $row['valor'], 2, '.', '')]);
    $n++;
}
fclose($f);
echo "Geradas {$n} linhas: {$saida}\n";
```

- [ ] **Step 2: Validar contra o dump energia_antes restaurado**

Com o dump `energia_antes` restaurado num Postgres acessível, rodar (ajustar env conforme o ambiente):

```bash
docker run --rm --add-host=host.docker.internal:host-gateway -v "$PWD":/app -w /app \
  -e DB_HOST=host.docker.internal -e DB_PORT=5432 -e DB_NAME=energia_antes -e DB_USER=arco -e DB_PASS=arco_dev_password \
  php-pgsql:latest php storage/reconstrucao/extrair_antes.php
grep '^521206860,2026-01' storage/reconstrucao/auditoria-antes.csv
```
Expected: "Geradas 311 linhas"; Romeu 2026-01 ≈ `1059.21`.

- [ ] **Step 3: Commit (só o script; CSV é gitignored)**

```bash
git add api-laravel/storage/reconstrucao/extrair_antes.php
git commit -m "feat(auditoria): extrator do Antes (valor_final do 1º dump -> CSV)"
```

---

## Task 4: Extrator do "Pago" (da planilha)

**Files:**
- Create: `api-laravel/storage/reconstrucao/extrair_pago_planilha.py`

**Interfaces:**
- Produces: script Python que lê o CSV da planilha (caminho via `argv[1]`) + um CSV de fatura de referência (`argv[2]`, formato `uc,competencia,fatura`) e escreve `storage/reconstrucao/auditoria-pago.csv` com `uc,competencia,valor` (UC já mapeada para o banco; swaps de maio resolvidos).

Utilitário offline — sem teste automatizado; validado no Step 2.

- [ ] **Step 1: Criar o script**

Criar `api-laravel/storage/reconstrucao/extrair_pago_planilha.py`:

```python
#!/usr/bin/env python3
"""
Extrai o "Efetivamente pago" da planilha (aba Faturamento Usinas) -> auditoria-pago.csv
(uc_banco, competencia, valor). Resolve mapa de UC (6 casos) e o swap de maio.

Uso:
  python3 extrair_pago_planilha.py "<planilha.csv>" "<fatura-fonte.csv>"
    fatura-fonte.csv: uc,competencia,fatura  (referência p/ detectar o swap)
"""
import csv, re, sys, os

PLAN = sys.argv[1] if len(sys.argv) > 1 else "Controle geral Consorcio.xlsx - Faturamento Usinas.csv"
FATREF = sys.argv[2] if len(sys.argv) > 2 else ""
SAIDA = os.path.join(os.path.dirname(os.path.abspath(__file__)), "auditoria-pago.csv")

MANUAL = {'2208':'113906836','43044':'521206860','47180':'562606800',
          '59098332':'6656137','4189733':'41897333','59244413':'9244413'}
UNI = [('2025-05',12),('2025-06',13),('2025-07',14),('2025-08',15),('2025-09',16),
       ('2025-10',17),('2025-11',18),('2025-12',19),('2026-01',20),('2026-02',21)]
PARES = [('2026-03',22,23),('2026-04',24,25),('2026-05',26,27),('2026-06',28,29),
         ('2026-07',30,31),('2026-08',32,33),('2026-09',34,35)]

def brl(s):
    if not s or not s.strip(): return None
    t = s.replace('R$','').replace(' ','').strip()
    if t in ('-','—'): return 0.0
    if not re.search(r'\d', t): return None
    try: return float(t.replace('.','').replace(',','.'))
    except: return None

# fatura de referência p/ swap
fat = {}
if FATREF and os.path.isfile(FATREF):
    for row in csv.DictReader(open(FATREF, encoding='utf-8')):
        fat[(re.sub(r'\D','',row['uc']), row['competencia'][:7])] = float(row['fatura'])

rows = list(csv.reader(open(PLAN, encoding='utf-8')))
out = open(SAIDA, 'w', newline='')
w = csv.writer(out); w.writerow(['uc','competencia','valor'])
n = 0; swaps = 0
for i in range(2, len(rows)):
    r = rows[i]; nome = (r[0] or '').strip()
    if not nome or nome.upper().startswith('USINAS EM PROCESSO'):
        if nome.upper().startswith('USINAS EM PROCESSO'): break
        continue
    uc = re.sub(r'\D','', (r[3] or '')); buc = MANUAL.get(uc, uc)
    if not buc: continue
    for m, idx in UNI:
        if idx < len(r):
            v = brl(r[idx])
            if v is not None: w.writerow([buc, m, f"{v:.2f}"]); n += 1
    for m, cp, cc in PARES:
        a = brl(r[cp]) if cp < len(r) else None
        b = brl(r[cc]) if cc < len(r) else None
        if a is None and b is None: continue
        if a is None: pago = b
        elif b is None: pago = a
        else:
            kf = fat.get((buc, m))
            if kf is not None and abs(b - kf) > abs(a - kf):
                pago = b; swaps += 1   # coluna "pago" é a fatura -> swap
            else:
                pago = a
        w.writerow([buc, m, f"{pago:.2f}"]); n += 1
out.close()
print(f"Geradas {n} linhas ({swaps} swaps de maio resolvidos): {SAIDA}")
```

- [ ] **Step 2: Validar contra a planilha + fatura-fonte**

```bash
cd api-laravel
python3 storage/reconstrucao/extrair_pago_planilha.py \
  "/Users/matheus/Downloads/Controle geral Consorcio.xlsx - Faturamento Usinas.csv" \
  storage/reconstrucao/fatura-fonte.csv
grep '^521206860,2026-01' storage/reconstrucao/auditoria-pago.csv   # esperado 1058.75
grep '^521206860,2026-05' storage/reconstrucao/auditoria-pago.csv   # esperado 3850.86 (swap)
```
Expected: "Geradas 501 linhas (3 swaps de maio resolvidos)"; Romeu 2026-01 = `1058.75`, 2026-05 = `3850.86`.

> Pré-requisito: `storage/reconstrucao/fatura-fonte.csv` existe (gerado por `extrair_fatura_fonte.php` da correção anterior). Se não existir, gerá-lo antes (contra o dump antigo).

- [ ] **Step 3: Commit (só o script; CSV é gitignored)**

```bash
git add api-laravel/storage/reconstrucao/extrair_pago_planilha.py
git commit -m "feat(auditoria): extrator do Pago (planilha -> CSV, mapa UC + swap de maio)"
```

---

## Task 5: Runbook de carga do baseline

**Files:**
- Create: `docs/superpowers/plans/runbook-auditoria-baseline.md`

- [ ] **Step 1: Escrever o runbook**

Criar `docs/superpowers/plans/runbook-auditoria-baseline.md` com os passos para popular o baseline em produção (após o deploy desta fase):

```markdown
# Runbook — Carga do baseline da auditoria (Fase 1)

Pré: PR da Fase 1 deployado (migration + comando). Backup do banco recomendado.

1. **Migration:** `php artisan migrate --force` (cria auditoria_baseline).
2. **Gerar o CSV do Antes** (offline): restaurar o 1º dump `energia_antes_20260611_164628.dump`
   num Postgres temporário e rodar `extrair_antes.php` (DB_NAME=energia_antes) →
   `auditoria-antes.csv` (≈311 linhas; Romeu 2026-01 ≈ 1059,21).
3. **Gerar o CSV do Pago** (offline): rodar `extrair_pago_planilha.py` com a planilha +
   `fatura-fonte.csv` → `auditoria-pago.csv` (501 linhas; 3 swaps; Romeu 2026-01=1058,75,
   2026-05=3850,86).
4. **Copiar os 2 CSVs** para o servidor (SCP via bastion, como na correção de fatura).
5. **Importar:** `php artisan auditoria:importar-baseline --antes=/tmp/auditoria-antes.csv --pago=/tmp/auditoria-pago.csv`
   → conferir contagens de gravados/ignorados.
6. **Conferir:** Romeu (UC 521206860) 2026-01 com valor_sistema_antes≈1059,21 e valor_pago=1058,75.

As 3 pendências (Solar Jungblut/Zito/Luciane) podem aparecer como "ignoradas" se a UC/mês
não casar — registrado, sem ação nesta fase.
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/plans/runbook-auditoria-baseline.md
git commit -m "docs(auditoria): runbook de carga do baseline (Fase 1)"
```

---

## Self-Review

**Spec coverage:**
- Tabela `auditoria_baseline` (spec §Componentes 1) → Task 1. ✓
- Model (spec §2) → Task 1. ✓
- Extrator Antes (spec §3) → Task 3. ✓
- Extrator Pago com mapa UC + swap (spec §3) → Task 4. ✓
- Comando import idempotente, UC ignorada (spec §4) → Task 2. ✓
- Critérios de aceite (Romeu antes/pago/swap) → validados nas Tasks 2/3/4. ✓
- Aplicação em prod → Task 5 (runbook). ✓

**Placeholder scan:** Task 1 Step 5 tem nota condicional sobre FK no SQLite (instrução concreta de verificação, não placeholder). Demais steps com código completo.

**Type consistency:** `AuditoriaBaseline` com `$fillable`/casts/`ab_id`/mutator consistentes entre Task 1 e 2. Comando grava em `valor_sistema_antes`/`valor_pago` (nomes batem com a migration). CSVs com cabeçalho `uc,competencia,valor` consistente entre extratores (Tasks 3/4) e o comando (Task 2). Swap detector idêntico ao validado na análise.

---

## Execution Handoff

Plano salvo. Próximo: escolher modo de execução.
