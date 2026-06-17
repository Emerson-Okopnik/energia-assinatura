# Auditoria — Fase 2: página no app (Antes · Efetivamente pago · Atual) — Design

> Design · 2026-06-17 · branch a partir da Fase 1 (PR #10)
> A página de auditoria: lista de usinas (leve) + pop-up com o detalhe mês a mês,
> nas nomenclaturas e padrões do app. Read-only. (Fase 3 = inputs/recálculo.)

## Contexto

A Fase 1 persistiu o baseline (`auditoria_baseline`: `valor_sistema_antes`, `valor_pago`
por usina/mês). A Fase 2 é a TELA que mostra, por usina/mês, **Antes · Efetivamente pago
· Atual**, com a diferença principal **Efetivamente pago − Atual** (pago a mais / a menos).

**Descoberta que simplifica:** o "Atual" = valor que está em produção hoje =
`geracao_faturamento_pdf.valor_final` (já corrigido — PAGA TUDO). **Não precisa do motor
ao vivo nesta fase** — tudo é leitura de banco. O motor (preview) entra só na Fase 3
(recalcular ao informar fatura). Os 4 termos da conta + o crédito expirado também saem de
`geracao_faturamento_pdf` (fixo/injetado/creditado/cuo/valor_final; `credito_expirado =
valor_final − (fixo+injetado+creditado−cuo)`).

## Nomenclaturas (fixas pelo cliente) e padrões do app

- 3 colunas: **Antes** · **Efetivamente pago** · **Atual**. Diferença = **pago − atual**.
- pago < atual → "pagamos a menos"; pago > atual → "pagamos a mais".
- Conta do Atual (termos do app, PR #9): `Fixo + Injetado + Crédito − CUO + Crédito expirado = Valor final`; rótulos "Crédito resgatado da reserva" e "Crédito expirado e pago".
- **Inconclusivo:** mês cuja `fatura_energia` (atual) é 0/nula → o Atual não é confiável →
  marcado e **fora dos totais** (decisão: fatura zerada = inconclusivo).
- Linguagem simples, sem travessões soltos; usar `formatReais`/`formatKwh`, componentes
  base (`StatValue`, `DataTable`, `BaseBadge`, `BaseButton`), tokens/design system.

## Definição dos dados (por usina/mês)

Para cada (usina, competência) na união de `auditoria_baseline` e `geracao_faturamento_pdf`:
- `antes` = `auditoria_baseline.valor_sistema_antes` (ou null)
- `pago` = `auditoria_baseline.valor_pago` (ou null)
- `atual` = `geracao_faturamento_pdf.valor_final` (ou null)
- `fatura_atual` = `geracao_faturamento_pdf.fatura_energia`
- `status`:
  - **inconclusivo** se `atual` é null OU `fatura_atual` é 0/nula
  - senão **conclusivo**; `diferenca = pago − atual` (pago null → não entra no saldo)
- `termos` (quando há atual): fixo, injetado, credito, cuo, valor_final + `credito_expirado` derivado.

## Componentes

### Backend (2 endpoints read-only, sob `auth:api`)

1. **`GET /auditoria/usinas`** — lista leve + totais globais.
   - Por usina: `usi_id, uc, cliente, saldo (Σ pago−atual dos conclusivos), meses_divergentes (|dif|≥0,01), inconclusivos (contagem)`.
   - Totais globais: `pago_a_mais, pago_a_menos, saldo, total_inconclusivos`.
   - Calculado por SQL agregando baseline + geracao_faturamento_pdf (sem motor).
2. **`GET /auditoria/usinas/{usiId}`** — detalhe mês a mês (carregado ao abrir o pop-up).
   - Linhas ordenadas por competência: `competencia, antes, pago, atual, fatura_atual, status, diferenca, termos{fixo,injetado,credito,cuo,credito_expirado,valor_final}`.
   - Resumo da usina: `pago_total, atual_total, saldo` (conclusivos).

Service `AuditoriaService` encapsula as duas consultas (SRP; o controller só orquestra).
Rota nova em `routes/api.php` no grupo `auth:api`.

### Frontend

- **Página `Auditoria.vue`** (rota nova `/auditoria` no router + item no menu): cards de
  resumo (pago a mais/menos, saldo, inconclusivos), busca (usina/cliente/UC), e a **lista**
  de usinas (saldo, meses, inconclusivos). Lista vem de `GET /auditoria/usinas`.
- **Modal `AuditoriaUsinaModal.vue`** (pop-up): ao clicar numa usina, faz
  `GET /auditoria/usinas/{usiId}` (**lazy** — só ao abrir), mostra resumo + tabela
  (Antes/Efetiv. pago/Atual/Diferença) + "ver conta" por mês (expande a fórmula nos termos
  do app) + badge de inconclusivo. Reusa `DataTable`/`StatValue`/`BaseBadge`.
- **Service** `auditoriaApi.js`: `obterAuditoriaUsinas()`, `obterAuditoriaUsina(usiId)`.

## Arquitetura / contrato (para o time paralelo)

Backend e frontend rodam em paralelo amarrados a este **contrato de API** (shape exato):

```
GET /auditoria/usinas -> {
  totais: { pago_a_mais, pago_a_menos, saldo, total_inconclusivos },
  usinas: [ { usi_id, uc, cliente, saldo, meses_divergentes, inconclusivos } ]
}
GET /auditoria/usinas/{usiId} -> {
  usina: { usi_id, uc, cliente },
  resumo: { pago_total, atual_total, saldo },
  meses: [ {
    competencia, antes, pago, atual, fatura_atual,
    status: 'conclusivo'|'inconclusivo', diferenca,
    termos: { fixo, injetado, credito, cuo, credito_expirado, valor_final } | null
  } ]
}
```
Valores numéricos em reais (float, 2 casas) na resposta; formatação no front.

## Não-objetivos (Fase 2)

- Inputs de fatura/consumo e recálculo via motor (Fase 3).
- Alterar dados (read-only).
- Aposentar formalmente o `reconstruir.php` (fica como histórico).

## Testes

- Backend: Feature test do `AuditoriaService`/endpoints com usina de teste + baseline +
  geracao_faturamento_pdf semeados; asserir saldo, contagem de inconclusivos (fatura 0),
  diferença por mês, e o credito_expirado derivado. Caso âncora: Romeu Jan antes=1059,21,
  pago=1058,75, atual=2446,29, dif a menos 1387,54.
- Frontend: lógica pura testável (ex.: rotular status/diferença, montar a fórmula) em
  `node --test`; a ligação visual (modal, lazy) verificada manualmente no app.
