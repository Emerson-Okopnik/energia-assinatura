# Auditoria — Fase 1: baseline no banco (Antes + Pago) — Design

> Design · 2026-06-17 · branch `feat/auditoria-bater-pago-real`
> Fundação de dados da nova auditoria (página no app). Persiste no banco o "valor que
> estava no sistema" (1º dump) e o "valor efetivamente pago" (planilha), por usina/mês.
> Fases seguintes (não nesta spec): Fase 2 = página de auditoria; Fase 3 = inputs.

## Contexto

A auditoria vai virar página no app (decisão 2026-06-17), aposentando o HTML standalone
(`reconstruir.php`). A página mostrará, por usina/mês: **Antes** (sistema original) ·
**Efetivamente pago** (planilha) · **Atual** (motor, ao vivo). O "Atual" vem do motor
(fonte única). Os outros dois **não existem em produção** (o "Antes" foi sobrescrito pela
correção; o "Pago" só existe na planilha), então precisam ser **persistidos no banco**.

Fontes (ambas fora de produção, lidas offline):
- **Antes** = `geracao_faturamento_pdf.valor_final` do 1º dump `energia_antes_20260611_164628.dump`.
- **Pago** = planilha `Controle geral Consorcio → Faturamento Usinas` (ver [[planilha-pago-real]]):
  mapa de UC (6 casos por nome), swap de maio (3 casos), parse BR. Extração já validada
  em `pago-real-extraido.csv` (501 linhas, swaps corrigidos).

## Objetivo (Fase 1)

Criar a tabela `auditoria_baseline` e populá-la com **Antes** e **Pago** por usina/mês, de
forma idempotente e reproduzível. Sem tela e sem inputs (fases 2 e 3).

## Componentes

### 1. Migration `auditoria_baseline`

```
auditoria_baseline:
  ab_id            (increments)
  usi_id           (unsignedInteger, FK usina.usi_id, cascade)
  competencia      (date, dia 1 do mês)
  valor_sistema_antes  (decimal 12,2, nullable)  -- do 1º dump; null = sem demonstrativo
  valor_pago           (decimal 12,2, nullable)  -- da planilha; null = sem pago registrado
  fatura_informada     (decimal 12,2, nullable)  -- Fase 3 (resolver inconclusivo)
  consumo_informado    (decimal 12,2, nullable)  -- Fase 3
  timestamps
  unique(usi_id, competencia)
```
Segue o padrão de `geracao_faturamento_pdf` (usi_id unsignedInteger, unique usi_id+competencia, FK cascade).

### 2. Model `AuditoriaBaseline`

`App\Models\AuditoriaBaseline`: `$table='auditoria_baseline'`; `$fillable` dos 6 campos;
casts `competencia=>date:Y-m-d`, valores `=>float`; mutator de `competencia` para
normalizar `Y-m-d` (igual ao `FaturaFonte`). `$timestamps=true`.

### 3. Extratores offline (geram CSV) — não tocam produção

- **`extrair_antes.php`** (em `storage/reconstrucao/`): conecta no dump `energia_antes`
  restaurado (env DB_*), lê `geracao_faturamento_pdf` e escreve
  `storage/reconstrucao/auditoria-antes.csv` com `uc,competencia,valor_sistema_antes`.
- **`extrair_pago_planilha.py`** (em `storage/reconstrucao/`): lê o CSV da planilha e
  escreve `auditoria-pago.csv` com `uc,competencia,valor_pago`. Encapsula:
  - parse BR (`R$ 1.234,56`→1234.56; `R$ -`→0; texto→ignora);
  - colunas: `mai/25..fev/26` único (12–21); de mar/26 pares (1ª=pago, 2ª=fatura): mar(22,23)…set(34,35);
  - mapa UC→banco (6 casos): 43044→521206860, 47180→562606800, 2208→113906836, 59098332→6656137, 4189733→41897333, 59244413→9244413;
  - swap de maio: por par, a coluna ≈ fatura de referência é a fatura, a outra é o pago (referência: `fatura-fonte.csv` já gerada). Validado: 3 swaps.
  - A saída usa **UC do banco** (já mapeada).

> Os extratores são utilitários operacionais (rodam contra dump/planilha, offline);
> validados por inspeção do caso âncora (Romeu) e contagens. Sem teste automatizado.

### 4. Comando `auditoria:importar-baseline`

`php artisan auditoria:importar-baseline {--antes=} {--pago=}` (caminhos dos CSVs).
Para cada CSV, resolve `usi_id` pela UC (via `usina.uc`), e faz `updateOrCreate` por
(usi_id, competencia) preenchendo o campo respectivo (`valor_sistema_antes` ou
`valor_pago`). Idempotente; re-rodar não duplica. UC sem usina no banco → conta e reporta
como "ignorada" (ex.: as 3 pendências Solar Jungblut/Zito/Luciane se não casarem o mês),
sem abortar.

## Critérios de aceite

- Tabela criada; migration aplica em SQLite (teste) e Postgres.
- Importar `auditoria-antes.csv` → linhas com `valor_sistema_antes`; Romeu (521206860)
  2026-01 ≈ 1.059,21.
- Importar `auditoria-pago.csv` → linhas com `valor_pago`; Romeu 2026-01 = 1.058,75 e
  2026-05 = 3.850,86 (swap resolvido).
- Re-rodar o comando não cria duplicatas (mesmo (usi_id, competencia)).
- UCs sem match são reportadas, não quebram o import.

## Testes

- `AuditoriaBaselineTest` (Feature, SQLite): criar usina de teste; rodar
  `auditoria:importar-baseline` com CSVs pequenos (fixtures inline ou tmp); asserir que a
  linha (usi_id, competencia) tem o valor certo; asserir idempotência (2ª rodada = mesma
  contagem); asserir que UC inexistente é ignorada (contador), não quebra.
- Extratores: sem teste automatizado (utilitários offline), validados por inspeção.

## Não-objetivos (Fase 1)

- Página/tela (Fase 2). Inputs de fatura/consumo e recálculo (Fase 3).
- Calcular o "Atual" (é do motor, na Fase 2).
- Resolver as 3 pendências (Solar Jungblut/Zito/Luciane) — só registrar.
