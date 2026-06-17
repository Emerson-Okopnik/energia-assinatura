# Auditoria Fase 2 — Página (Antes · Efetivamente pago · Atual) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A página de auditoria no app: lista leve de usinas (saldo pago−atual, meses, inconclusivos) + pop-up lazy com o detalhe mês a mês (Antes/Efetivamente pago/Atual + conta nos termos do app), read-only.

**Architecture:** Backend: `AuditoriaService` (2 consultas SQL sobre `auditoria_baseline` + `geracao_faturamento_pdf`) exposto por `AuditoriaController` em 2 endpoints. "Atual" = `geracao_faturamento_pdf.valor_final` (sem motor nesta fase). Frontend: util puro de formatação/rótulos + service + página `Auditoria.vue` (lista/busca/cards) + modal `AuditoriaUsinaModal.vue` (lazy). Tudo amarrado ao contrato de API do spec.

**Tech Stack:** PHP 8.3/Laravel, PostgreSQL (prod) / SQLite (testes), PHPUnit 11; Vue 3 `<script setup>`, Vite, node:test.

## Global Constraints

- 3 colunas/nomes: **Antes** · **Efetivamente pago** · **Atual**. Diferença principal = **Efetivamente pago − Atual** (pago<atual = "pagamos a menos"; pago>atual = "a mais").
- **Inconclusivo** = `geracao_faturamento_pdf.fatura_energia` 0/nula OU sem `atual` → fora dos totais.
- Conta do Atual (termos do app): `Fixo + Injetado + Crédito − CUO + Crédito expirado = Valor final`; `credito_expirado = valor_final − (fixo+injetado+creditado−cuo)`.
- Contrato de API (shape exato) conforme o spec §"Arquitetura/contrato". Valores em reais (float 2 casas) no backend; formatação no front (`formatReais`/`formatKwh`).
- Read-only (sem alterar dados); sob `auth:api`.
- Reusar componentes base (`StatValue`, `DataTable`, `BaseBadge`, `BaseButton`) e o design system; sem travessões soltos; linguagem simples.
- Backend tests: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter <X>` (de `api-laravel/`). Front tests: `node --test tests/*.test.js` (de `front/`). Build front: `npm run build`.

---

## File Structure

- `api-laravel/app/Services/AuditoriaService.php` — **novo**: `listaUsinas(): array`, `detalheUsina(int $usiId): array`.
- `api-laravel/app/Http/Controllers/AuditoriaController.php` — **novo**: `usinas()`, `usina(int $usiId)`.
- `api-laravel/routes/api.php` — **modificar**: 2 rotas no grupo `auth:api`.
- `api-laravel/tests/Feature/AuditoriaApiTest.php` — **novo**: testes dos endpoints/service.
- `front/src/utils/auditoria.js` — **novo**: funções puras (status, diferença rotulada, linha da conta).
- `front/tests/auditoria.test.js` — **novo**: testes do util.
- `front/src/services/auditoriaApi.js` — **novo**: `obterAuditoriaUsinas()`, `obterAuditoriaUsina(usiId)`.
- `front/src/components/Auditoria.vue` — **novo**: página (cards + busca + lista).
- `front/src/components/faturamento/AuditoriaUsinaModal.vue` — **novo**: pop-up detalhe (lazy).
- `front/src/router/index.js` — **modificar**: rota `/auditoria`.
- `front/src/components/TheNavbar.vue` — **modificar**: link "Auditoria".

**Execução paralela:** Track Backend = Task 1 (só `api-laravel/`). Track Frontend = Tasks 2→3 (só `front/`). Task 1 e Task 2 rodam EM PARALELO (diretórios disjuntos, zero conflito). Task 3 depois da Task 2.

---

## Task 1 (Track Backend): AuditoriaService + endpoints + testes

**Files:**
- Create: `api-laravel/app/Services/AuditoriaService.php`
- Create: `api-laravel/app/Http/Controllers/AuditoriaController.php`
- Modify: `api-laravel/routes/api.php`
- Test: `api-laravel/tests/Feature/AuditoriaApiTest.php`

**Interfaces:**
- Produces (API contract):
  - `GET /auditoria/usinas` → `{ totais:{pago_a_mais,pago_a_menos,saldo,total_inconclusivos}, usinas:[{usi_id,uc,cliente,saldo,meses_divergentes,inconclusivos}] }`
  - `GET /auditoria/usinas/{usiId}` → `{ usina:{usi_id,uc,cliente}, resumo:{pago_total,atual_total,saldo}, meses:[{competencia,antes,pago,atual,fatura_atual,status,diferenca,termos:{fixo,injetado,credito,cuo,credito_expirado,valor_final}|null}] }`

- [ ] **Step 1: Escrever o teste que falha**

Criar `api-laravel/tests/Feature/AuditoriaApiTest.php`. Usa helpers de criação semeando `usina` + `auditoria_baseline` + `geracao_faturamento_pdf` (DB direto). Cenário: 1 usina, 2 meses — um conclusivo (fatura>0, pago≠atual) e um inconclusivo (fatura 0).

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_detalhe_usina_status_diferenca_e_termos(): void
    {
        $usiId = $this->seedUsina('UC777', 'Cliente 777');
        // mês conclusivo: fatura>0, antes/pago/atual
        $this->seedBaseline($usiId, '2026-01-01', antes: 1059.21, pago: 1058.75);
        $this->seedPdf($usiId, '2026-01-01', fixo: 2129.54, injetado: 1575.16, creditado: 0, cuo: 2645.95, valorFinal: 2446.29, fatura: 1663.71);
        // mês inconclusivo: fatura 0
        $this->seedBaseline($usiId, '2025-06-01', antes: null, pago: 2705.80);
        $this->seedPdf($usiId, '2025-06-01', fixo: 1000, injetado: 500, creditado: 0, cuo: 200, valorFinal: 1300, fatura: 0);

        $resp = $this->getJson("/api/auditoria/usinas/{$usiId}");
        $resp->assertOk();
        $meses = collect($resp->json('meses'))->keyBy('competencia');

        $jan = $meses['2026-01'];
        $this->assertSame('conclusivo', $jan['status']);
        $this->assertEqualsWithDelta(2446.29, $jan['atual'], 0.01);
        $this->assertEqualsWithDelta(1058.75 - 2446.29, $jan['diferenca'], 0.01);
        $this->assertEqualsWithDelta(1387.54, $jan['termos']['credito_expirado'], 0.01); // 2446.29 - (2129.54+1575.16+0-2645.95)

        $jun = $meses['2025-06'];
        $this->assertSame('inconclusivo', $jun['status']); // fatura 0
    }

    public function test_lista_usinas_saldo_e_inconclusivos(): void
    {
        $usiId = $this->seedUsina('UC888', 'Cliente 888');
        $this->seedBaseline($usiId, '2026-01-01', antes: 1000, pago: 800);
        $this->seedPdf($usiId, '2026-01-01', fixo: 0, injetado: 0, creditado: 0, cuo: 0, valorFinal: 1000, fatura: 50); // conclusivo, dif = 800-1000 = -200
        $this->seedBaseline($usiId, '2025-06-01', antes: null, pago: 500);
        $this->seedPdf($usiId, '2025-06-01', fixo: 0, injetado: 0, creditado: 0, cuo: 0, valorFinal: 500, fatura: 0); // inconclusivo

        $resp = $this->getJson('/api/auditoria/usinas');
        $resp->assertOk();
        $u = collect($resp->json('usinas'))->firstWhere('usi_id', $usiId);
        $this->assertEqualsWithDelta(-200.0, $u['saldo'], 0.01);
        $this->assertSame(1, $u['inconclusivos']);
        $this->assertSame(1, $u['meses_divergentes']);
    }

    // ---- helpers ----
    private function seedUsina(string $uc, string $nome): int
    {
        $now = now();
        $end = DB::table('endereco')->insertGetId(['created_at'=>$now,'updated_at'=>$now], 'end_id');
        $cli = DB::table('cliente')->insertGetId(['nome'=>$nome,'cpf_cnpj'=>'0','end_id'=>$end,'created_at'=>$now,'updated_at'=>$now], 'cli_id');
        $dger = DB::table('dados_geracao')->insertGetId(array_merge(array_fill_keys(['janeiro','fevereiro','marco','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'],0), ['media'=>0,'menor_geracao'=>0,'created_at'=>$now,'updated_at'=>$now]), 'dger_id');
        $com = DB::table('comercializacao')->insertGetId(['valor_kwh'=>0.5,'valor_fixo'=>0,'cia_energia'=>'T','valor_final_media'=>0,'previsao_conexao'=>$now->toDateString(),'created_at'=>$now,'updated_at'=>$now], 'com_id');
        $ven = DB::table('vendedor')->insertGetId(['nome'=>'V','patente'=>'junior','created_at'=>$now,'updated_at'=>$now], 'ven_id');
        return (int) DB::table('usina')->insertGetId(['cli_id'=>$cli,'dger_id'=>$dger,'com_id'=>$com,'ven_id'=>$ven,'uc'=>$uc,'created_at'=>$now,'updated_at'=>$now], 'usi_id');
    }

    private function seedBaseline(int $usiId, string $comp, ?float $antes, ?float $pago): void
    {
        DB::table('auditoria_baseline')->insert([
            'usi_id'=>$usiId,'competencia'=>$comp,'valor_sistema_antes'=>$antes,'valor_pago'=>$pago,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function seedPdf(int $usiId, string $comp, float $fixo, float $injetado, float $creditado, float $cuo, float $valorFinal, float $fatura): void
    {
        DB::table('geracao_faturamento_pdf')->insert([
            'usi_id'=>$usiId,'competencia'=>$comp,'geracao_kwh'=>0,
            'valor_fixo'=>$fixo,'injetado'=>$injetado,'creditado'=>$creditado,'cuo'=>$cuo,
            'valor_final'=>$valorFinal,'fatura_energia'=>$fatura,'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
```

- [ ] **Step 2: Rodar e confirmar falha**

Run (de `api-laravel/`): `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter AuditoriaApiTest`
Expected: FAIL — rota/serviço inexistentes (404 / class not found).

- [ ] **Step 3: Criar o `AuditoriaService`**

Criar `api-laravel/app/Services/AuditoriaService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas read-only da auditoria: junta o baseline (Antes/Pago) com o valor Atual
 * (geracao_faturamento_pdf.valor_final). NÃO recalcula nada (motor é da Fase 3).
 * Inconclusivo = fatura_energia atual 0/nula OU sem atual.
 */
final class AuditoriaService
{
    private const TOL = 0.01;

    /** @return array{totais: array, usinas: array} */
    public function listaUsinas(): array
    {
        $linhas = $this->linhas();

        $porUsina = [];
        foreach ($linhas as $l) {
            $u = &$porUsina[$l->usi_id];
            if (! isset($u)) {
                $u = ['usi_id' => (int) $l->usi_id, 'uc' => $l->uc, 'cliente' => $l->cliente ?? '-',
                      'saldo' => 0.0, 'meses_divergentes' => 0, 'inconclusivos' => 0];
            }
            if ($this->inconclusivo($l)) {
                $u['inconclusivos']++;
                continue;
            }
            if ($l->pago === null) {
                continue;
            }
            $dif = round((float) $l->pago - (float) $l->atual, 2);
            $u['saldo'] = round($u['saldo'] + $dif, 2);
            if (abs($dif) >= self::TOL) {
                $u['meses_divergentes']++;
            }
        }
        unset($u);
        $usinas = array_values($porUsina);

        $pagoAMais = 0.0; $pagoAMenos = 0.0; $inconc = 0;
        foreach ($usinas as $u) {
            if ($u['saldo'] < 0) { $pagoAMais += -$u['saldo']; }
            elseif ($u['saldo'] > 0) { $pagoAMenos += $u['saldo']; }
            $inconc += $u['inconclusivos'];
        }

        return [
            'totais' => [
                'pago_a_mais' => round($pagoAMais, 2),
                'pago_a_menos' => round($pagoAMenos, 2),
                'saldo' => round($pagoAMenos - $pagoAMais, 2),
                'total_inconclusivos' => $inconc,
            ],
            'usinas' => $usinas,
        ];
    }

    /** @return array{usina: array, resumo: array, meses: array} */
    public function detalheUsina(int $usiId): array
    {
        $linhas = array_values(array_filter($this->linhas(), fn ($l) => (int) $l->usi_id === $usiId));
        usort($linhas, fn ($a, $b) => strcmp((string) $a->competencia, (string) $b->competencia));

        $meses = [];
        $pagoTotal = 0.0; $atualTotal = 0.0;
        $uc = null; $cliente = null;
        foreach ($linhas as $l) {
            $uc ??= $l->uc; $cliente ??= $l->cliente;
            $inconc = $this->inconclusivo($l);
            $dif = (! $inconc && $l->pago !== null) ? round((float) $l->pago - (float) $l->atual, 2) : null;
            if (! $inconc && $l->pago !== null) {
                $pagoTotal += (float) $l->pago; $atualTotal += (float) $l->atual;
            }
            $meses[] = [
                'competencia' => Carbon::parse($l->competencia)->format('Y-m'),
                'antes' => $l->antes !== null ? (float) $l->antes : null,
                'pago' => $l->pago !== null ? (float) $l->pago : null,
                'atual' => $l->atual !== null ? (float) $l->atual : null,
                'fatura_atual' => $l->fatura !== null ? (float) $l->fatura : null,
                'status' => $inconc ? 'inconclusivo' : 'conclusivo',
                'diferenca' => $dif,
                'termos' => $l->atual !== null ? $this->termos($l) : null,
            ];
        }

        return [
            'usina' => ['usi_id' => $usiId, 'uc' => $uc, 'cliente' => $cliente ?? '-'],
            'resumo' => [
                'pago_total' => round($pagoTotal, 2),
                'atual_total' => round($atualTotal, 2),
                'saldo' => round($pagoTotal - $atualTotal, 2),
            ],
            'meses' => $meses,
        ];
    }

    /** União baseline + pdf por (usina, competência). Uma linha por chave. */
    private function linhas(): array
    {
        return DB::select(
            "SELECT u.usi_id, u.uc, cli.nome AS cliente,
                    to_char(COALESCE(ab.competencia, g.competencia), 'YYYY-MM-DD') AS competencia,
                    ab.valor_sistema_antes AS antes,
                    ab.valor_pago AS pago,
                    g.valor_final AS atual,
                    g.fatura_energia AS fatura,
                    g.valor_fixo, g.injetado, g.creditado, g.cuo
             FROM usina u
             LEFT JOIN cliente cli ON cli.cli_id = u.cli_id
             LEFT JOIN auditoria_baseline ab ON ab.usi_id = u.usi_id
             FULL OUTER JOIN geracao_faturamento_pdf g
                  ON g.usi_id = ab.usi_id AND date_trunc('month', g.competencia) = date_trunc('month', ab.competencia)
             WHERE u.usi_id = COALESCE(ab.usi_id, g.usi_id)
             ORDER BY u.usi_id, competencia"
        );
    }

    private function inconclusivo(object $l): bool
    {
        return $l->atual === null || $l->fatura === null || (float) $l->fatura == 0.0;
    }

    /** @return array<string, float> */
    private function termos(object $l): array
    {
        $fixo = (float) $l->valor_fixo; $inj = (float) $l->injetado;
        $cred = (float) $l->creditado; $cuo = (float) $l->cuo; $vf = (float) $l->atual;
        return [
            'fixo' => round($fixo, 2), 'injetado' => round($inj, 2), 'credito' => round($cred, 2),
            'cuo' => round($cuo, 2),
            'credito_expirado' => round($vf - ($fixo + $inj + $cred - $cuo), 2),
            'valor_final' => round($vf, 2),
        ];
    }
}
```

> Nota SQL: `FULL OUTER JOIN` (Postgres) cobre meses só-baseline e só-pdf. SQLite (testes) também suporta `FULL OUTER JOIN` na versão usada pelo Laravel 11/PHP 8.3. Se o SQLite do ambiente de teste não suportar, o reviewer/implementer deve trocar por uma união equivalente (UNION dos dois lados) — verificar no Step 4.

- [ ] **Step 4: Criar o controller + rotas**

Criar `api-laravel/app/Http/Controllers/AuditoriaController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;

class AuditoriaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria)
    {
    }

    public function usinas(): JsonResponse
    {
        return response()->json($this->auditoria->listaUsinas());
    }

    public function usina(int $usiId): JsonResponse
    {
        return response()->json($this->auditoria->detalheUsina($usiId));
    }
}
```

Em `api-laravel/routes/api.php`, no grupo `Route::middleware('auth:api')->group(...)` (junto das outras rotas GET), adicionar:

```php
  Route::get('/auditoria/usinas', [\App\Http\Controllers\AuditoriaController::class, 'usinas']);
  Route::get('/auditoria/usinas/{usiId}', [\App\Http\Controllers\AuditoriaController::class, 'usina']);
```

- [ ] **Step 5: Rodar e confirmar passa**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter AuditoriaApiTest`
Expected: PASS. (Se falhar por `FULL OUTER JOIN` no SQLite, aplicar a alternativa UNION descrita na nota do Step 3 e re-rodar.)

- [ ] **Step 6: Regressão**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter "Auditoria|Faturamento|Reconstruir|CorrigirFatura"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add api-laravel/app/Services/AuditoriaService.php api-laravel/app/Http/Controllers/AuditoriaController.php api-laravel/routes/api.php api-laravel/tests/Feature/AuditoriaApiTest.php
git commit -m "feat(auditoria): endpoints lista + detalhe (antes/pago/atual, inconclusivo, termos)"
```

---

## Task 2 (Track Frontend): util puro de auditoria + testes

**Files:**
- Create: `front/src/utils/auditoria.js`
- Test: `front/tests/auditoria.test.js`

**Interfaces:**
- Produces:
  - `rotuloDiferenca(pago: number|null, atual: number|null): {tipo:'a_mais'|'a_menos'|'igual'|'inconclusivo', valor:number}` — `inconclusivo` se atual/pago null.
  - `linhaConta(termos: object): string` — monta `Fixo R$… + Injetado R$… + Crédito R$… − CUO R$… + Crédito expirado R$… = R$…` (o termo "Crédito expirado" só entra se `credito_expirado > 0`), usando `formatReais`.

- [ ] **Step 1: Escrever o teste que falha**

Criar `front/tests/auditoria.test.js`:

```js
import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { rotuloDiferenca, linhaConta } from '../src/utils/auditoria.js'

describe('rotuloDiferenca (pago × atual)', () => {
  test('pago < atual => a_menos', () => {
    const r = rotuloDiferenca(1058.75, 2446.29)
    assert.equal(r.tipo, 'a_menos'); assert.ok(Math.abs(r.valor - 1387.54) < 0.01)
  })
  test('pago > atual => a_mais', () => {
    assert.equal(rotuloDiferenca(500, 400).tipo, 'a_mais')
  })
  test('iguais => igual', () => { assert.equal(rotuloDiferenca(100, 100).tipo, 'igual') })
  test('atual null => inconclusivo', () => { assert.equal(rotuloDiferenca(100, null).tipo, 'inconclusivo') })
})

describe('linhaConta', () => {
  test('com expiração inclui o termo', () => {
    const s = linhaConta({ fixo: 2129.54, injetado: 1575.16, credito: 0, cuo: 2645.95, credito_expirado: 1387.54, valor_final: 2446.29 })
    assert.ok(s.includes('Crédito expirado'))
    assert.ok(s.includes('= R$'))
  })
  test('sem expiração não inclui o termo', () => {
    const s = linhaConta({ fixo: 100, injetado: 50, credito: 0, cuo: 20, credito_expirado: 0, valor_final: 130 })
    assert.ok(!s.includes('Crédito expirado'))
  })
})
```

- [ ] **Step 2: Rodar e confirmar falha**

Run (de `front/`): `node --test tests/auditoria.test.js`
Expected: FAIL — módulo não existe.

- [ ] **Step 3: Implementar o util**

Criar `front/src/utils/auditoria.js`:

```js
import { formatReais } from './formatters'

/**
 * Classifica a diferença entre o efetivamente pago e o atual (correto).
 * @param {number|null} pago @param {number|null} atual
 * @returns {{tipo:'a_mais'|'a_menos'|'igual'|'inconclusivo', valor:number}}
 */
export function rotuloDiferenca(pago, atual) {
  if (pago === null || pago === undefined || atual === null || atual === undefined) {
    return { tipo: 'inconclusivo', valor: 0 }
  }
  const dif = Number((pago - atual).toFixed(2))
  if (Math.abs(dif) < 0.01) return { tipo: 'igual', valor: 0 }
  return dif < 0 ? { tipo: 'a_menos', valor: -dif } : { tipo: 'a_mais', valor: dif }
}

/**
 * Linha da conta do "Atual" nos termos do app:
 * Fixo + Injetado + Crédito − CUO [+ Crédito expirado] = Valor final.
 * @param {{fixo:number,injetado:number,credito:number,cuo:number,credito_expirado:number,valor_final:number}} t
 * @returns {string}
 */
export function linhaConta(t) {
  if (!t) return ''
  const partes = [
    `Fixo ${formatReais(t.fixo)}`,
    `+ Injetado ${formatReais(t.injetado)}`,
    `+ Crédito ${formatReais(t.credito)}`,
    `− CUO ${formatReais(t.cuo)}`,
  ]
  if (Number(t.credito_expirado) > 0) {
    partes.push(`+ Crédito expirado ${formatReais(t.credito_expirado)}`)
  }
  return `${partes.join(' ')} = ${formatReais(t.valor_final)}`
}
```

- [ ] **Step 4: Rodar e confirmar passa**

Run: `node --test tests/auditoria.test.js`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add front/src/utils/auditoria.js front/tests/auditoria.test.js
git commit -m "feat(auditoria/front): util puro (rótulo da diferença + linha da conta)"
```

---

## Task 3 (Track Frontend): service + página + modal + rota/menu

**Files:**
- Create: `front/src/services/auditoriaApi.js`
- Create: `front/src/components/Auditoria.vue`
- Create: `front/src/components/faturamento/AuditoriaUsinaModal.vue`
- Modify: `front/src/router/index.js`
- Modify: `front/src/components/TheNavbar.vue`

**Interfaces:**
- Consumes: `rotuloDiferenca`, `linhaConta` (Task 2); endpoints (Task 1, mesmo contrato).

- [ ] **Step 1: Service `auditoriaApi.js`**

Criar `front/src/services/auditoriaApi.js` (segue o padrão de `faturamentoApi.js` — baseURL + authHeaders):

```js
import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL

function authHeaders() {
  return { Authorization: `Bearer ${localStorage.getItem('token')}` }
}

/** GET /auditoria/usinas — lista leve + totais. */
export async function obterAuditoriaUsinas() {
  const { data } = await axios.get(`${baseURL}/auditoria/usinas`, { headers: authHeaders() })
  return data
}

/** GET /auditoria/usinas/{id} — detalhe mês a mês (lazy, ao abrir o modal). */
export async function obterAuditoriaUsina(usiId) {
  const { data } = await axios.get(`${baseURL}/auditoria/usinas/${usiId}`, { headers: authHeaders() })
  return data
}
```

- [ ] **Step 2: Modal `AuditoriaUsinaModal.vue`**

Criar `front/src/components/faturamento/AuditoriaUsinaModal.vue`. Pop-up que, ao abrir (prop `usiId`), faz `obterAuditoriaUsina` (lazy), mostra resumo (`StatValue`) + tabela (`DataTable`) com Antes/Efetivamente pago/Atual/Diferença, "ver conta" por mês (expande `linhaConta`), e badge de inconclusivo. Seguir o padrão visual de `ApurarMesModal.vue` (overlay + card + Transition) e usar `formatReais`. Script (completo):

```vue
<script setup>
import { ref, watch } from 'vue'
import { obterAuditoriaUsina } from '../../services/auditoriaApi'
import { formatReais } from '../../utils/formatters'
import { rotuloDiferenca, linhaConta } from '../../utils/auditoria'
import StatValue from '../base/StatValue.vue'
import DataTable from '../base/DataTable.vue'
import BaseBadge from '../base/BaseBadge.vue'
import BaseButton from '../base/BaseButton.vue'

const props = defineProps({
  aberto: { type: Boolean, default: false },
  usiId: { type: Number, default: null },
})
const emit = defineEmits(['fechar'])

const carregando = ref(false)
const erro = ref('')
const dados = ref(null)
const contaAberta = ref(new Set())

const colunas = [
  { key: 'competencia', label: 'Mês' },
  { key: 'antes', label: 'Antes', numeric: true },
  { key: 'pago', label: 'Efetivamente pago', numeric: true },
  { key: 'atual', label: 'Atual', numeric: true },
  { key: 'diferenca', label: 'Diferença', numeric: true },
]

function rotuloTexto(m) {
  const r = rotuloDiferenca(m.pago, m.atual)
  if (r.tipo === 'inconclusivo') return 'inconclusivo (sem fatura)'
  if (r.tipo === 'igual') return 'ok'
  return (r.tipo === 'a_menos' ? 'pagamos a menos ' : 'pagamos a mais ') + formatReais(r.valor)
}
function rotuloTipo(m) { return rotuloDiferenca(m.pago, m.atual).tipo }
function conta(m) { return linhaConta(m.termos) }
function toggleConta(comp) {
  const s = new Set(contaAberta.value)
  s.has(comp) ? s.delete(comp) : s.add(comp)
  contaAberta.value = s
}

async function carregar() {
  if (!props.usiId) return
  carregando.value = true; erro.value = ''; dados.value = null; contaAberta.value = new Set()
  try {
    dados.value = await obterAuditoriaUsina(props.usiId)
  } catch (e) {
    erro.value = 'Não foi possível carregar o detalhe da usina.'
  } finally {
    carregando.value = false
  }
}

watch(() => [props.aberto, props.usiId], () => { if (props.aberto) carregar() })
</script>
```

Template: overlay + card; cabeçalho com `dados.usina` (nome/uc); 3 `StatValue` do `resumo` (pago_total/atual_total/saldo); `DataTable :columns="colunas" :rows="dados.meses"` com slots formatando `antes/pago/atual` via `formatReais`, `diferenca` mostrando `rotuloTexto(row)` com classe por `rotuloTipo`, e uma linha de detalhe (`#row-details` quando `contaAberta.has(row.competencia)`) exibindo `conta(row)` + a explicação simples; botão "ver conta" por linha chamando `toggleConta`. Estados `carregando`/`erro`. Fechar via overlay e botão. **Seguir o estilo de `ApurarMesModal.vue`.**

- [ ] **Step 3: Página `Auditoria.vue`**

Criar `front/src/components/Auditoria.vue`: cards de resumo (totais: pago a mais/menos, saldo, inconclusivos) via `StatValue`; campo de busca (filtra `usinas` por uc/cliente, client-side); lista de usinas (`DataTable` ou lista) com colunas usina/cliente, meses_divergentes, inconclusivos, saldo (com cor por sinal); ao clicar numa usina abre `AuditoriaUsinaModal` (passa `usiId`). Carrega `obterAuditoriaUsinas()` no `onMounted`. Script (completo):

```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import { obterAuditoriaUsinas } from '../services/auditoriaApi'
import { formatReais } from '../utils/formatters'
import StatValue from './base/StatValue.vue'
import AuditoriaUsinaModal from './faturamento/AuditoriaUsinaModal.vue'

const carregando = ref(true)
const erro = ref('')
const totais = ref(null)
const usinas = ref([])
const busca = ref('')
const usiSelecionada = ref(null)
const modalAberto = ref(false)

const filtradas = computed(() => {
  const q = busca.value.trim().toLowerCase()
  if (!q) return usinas.value
  return usinas.value.filter((u) =>
    String(u.uc).toLowerCase().includes(q) || String(u.cliente).toLowerCase().includes(q))
})

function abrir(u) { usiSelecionada.value = u.usi_id; modalAberto.value = true }

onMounted(async () => {
  try {
    const d = await obterAuditoriaUsinas()
    totais.value = d.totais; usinas.value = d.usinas
  } catch (e) {
    erro.value = 'Não foi possível carregar a auditoria.'
  } finally {
    carregando.value = false
  }
})
</script>
```

Template: título "Auditoria de pagamentos"; 4 `StatValue` (Pago a mais, Pago a menos, Saldo, Inconclusivos) de `totais`; `<input>` de busca (v-model `busca`); lista de `filtradas` (cada item clicável → `abrir(u)`) mostrando nome/uc, meses_divergentes, inconclusivos e saldo (classe por sinal, texto "a menos R$ X"/"a mais R$ X" usando `formatReais(Math.abs(u.saldo))`); estados carregando/erro; `<AuditoriaUsinaModal :aberto="modalAberto" :usi-id="usiSelecionada" @fechar="modalAberto=false" />`. **Design system do app** (tokens/cores; reusar classes existentes da tela de faturamento onde fizer sentido).

- [ ] **Step 4: Rota + menu**

Em `front/src/router/index.js`: importar `Auditoria` e adicionar a rota (perto da de `/relatorio`):

```js
import Auditoria from '@/components/Auditoria.vue'
// ...
  {
    path: '/auditoria',
    name: 'auditoria',
    component: Auditoria,
    meta: { requiresAuth: true, titulo: 'Auditoria' },
  },
```

Em `front/src/components/TheNavbar.vue`, junto do link de Relatórios (linha ~77), adicionar:

```html
            <router-link class="app-nav-link" to="/auditoria" @click="closeAll">Auditoria</router-link>
```

- [ ] **Step 5: Build + testes**

Run (de `front/`): `npm run build` → Expected: build sem erro.
Run: `node --test tests/*.test.js` → Expected: PASS (inclui Task 2 + os existentes).

- [ ] **Step 6: Commit**

```bash
git add front/src/services/auditoriaApi.js front/src/components/Auditoria.vue front/src/components/faturamento/AuditoriaUsinaModal.vue front/src/router/index.js front/src/components/TheNavbar.vue
git commit -m "feat(auditoria/front): página + modal lazy (lista, busca, detalhe nos termos do app)"
```

---

## Task 4: Verificação manual no app

**Files:** nenhum.

- [ ] **Step 1:** Subir backend (apontando p/ `energia_assinatura` local, que já tem baseline + geracao_faturamento_pdf) + `npm run dev`. Logar.
- [ ] **Step 2:** Abrir `/auditoria`. Conferir: cards de totais; busca filtra; lista mostra saldo/meses/inconclusivos. Clicar no **Romeu** → modal abre (lazy), mostra Jan/26 com Antes 1.059,21 · Efetiv. pago 1.058,75 · Atual 2.446,29 · "pagamos a menos R$ 1.387,54"; "ver conta" mostra `Fixo … + Injetado … + Crédito … − CUO … + Crédito expirado R$ 1.387,54 = R$ 2.446,29`; meses 2025 sem fatura aparecem "inconclusivo".
- [ ] **Step 3:** Se algo divergir, voltar ao código antes de concluir.

> Verificação manual (o app Vue não tem teste de componente). 

---

## Self-Review

**Spec coverage:**
- Endpoints lista+detalhe (contrato) → Task 1. ✓
- Atual do banco / inconclusivo=fatura 0 / termos derivados → Task 1 (service). ✓
- Nomes Antes/Efetivamente pago/Atual + diferença pago−atual → Tasks 1 (dados) e 3 (colunas). ✓
- Conta nos termos do app → Task 2 (`linhaConta`) + Task 3 (modal). ✓
- Lista leve + modal lazy → Task 3 (modal carrega no `watch`/abrir). ✓
- Busca, cards, menu/rota → Task 3. ✓
- Design system / formatReais → Tasks 2/3. ✓

**Placeholder scan:** Tasks 2/3 Vue templates descritos em prosa com os bindings exatos (script completo); a parte visual segue mockup aprovado + `ApurarMesModal`/design system — instrução concreta, não placeholder. Task 1 tem nota condicional sobre FULL OUTER JOIN no SQLite (instrução de verificação).

**Type consistency:** contrato de API idêntico entre Task 1 (produz) e Tasks 2/3 (consomem): `usinas[]{usi_id,uc,cliente,saldo,meses_divergentes,inconclusivos}`, `meses[]{competencia,antes,pago,atual,fatura_atual,status,diferenca,termos}`. `rotuloDiferenca`/`linhaConta` usados consistentemente. `termos.credito_expirado` derivado no backend e consumido no `linhaConta`.

---

## Execution Handoff

Plano salvo. Tracks: Backend (Task 1) ∥ Frontend (Task 2 → Task 3); Task 4 manual ao fim.
