# Consumo e fatura obrigatórios no lançamento (front) — Design

> Design · 2026-06-17 · branch `feat/consumo-fatura-obrigatorios`
> Impede lançar/refaturar um mês sem fatura válida e sem consumo informado.

## Problema

Hoje o `ApurarMesModal` valida os inputs com `=== null`, então **`0` passa**. Isso
permite faturar com **fatura de energia 0**, que subdimensiona o CUO e infla o Valor
Final (a mesma família do bug que originou a correção de produção). É preciso bloquear
isso no front, inclusive ao **reabrir** um mês já lançado.

## Regras (confirmadas)

- **Fatura de energia:** obrigatória e **> 0**. Bloqueia se vazia (`null`) ou `≤ 0`.
- **Consumo da usina:** obrigatório **informar** (não pode `null`/vazio), mas **`0` é
  válido** (a usina pode não ter consumo no mês).
- **Vale para lançamento E reabertura/refaturamento** — uma única regra, sem exceção
  para meses já lançados. Ao reabrir um mês salvo com fatura 0 (resíduo do backfill), o
  operador é obrigado a informar uma fatura > 0 antes de re-salvar.

## Escopo

Apenas `front/src/components/faturamento/ApurarMesModal.vue`. **Sem backend** (decisão:
só o front por enquanto). O componente já tem o esqueleto de validação inline
(`erroConsumo`, `erroFatura`), o gate de submit (`aoConfirmar` faz
`tocado=true; if (!formValido) return`) e o estado `tocado`. A mudança é só ajustar as
**condições** — não há nova arquitetura.

## Mudanças (mínimas, DRY)

**SOLID/DRY — uma fonte única por campo.** Hoje a regra de cada campo está duplicada
entre `formValido` e `erroX`. Para não repetir "fatura > 0" em dois lugares, a regra de
cada campo vira **um predicado próprio** (SRP: cada predicado decide a validade de um
campo), e todo o resto **deriva** dele:

1. **`consumoValido`** (computed) — `consumoUsinaMes.value !== null`. (`0` é válido.)
2. **`faturaValida`** (computed) — `faturaEnergia.value !== null && faturaEnergia.value > 0`.
3. **`erroConsumo`** — `tocado.consumo && !consumoValido.value` → `Informe o consumo da usina no mês.`
4. **`erroFatura`** — `tocado.fatura && !faturaValida.value` → `Informe a fatura de energia (maior que zero).`
5. **`formValido`** — `consumoValido.value && faturaValida.value`.

A regra de cada campo aparece **uma só vez** (no predicado); mensagens e `formValido`
consomem o predicado. O submit (`aoConfirmar`/`executarFaturamento`) já depende de
`formValido` e marca `tocado.* = true` antes de checar — logo o bloqueio passa a
respeitar a nova regra automaticamente, sem tocar no fluxo de submit.

## Comportamento esperado

- Campo de fatura vazio ou 0 → mensagem de erro, botão de faturar não prossegue.
- Campo de consumo vazio → mensagem de erro. Consumo 0 digitado → aceito.
- Reabrir mês com fatura 0 salva → ao tentar salvar, exige fatura > 0.
- Geração (`mesGeracao`) e adicional_cuo: **inalterados** (fora do escopo).

## Testes

O front usa `node --test` (sem framework de componente Vue). A lógica de validação é
pura (computeds sobre refs). Plano de teste: extrair/validar a condição de `formValido`
e `erroFatura` de forma testável, ou — se o componente não for facilmente testável em
unidade — validar manualmente rodando o app (cobertura por inspeção dos 4 casos:
fatura vazia, fatura 0, consumo vazio, consumo 0 + fatura > 0). A decisão de como testar
fica para o plano.

## Não-objetivos

- Backend / FormRequest (decisão: só front agora).
- Validar geração ou adicional_cuo.
- Mexer no fluxo de cálculo/preview.
