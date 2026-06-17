# Detalhamento: crédito expirado na fórmula + crédito resgatado vs expirado explícito — Design

> Design · 2026-06-17 · branch `feat/consumo-fatura-obrigatorios` (mesma frente de UI)
> Torna visível, no detalhamento do lançamento, a receita de crédito expirado (PAGA TUDO)
> e deixa explícito o que é crédito **resgatado da reserva** vs **pago por expiração**.

## Problema

1. **A fórmula não fecha visualmente quando há expiração.** O valor final inclui a
   receita de expiração (PAGA TUDO), mas a linha de fórmula e os cards só mostram os 4
   termos (Fixo+Injetado+Crédito−CUO). Ex. (Eder Abr/2026): cards somam 5.564,60 mas o
   valor final é 5.627,84 — os R$ 63,24 de expiração (124 kWh × 0,51) não aparecem.

2. **"Crédito" é ambíguo.** O termo "Crédito" (resgate da reserva pra compensar o
   déficit) e o "Crédito expirado" (pago por vencer sem uso) são coisas diferentes, mas
   o detalhamento não explica a diferença nem mostra o valor em R$ da expiração.

Backend **não muda**: já expõe `termos.receita_expiracao_reais`, e `consumo_fifo`/
`expiracao` (cada um `{origem, kwh}`) + a tarifa (`geracao.tarifa_kwh` / `parametros.tarifa`).

## Escopo

Apenas front, 2 componentes:
- `front/src/components/faturamento/PreviewPanel.vue` (Melhoria 1)
- `front/src/components/faturamento/AuditoriaAccordion.vue` (Melhoria 2)

## Melhoria 1 — Crédito expirado nos cards e na fórmula (PreviewPanel)

**Regra:** só aparece **quando `receita_expiracao_reais > 0`**. Sem expiração, a tela
fica idêntica a hoje (4 termos).

- **Card novo** ao lado dos 4: rótulo **"Crédito expirado"**, valor
  `formatReais(termos.receita_expiracao_reais)`. (mesmo `StatValue`, tom neutro/positivo)
- **Linha de fórmula** passa a incluir o termo, antes do `=`:
  `Fixo X + Injetado Y + Crédito Z − CUO W + Crédito expirado E = Total`
  O termo `+ Crédito expirado E` só entra quando `> 0` (DRY: a linha é montada a partir
  de um array de partes; a parte de expiração é condicional).

## Melhoria 2 — Resgatado vs expirado explícito (AuditoriaAccordion)

As duas seções existentes passam a:

1. **Rótulos claros:**
   - "Crédito resgatado por origem (FIFO)" → **"Crédito resgatado da reserva (compensa o déficit do mês)"**
   - "Crédito expirado" → **"Crédito expirado e pago (180 dias sem uso)"**
2. **Coluna em R$ (destaque), com tooltip do kWh:** cada linha mostra
   `formatReais(kwh × tarifa)`; ao passar o mouse (atributo `title`), mostra
   `formatKwh(kwh)`. A tarifa vem de `parametros.tarifa` (já disponível no componente).
   - Se a tarifa não estiver disponível (null), exibir o kWh direto (fallback), sem quebrar.

> A conversão R$ = kwh × tarifa é a mesma do motor (§6/§7: `kwh × tarifa`), então o
> número exibido reconcilia com os termos "Crédito" e "Crédito expirado" do PreviewPanel.

## Não-objetivos

- Backend (já manda tudo).
- Mudar o cálculo (só apresentação).
- Mexer nos outros termos (Fixo/Injetado/CUO) ou na geração líquida.

## Testes

Lógica testável extraída para função pura quando fizer sentido (ex.: montagem das partes
da fórmula, ou o cálculo R$=kwh×tarifa) — `node --test`, como o resto do front. A ligação
com a UI (card condicional, tooltip) é verificada manualmente no app (4 casos: com e sem
expiração; tooltip mostra kWh). Decisão de granularidade fica para o plano.
