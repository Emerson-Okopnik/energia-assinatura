# Consumo e Fatura Obrigatórios no Lançamento — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Impedir, no front, lançar/refaturar um mês sem fatura de energia > 0 e sem consumo informado (0 válido) — inclusive ao reabrir um mês já lançado.

**Architecture:** Extrair a regra de validação para um módulo puro e testável (`src/utils/validacaoLancamento.js`) — fonte única da regra (SOLID/DRY). O `ApurarMesModal.vue` passa a consumir esse módulo em seus computeds (`consumoValido`, `faturaValida`, `erroConsumo`, `erroFatura`, `formValido`). O gate de submit já existente (`aoConfirmar` faz `tocado=true; if (!formValido) return`) não muda — passa a respeitar a nova regra automaticamente.

**Tech Stack:** Vue 3 (`<script setup>`), Vite, testes com `node --test` (node:test + node:assert).

## Global Constraints

- **Fatura de energia:** obrigatória e **> 0** (bloqueia `null` e `≤ 0`).
- **Consumo da usina:** obrigatório informar (`!== null`), mas **`0` é válido**.
- Regra vale para **lançamento E reabertura/refaturamento** — sem exceção.
- **Só front** — nenhuma alteração de backend.
- A regra de cada campo aparece **uma única vez** (no módulo puro); mensagens e `formValido` derivam dela (DRY).
- Não tocar em geração (`mesGeracao`) nem `adicional_cuo`.
- Mensagem de fatura: `Informe a fatura de energia (maior que zero).`
- Mensagem de consumo: `Informe o consumo da usina no mês.`
- Rodar testes (de `front/`): `node --test tests/`

---

## File Structure

- `front/src/utils/validacaoLancamento.js` — **novo**. Funções puras: `consumoValido(v)`, `faturaValida(v)`. Fonte única da regra de validade de cada campo.
- `front/tests/validacaoLancamento.test.js` — **novo**. Testes das funções puras.
- `front/src/components/faturamento/ApurarMesModal.vue` — **modificar**. Importa o módulo; computeds `consumoValido`/`faturaValida`/`erroConsumo`/`erroFatura`/`formValido` derivam dele.

---

## Task 1: Módulo puro de validação + testes

**Files:**
- Create: `front/src/utils/validacaoLancamento.js`
- Test: `front/tests/validacaoLancamento.test.js`

**Interfaces:**
- Produces: `consumoValido(valor: number|null): boolean` — `true` se `valor !== null && Number.isFinite(valor)` (0 é válido; null/NaN não). `faturaValida(valor: number|null): boolean` — `true` se `valor !== null && Number.isFinite(valor) && valor > 0`.

- [ ] **Step 1: Escrever os testes que falham**

Criar `front/tests/validacaoLancamento.test.js`:

```js
import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { consumoValido, faturaValida } from '../src/utils/validacaoLancamento.js'

describe('consumoValido (obrigatório informar; 0 é válido)', () => {
  test('0 é válido', () => assert.equal(consumoValido(0), true))
  test('positivo é válido', () => assert.equal(consumoValido(134), true))
  test('null é inválido', () => assert.equal(consumoValido(null), false))
  test('NaN é inválido', () => assert.equal(consumoValido(NaN), false))
})

describe('faturaValida (obrigatória e > 0)', () => {
  test('positivo é válido', () => assert.equal(faturaValida(98.77), true))
  test('0 é inválido', () => assert.equal(faturaValida(0), false))
  test('negativo é inválido', () => assert.equal(faturaValida(-5), false))
  test('null é inválido', () => assert.equal(faturaValida(null), false))
  test('NaN é inválido', () => assert.equal(faturaValida(NaN), false))
})
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run (de `front/`): `node --test tests/validacaoLancamento.test.js`
Expected: FAIL — `Cannot find module '../src/utils/validacaoLancamento.js'`.

- [ ] **Step 3: Implementar o módulo**

Criar `front/src/utils/validacaoLancamento.js`:

```js
/**
 * Regras de validade dos inputs de lançamento de faturamento (fonte única).
 *
 * - Consumo da usina: obrigatório INFORMAR (não pode null/NaN); 0 é um valor válido.
 * - Fatura de energia: obrigatória e MAIOR QUE ZERO (sem fatura não há CUO correto).
 *
 * Funções puras — testáveis isoladamente e consumidas pelos computeds do modal.
 */

/** @param {number|null} valor @returns {boolean} */
export function consumoValido(valor) {
  return valor !== null && Number.isFinite(valor)
}

/** @param {number|null} valor @returns {boolean} */
export function faturaValida(valor) {
  return valor !== null && Number.isFinite(valor) && valor > 0
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `node --test tests/validacaoLancamento.test.js`
Expected: PASS (todos os casos).

- [ ] **Step 5: Commit**

```bash
git add front/src/utils/validacaoLancamento.js front/tests/validacaoLancamento.test.js
git commit -m "feat(front): módulo puro de validação de lançamento (fatura>0, consumo informado)"
```

---

## Task 2: ApurarMesModal consome o módulo

**Files:**
- Modify: `front/src/components/faturamento/ApurarMesModal.vue`

**Interfaces:**
- Consumes: `consumoValido`, `faturaValida` de `src/utils/validacaoLancamento.js` (Task 1).

- [ ] **Step 1: Importar o módulo**

Em `front/src/components/faturamento/ApurarMesModal.vue`, no bloco de imports (junto dos outros `import` no topo do `<script setup>`), adicionar:

```js
import { consumoValido, faturaValida } from '@/utils/validacaoLancamento.js'
```

> Observação: confirmar o alias usado no projeto. Se os imports vizinhos usarem caminho relativo (ex.: `../../utils/...`) em vez de `@/`, seguir o mesmo padrão dos imports existentes no arquivo.

- [ ] **Step 2: Substituir os computeds de validação**

Localizar o bloco atual (≈ linhas 66-79):

```js
// Validação inline por blur (F7).
const erroConsumo = computed(() =>
  tocado.consumo && consumoUsinaMes.value === null
    ? 'Informe o consumo da usina no mês.'
    : ''
)
const erroFatura = computed(() =>
  tocado.fatura && faturaEnergia.value === null
    ? 'Informe a fatura de energia do mês.'
    : ''
)
const formValido = computed(
  () => consumoUsinaMes.value !== null && faturaEnergia.value !== null
)
```

Substituir por (predicados como fonte única; mensagens e formValido derivam):

```js
// Validade de cada campo — fonte única em utils/validacaoLancamento (SOLID/DRY).
const consumoEhValido = computed(() => consumoValido(consumoUsinaMes.value))
const faturaEhValida = computed(() => faturaValida(faturaEnergia.value))

// Validação inline por blur (F7): mensagem só após o campo ser tocado.
const erroConsumo = computed(() =>
  tocado.consumo && !consumoEhValido.value
    ? 'Informe o consumo da usina no mês.'
    : ''
)
const erroFatura = computed(() =>
  tocado.fatura && !faturaEhValida.value
    ? 'Informe a fatura de energia (maior que zero).'
    : ''
)
const formValido = computed(() => consumoEhValido.value && faturaEhValida.value)
```

- [ ] **Step 3: Build do front (garante import resolvido e sem erro de sintaxe)**

Run (de `front/`): `npm run build`
Expected: build conclui sem erro (o import de `validacaoLancamento` resolve; nenhum erro de Vite/rollup).

- [ ] **Step 4: Suíte de testes do front (regressão)**

Run (de `front/`): `node --test tests/`
Expected: PASS (todos, incluindo `validacaoLancamento.test.js` da Task 1).

- [ ] **Step 5: Commit**

```bash
git add front/src/components/faturamento/ApurarMesModal.vue
git commit -m "feat(front): consumo/fatura obrigatórios no lançamento (fatura>0) via módulo único"
```

---

## Task 3: Verificação manual no app (4 casos)

**Files:** nenhum (validação de comportamento).

Esta task confirma o comportamento end-to-end na tela — o que o teste de unidade não cobre (a ligação computed → UI → gate de submit). Sem código novo.

- [ ] **Step 1: Subir o app e abrir o modal de apuração**

Com o app rodando (backend + `npm run dev`), abrir Faturar → uma usina → um mês → "Apurar".

- [ ] **Step 2: Validar os 4 casos**

Confirmar cada um:
1. **Fatura vazia** → ao tentar faturar, mensagem `Informe a fatura de energia (maior que zero).` e não prossegue.
2. **Fatura 0** → mesma mensagem; não prossegue.
3. **Consumo vazio** → mensagem `Informe o consumo da usina no mês.`; não prossegue.
4. **Consumo 0 + fatura > 0** → válido; faturamento prossegue normalmente.

- [ ] **Step 3: Validar a reabertura**

Abrir um mês já faturado com fatura 0 (resíduo do backfill). Confirmar que, ao tentar salvar, exige fatura > 0 (mesma regra). Geração e adicional_cuo permanecem inalterados.

> Esta verificação é manual (o app Vue não tem teste de componente automatizado no projeto). Se algum caso falhar, retornar ao código antes de concluir.

---

## Self-Review

**Spec coverage:**
- Fatura obrigatória > 0 → `faturaValida` (Task 1) + `faturaEhValida`/`erroFatura`/`formValido` (Task 2). ✓
- Consumo obrigatório, 0 válido → `consumoValido` (Task 1) + Task 2. ✓
- Vale na reabertura → Task 3 Step 3 (a regra é a mesma; `reidratar` não reseta a validação, só `tocado`). ✓
- Só front, sem backend → nenhuma task toca backend. ✓
- Fonte única / DRY → regra só no módulo puro (Task 1); componente deriva (Task 2). ✓
- Mensagens exatas → copiadas verbatim nas Tasks 2 e 3. ✓
- Não tocar geração/adicional_cuo → nenhuma task os altera. ✓

**Placeholder scan:** Task 2 Step 1 tem uma observação sobre o alias de import (`@/` vs relativo) — é uma instrução concreta (seguir o padrão dos imports vizinhos do arquivo), não um placeholder. Demais steps têm código completo.

**Type consistency:** `consumoValido`/`faturaValida` (nomes do módulo) usados consistentemente entre Task 1 e Task 2. Os computeds no `.vue` usam nomes distintos (`consumoEhValido`/`faturaEhValida`) para não colidir com as funções importadas — proposital e consistente dentro da Task 2.

---

## Execution Handoff

Plano salvo. Próximo: escolher modo de execução.
