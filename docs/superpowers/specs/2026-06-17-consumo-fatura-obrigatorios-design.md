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

Fonte única de verdade da validação: os computeds `formValido` / `erroFatura` /
`erroConsumo`. O submit (`aoConfirmar`/`executarFaturamento`) já depende de `formValido`
— logo o bloqueio passa a respeitar a nova regra automaticamente.

1. **`erroFatura`** — erro quando `faturaEnergia === null || faturaEnergia <= 0`.
   Mensagem: `Informe a fatura de energia (maior que zero).`
2. **`erroConsumo`** — mantém: erro só quando `consumoUsinaMes === null`. (`0` é válido.)
3. **`formValido`** — passa a exigir:
   `consumoUsinaMes !== null && faturaEnergia !== null && faturaEnergia > 0`.

Nada mais muda: o `aoConfirmar()` já marca `tocado.consumo/fatura = true` e retorna se
`!formValido`, exibindo as mensagens; o `executarFaturamento()` reconfere `formValido`.

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
