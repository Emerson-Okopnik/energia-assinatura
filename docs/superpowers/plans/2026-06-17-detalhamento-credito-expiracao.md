# Detalhamento: crédito expirado na fórmula + resgate vs expiração — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** No detalhamento do lançamento, exibir a receita de crédito expirado (card + termo na fórmula, só quando > 0) e deixar explícito, na auditoria, o que é crédito resgatado da reserva vs pago por expiração — com valor em R$ por linha e tooltip do kWh.

**Architecture:** Só front, 2 componentes. Backend já provê tudo (`termos.receita_expiracao_reais`, `consumo_fifo`/`expiracao` com `{origem, kwh}`, e a tarifa em `parametros.tarifa`). Lógica de montagem (partes da fórmula, R$=kwh×tarifa) extraída para função pura testável onde fizer sentido (DRY).

**Tech Stack:** Vue 3 (`<script setup>`), Vite, testes `node --test`.

## Global Constraints

- Card "Crédito expirado" e o termo na fórmula **só aparecem quando `receita_expiracao_reais > 0`**. Sem expiração, a tela fica idêntica a hoje.
- Termo na fórmula: `+ Crédito expirado E` (antes do `= Total`).
- Auditoria — rótulos exatos:
  - "Crédito resgatado da reserva (compensa o déficit do mês)"
  - "Crédito expirado e pago (180 dias sem uso)"
- Auditoria — coluna passa a exibir **R$** (`kwh × tarifa`) com **tooltip (`title`) do kWh** (`formatKwh`). Se `tarifa` for null, exibir o kWh direto (fallback, sem quebrar).
- Não mexer no backend, no cálculo, nem em outros termos (Fixo/Injetado/CUO/geração).
- Rodar testes (de `front/`): `node --test tests/*.test.js`. Build: `npm run build`.

---

## File Structure

- `front/src/utils/detalhamentoFatura.js` — **novo**. Funções puras: `partesFormula(termos, fmt)` (monta o array de partes da fórmula, incluindo expiração condicional) e `valorEmReais(kwh, tarifa)` (kwh×tarifa ou null). Fonte única dessas lógicas.
- `front/tests/detalhamentoFatura.test.js` — **novo**. Testes das funções puras.
- `front/src/components/faturamento/PreviewPanel.vue` — **modificar**. Card "Crédito expirado" condicional; fórmula via `partesFormula`.
- `front/src/components/faturamento/AuditoriaAccordion.vue` — **modificar**. Rótulos novos; coluna R$ + tooltip kWh via `valorEmReais`.

---

## Task 1: Funções puras de detalhamento + testes

**Files:**
- Create: `front/src/utils/detalhamentoFatura.js`
- Test: `front/tests/detalhamentoFatura.test.js`

**Interfaces:**
- Produces:
  - `valorEmReais(kwh: number, tarifa: number|null): number|null` — `kwh * tarifa`, ou `null` se `tarifa` não for número finito.
  - `partesFormula(termos: object): Array<{label: string, valor: number}>` — devolve as partes na ordem: Fixo, Injetado, Crédito, CUO (negativo), e — **somente se `receita_expiracao_reais > 0`** — "Crédito expirado". Cada parte é `{label, valor}` (valor cru; a formatação é responsabilidade da view). CUO vem com `valor` negativo (é subtraído). Não inclui o total.

- [ ] **Step 1: Escrever os testes que falham**

Criar `front/tests/detalhamentoFatura.test.js`:

```js
import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { valorEmReais, partesFormula } from '../src/utils/detalhamentoFatura.js'

describe('valorEmReais', () => {
  test('kwh × tarifa', () => assert.equal(valorEmReais(124, 0.51), 63.24))
  test('tarifa null → null', () => assert.equal(valorEmReais(124, null), null))
  test('tarifa NaN → null', () => assert.equal(valorEmReais(124, NaN), null))
  test('0 kwh → 0', () => assert.equal(valorEmReais(0, 0.51), 0))
})

describe('partesFormula', () => {
  const base = {
    valor_fixo_reais: 3894.36,
    valor_variavel_reais: 2017.56,
    credito_reais: 672.69,
    cuo_reais: 1020.01,
    receita_expiracao_reais: 0,
  }

  test('sem expiração: 4 partes (fixo, injetado, crédito, -cuo)', () => {
    const p = partesFormula(base)
    assert.deepEqual(
      p.map((x) => x.label),
      ['Fixo', 'Injetado', 'Crédito', 'CUO']
    )
    assert.equal(p[3].valor, -1020.01) // CUO negativo
  })

  test('com expiração > 0: inclui "Crédito expirado" no fim', () => {
    const p = partesFormula({ ...base, receita_expiracao_reais: 63.24 })
    assert.equal(p.length, 5)
    assert.equal(p[4].label, 'Crédito expirado')
    assert.equal(p[4].valor, 63.24)
  })

  test('expiração 0 não entra', () => {
    const p = partesFormula(base)
    assert.equal(p.some((x) => x.label === 'Crédito expirado'), false)
  })
})
```

- [ ] **Step 2: Rodar e confirmar falha**

Run (de `front/`): `node --test tests/detalhamentoFatura.test.js`
Expected: FAIL — `Cannot find module '../src/utils/detalhamentoFatura.js'`.

- [ ] **Step 3: Implementar o módulo**

Criar `front/src/utils/detalhamentoFatura.js`:

```js
/**
 * Lógica pura do detalhamento do lançamento (fonte única — consumida pela UI).
 */

/**
 * Converte energia em dinheiro pela tarifa. Null se a tarifa não for um número.
 * @param {number} kwh @param {number|null} tarifa @returns {number|null}
 */
export function valorEmReais(kwh, tarifa) {
  if (!Number.isFinite(tarifa)) return null
  return Number((kwh * tarifa).toFixed(2))
}

/**
 * Partes da fórmula do valor final (sem o total). CUO entra negativo (é subtraído).
 * "Crédito expirado" (receita de expiração, PAGA TUDO) só entra quando > 0.
 * @param {object} termos @returns {Array<{label: string, valor: number}>}
 */
export function partesFormula(termos) {
  if (!termos) return []
  const partes = [
    { label: 'Fixo', valor: Number(termos.valor_fixo_reais) || 0 },
    { label: 'Injetado', valor: Number(termos.valor_variavel_reais) || 0 },
    { label: 'Crédito', valor: Number(termos.credito_reais) || 0 },
    { label: 'CUO', valor: -(Number(termos.cuo_reais) || 0) },
  ]
  const expiracao = Number(termos.receita_expiracao_reais) || 0
  if (expiracao > 0) {
    partes.push({ label: 'Crédito expirado', valor: expiracao })
  }
  return partes
}
```

- [ ] **Step 4: Rodar e confirmar passa**

Run: `node --test tests/detalhamentoFatura.test.js`
Expected: PASS (todos).

- [ ] **Step 5: Commit**

```bash
git add front/src/utils/detalhamentoFatura.js front/tests/detalhamentoFatura.test.js
git commit -m "feat(front): funções puras do detalhamento (partesFormula + valorEmReais)"
```

---

## Task 2: PreviewPanel — card e fórmula com crédito expirado

**Files:**
- Modify: `front/src/components/faturamento/PreviewPanel.vue`

**Interfaces:**
- Consumes: `partesFormula` de `../../utils/detalhamentoFatura.js` (Task 1).

- [ ] **Step 1: Importar a função pura**

Em `front/src/components/faturamento/PreviewPanel.vue`, junto dos imports do topo (após `import { formatReais, formatKwh, formatNumero } from '../../utils/formatters'`):

```js
import { partesFormula } from '../../utils/detalhamentoFatura'
```

- [ ] **Step 2: Reescrever `formulaLinha` usando `partesFormula` + flag de expiração**

Substituir o computed atual:

```js
const formulaLinha = computed(() => {
  if (!termos.value) return ''
  const t = termos.value
  return [
    `Fixo ${formatReais(t.valor_fixo_reais)}`,
    `+ Injetado ${formatReais(t.valor_variavel_reais)}`,
    `+ Crédito ${formatReais(t.credito_reais)}`,
    `− CUO ${formatReais(t.cuo_reais)}`,
    `= ${formatReais(t.valor_final_reais)}`,
  ].join(' ')
})
```

por (monta a partir das partes; o 1º termo sem sinal, os demais com `+`/`−` conforme o valor; total ao fim):

```js
const temExpiracao = computed(
  () => Number(termos.value?.receita_expiracao_reais) > 0
)

const formulaLinha = computed(() => {
  if (!termos.value) return ''
  const partes = partesFormula(termos.value)
  const corpo = partes
    .map((p, i) => {
      const sinal = i === 0 ? '' : p.valor < 0 ? '− ' : '+ '
      return `${sinal}${p.label} ${formatReais(Math.abs(p.valor))}`
    })
    .join(' ')
  return `${corpo} = ${formatReais(termos.value.valor_final_reais)}`
})
```

- [ ] **Step 3: Adicionar o card "Crédito expirado" (condicional) no grid de termos**

No `<template>`, no bloco `<div class="preview-panel__grid">` do conteúdo (o que tem Fixo/Injetado/Crédito/CUO), adicionar após o card de CUO:

```html
        <StatValue label="CUO" tone="danger" :value="formatReais(termos.cuo_reais)" />
        <StatValue
          v-if="temExpiracao"
          label="Crédito expirado"
          :value="formatReais(termos.receita_expiracao_reais)"
        />
```

> O grid usa CSS de colunas automáticas; o 5º card acomoda sem mudança de layout. Não alterar o grid de loading (continua com 4 skeletons).

- [ ] **Step 4: Build (resolve import + sintaxe)**

Run (de `front/`): `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 5: Suíte de testes (regressão)**

Run (de `front/`): `node --test tests/*.test.js`
Expected: PASS (todos, incluindo Task 1).

- [ ] **Step 6: Commit**

```bash
git add front/src/components/faturamento/PreviewPanel.vue
git commit -m "feat(front): card 'Crédito expirado' + termo na fórmula quando há expiração"
```

---

## Task 3: AuditoriaAccordion — rótulos claros + R$ com tooltip de kWh

**Files:**
- Modify: `front/src/components/faturamento/AuditoriaAccordion.vue`

**Interfaces:**
- Consumes: `valorEmReais` de `../../utils/detalhamentoFatura.js` (Task 1); `props.parametros.tarifa` (já disponível).

- [ ] **Step 1: Importar a função pura**

Junto dos imports do topo (após `import { formatReais, formatKwh, formatNumero } from '../../utils/formatters'`):

```js
import { valorEmReais } from '../../utils/detalhamentoFatura'
```

- [ ] **Step 2: Trocar a coluna `kwh` por uma coluna em R$ (com kWh no tooltip)**

Trocar `colunasOrigem`:

```js
const colunasOrigem = [
  { key: 'origem', label: 'Mês de origem' },
  { key: 'kwh', label: 'Energia', numeric: true },
]
```

por:

```js
const colunasOrigem = [
  { key: 'origem', label: 'Mês de origem' },
  { key: 'kwh', label: 'Valor (R$)', numeric: true },
]

const tarifa = computed(() => props.parametros?.tarifa ?? null)
```

- [ ] **Step 3: Atualizar os rótulos e o slot da célula nas duas tabelas**

No `<template>`, localizar:

```html
        <h4 class="auditoria__titulo">Crédito resgatado por origem (FIFO)</h4>
        <DataTable :columns="colunasOrigem" :rows="consumoFifo">
          <template #cell-kwh="{ value }">{{ formatKwh(value) }}</template>
          <template #empty>Nenhum crédito resgatado neste mês.</template>
        </DataTable>

        <h4 class="auditoria__titulo">Crédito expirado</h4>
        <DataTable :columns="colunasOrigem" :rows="expiracao">
          <template #cell-kwh="{ value }">{{ formatKwh(value) }}</template>
          <template #empty>Nenhum crédito expirado neste mês.</template>
        </DataTable>
```

Substituir por (rótulos novos; célula mostra R$ com `title`=kWh; fallback p/ kWh se tarifa null):

```html
        <h4 class="auditoria__titulo">Crédito resgatado da reserva (compensa o déficit do mês)</h4>
        <DataTable :columns="colunasOrigem" :rows="consumoFifo">
          <template #cell-kwh="{ value }">
            <span :title="`${formatKwh(value)}`">
              {{ valorEmReais(value, tarifa) === null ? formatKwh(value) : formatReais(valorEmReais(value, tarifa)) }}
            </span>
          </template>
          <template #empty>Nenhum crédito resgatado neste mês.</template>
        </DataTable>

        <h4 class="auditoria__titulo">Crédito expirado e pago (180 dias sem uso)</h4>
        <DataTable :columns="colunasOrigem" :rows="expiracao">
          <template #cell-kwh="{ value }">
            <span :title="`${formatKwh(value)}`">
              {{ valorEmReais(value, tarifa) === null ? formatKwh(value) : formatReais(valorEmReais(value, tarifa)) }}
            </span>
          </template>
          <template #empty>Nenhum crédito expirado neste mês.</template>
        </DataTable>
```

- [ ] **Step 4: Build**

Run (de `front/`): `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 5: Suíte de testes (regressão)**

Run (de `front/`): `node --test tests/*.test.js`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add front/src/components/faturamento/AuditoriaAccordion.vue
git commit -m "feat(front): auditoria — rótulos claros (resgate/expiração) e valor R$ com tooltip kWh"
```

---

## Task 4: Verificação manual no app

**Files:** nenhum.

- [ ] **Step 1: Caso COM expiração** (ex.: Eder Abr/2026)

Abrir o lançamento. Confirmar:
- Card **"Crédito expirado"** aparece com o valor (ex.: R$ 63,24).
- Fórmula mostra `... − CUO ... + Crédito expirado R$ 63,24 = R$ 5.627,84` e a conta fecha.
- Auditoria: título "Crédito resgatado da reserva (compensa o déficit do mês)" e "Crédito expirado e pago (180 dias sem uso)"; coluna em R$; tooltip (hover) mostra o kWh.

- [ ] **Step 2: Caso SEM expiração** (ex.: um mês sem crédito vencido)

Confirmar que **não** aparece card "Crédito expirado" nem o termo na fórmula — tela igual a hoje (4 termos).

> Verificação manual: o app Vue não tem teste de componente no projeto. Se algum caso falhar, voltar ao código antes de concluir.

---

## Self-Review

**Spec coverage:**
- Card + termo de expiração só quando > 0 → Task 1 (`partesFormula` condicional) + Task 2. ✓
- Fórmula fecha com expiração → Task 2 Step 2. ✓
- Rótulos claros resgate/expiração → Task 3 Step 3 (texto verbatim do spec). ✓
- R$ por linha + tooltip kWh + fallback tarifa null → Task 3 Step 3 + `valorEmReais` (Task 1). ✓
- Sem backend / sem mudar cálculo → nenhuma task toca backend ou fórmula do motor. ✓
- DRY / fonte única → lógica em `detalhamentoFatura.js` (Task 1); componentes consomem. ✓

**Placeholder scan:** sem TBD/TODO; todo step de código tem o código completo.

**Type consistency:** `partesFormula(termos)` e `valorEmReais(kwh, tarifa)` usados com a mesma assinatura entre Task 1, 2 e 3. `colunasOrigem` mantém `key: 'kwh'` (o slot `#cell-kwh` depende disso) — só o `label` muda para "Valor (R$)". Consistente.

---

## Execution Handoff

Plano salvo. Próximo: escolher modo de execução.
