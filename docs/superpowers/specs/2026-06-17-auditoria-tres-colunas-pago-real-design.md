# Auditoria de 3 colunas: Antes · Efetivamente pago · Atual — Design

> Design · 2026-06-17 · branch `feat/auditoria-bater-pago-real`
> Evolui o relatório de auditoria (`reconstruir.php`) para mostrar, por usina/mês,
> o que estava no sistema (Antes), o que foi efetivamente pago (planilha) e o que
> deveria/deve ser (Atual = PAGA TUDO). Diferença principal = Pago × Atual.

## Contexto e problema

A auditoria atual (`reconstruir.php`) compara **Antes × Depois** (2 colunas), onde o
"Antes" vem do banco (`geracao_faturamento_pdf.valor_final`). Mas o banco não é fonte
confiável do que foi **efetivamente pago** ao usineiro (PDF 311 meses × faturamento_usina
495, divergentes — bug C5; a fatura nunca foi persistida como dado próprio).

Surgiu a fonte confiável do pago real: a planilha **Controle geral Consorcio → aba
Faturamento Usinas** (`/Users/matheus/Downloads/Controle geral Consorcio.xlsx -
Faturamento Usinas.csv`), com o valor pago mês a mês. Análise detalhada já feita
(ver [memória planilha-pago-real]): estrutura, mapa de UC (6 casos por nome), swap de
maio (3 casos resolvidos), 3 casos em aberto.

## Objetivo

Auditoria com **3 colunas** por usina/mês:

| Coluna | Significado | Fonte | Ex. Romeu Jan/2026 |
|--------|-------------|-------|--------------------|
| **Antes** | o que o sistema ORIGINAL calculava | `geracao_faturamento_pdf.valor_final` do dump **`energia_antes_20260611_164628.dump`** (o `finalA` que o script já lê) | 1.059,21 |
| **Efetivamente pago** | o que foi pago ao usineiro | planilha (extração `pago-real.csv`) | 1.058,75 |
| **Atual** | o que deve ser (correto, em produção) | motor PAGA TUDO (o `finalD` que o script já calcula) | 2.446,29 |

**Diferença principal = Efetivamente pago − Atual**, por usina e no total:
- pago < atual → **pagamos a MENOS** (devemos ao cliente)
- pago > atual → **pagamos a MAIS**
Vira o headline (cards) e o resumo por usina, substituindo o atual "Antes × Depois".

## Arquitetura (2 peças)

### 1. Extrator `extrair_pago_planilha.py`
Lê o CSV da planilha → escreve `pago-real.csv` (`uc_banco, competencia, pago`). Encapsula:
- **Parse BR:** `R$ 1.234,56` → 1234.56; `R$ -` → 0; célula com texto → ignora (sem pago).
- **Colunas:** `mai/25..fev/26` (cols 12–21, valor único); de **mar/26** em diante PARES
  (1ª=pago, 2ª=fatura CELESC): mar(22,23) abr(24,25) mai(26,27) jun(28,29) jul(30,31)
  ago(32,33) set(34,35).
- **Mapa UC planilha→banco** (6 casos, casar por nome): 43044→521206860 (Romeu),
  47180→562606800 (Eder), 2208→113906836 (Edo Eloi Weber), 59098332→6656137 (3K),
  4189733→41897333 (Odair), 59244413→9244413 (Darci). Demais: UC idêntica.
- **Swap de maio:** para cada par, comparar as 2 colunas com a `fatura_energia` de
  referência — a coluna ≈ fatura É a fatura, a outra é o pago. (Validado: só 3 swaps —
  Romeu, Eder, Edo em maio/26; sem falso-positivo.) A referência de fatura vem de um
  arquivo de fatura por (uc, competência) — a `fatura-fonte.csv` já gerada serve; o
  detalhe da fonte exata fica para o plano.
- Para os meses não-pareados não há swap.

Saída revisável (já validada: 501 linhas usina-mês, 3 swaps corrigidos).

### 2. `reconstruir.php` (modificar)
- Carrega `pago-real.csv` num mapa `[uc][competencia] => pago`.
- No drill-down por usina (tabela de valor final), entre **Antes** (`finalA`) e **Atual**
  (`finalD`), insere a coluna **Efetivamente pago** + a coluna **Δ (pago − atual)**.
- Headline/cards e resumo por usina passam a usar **Pago × Atual**: pago a mais, pago a
  menos, saldo (substituem os atuais, que usam Antes×Depois/valor final).
- Roda contra o dump **`energia_antes`** (garante `finalA` = sistema original).

## Ressalvas exibidas no relatório (honestidade)

- **Antes** só existe nos meses com demonstrativo (≈311); onde não há, "—".
- **Efetivamente pago** ausente em meses sem registro na planilha → "—" (não conta no total).
- **3 casos em aberto** (pago na planilha sem mês correspondente no banco): Solar Jungblut
  (mar 20.072), Zito (abr 3.523), Luciane (100) — listados numa seção própria "pendências",
  fora dos totais, para verificação manual.
- 6 UCs casadas por nome (não por UC) e 3 swaps de maio resolvidos — notas no rodapé.

## Não-objetivos

- Mexer no motor de cálculo (Atual = engine PAGA TUDO, inalterado).
- Alterar produção/banco (auditoria é relatório read-only sobre o dump).
- Resolver os 3 casos em aberto (só registrar).

## Testes

A lógica nova testável é o **extrator** (parse BR, mapa UC, detecção de swap). Extrair as
funções puras e cobri-las com testes (ex.: `node --test` ou pytest — definir no plano,
conforme a linguagem do extrator). O `reconstruir.php` é relatório standalone, validado
por inspeção do caso âncora (Romeu: Antes 1.059,21 · Pago 1.058,75 · Atual 2.446,29; e
o total Pago × Atual reconcilia com a análise: ~−26 mil sem maio, a recalcular com maio).
