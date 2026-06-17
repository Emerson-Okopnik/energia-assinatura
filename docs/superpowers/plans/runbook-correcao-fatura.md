# Runbook — Correção da `fatura_energia` em produção (PAGA TUDO)

> Aplica a correção que faz o sistema registrar o Valor Final correto de cada mês
> (fatura real + expiração paga). **Não** mexe no que já foi pago ao cliente.
> Pré-requisito: branch `fix/recalculo-faturamento-auditoria` deployada (comandos e migration).

## Contexto

O backfill faturou com `fatura_energia = 0`, inflando o Valor Final. A fatura real só
existe no dump pré-correção (`energia_antes_20260611_164628.dump`). A correção extrai a
fatura desse dump, importa numa tabela-fonte e re-materializa cada mês pelo motor único,
preservando os lançamentos manuais já feitos em produção.

Precedência da fatura por (usina, competência): **prod > 0** (lançamento manual) →
**fatura-fonte** (derivada do dump) → **0** (sem dado).

## Pré-checagem

- [ ] Backup do banco de produção feito e validado (restaurável). **OBRIGATÓRIO.**
- [ ] Dump antigo `energia_antes_20260611_164628.dump` disponível.
- [ ] Acesso ao banco de produção (bastion) e capacidade de rodar `php artisan` na app.

## Passos

### 1. Backup de produção
Gerar e guardar o dump atual de produção antes de qualquer escrita. Sem isto, não prosseguir.

### 2. Migration (cria a tabela `fatura_fonte`)
```bash
php artisan migrate --force
```
Confirmar que a tabela `fatura_fonte` existe.

### 3. Extrair a fatura-fonte do dump antigo
Restaurar o dump antigo num Postgres **temporário** (não produção) e rodar:
```bash
DB_HOST=<host_temp> DB_PORT=<porta_temp> DB_NAME=<db_dump_antigo> DB_USER=<user> DB_PASS=<pass> \
  php storage/reconstrucao/extrair_fatura_fonte.php
```
Gera `storage/reconstrucao/fatura-fonte.csv`.
- [ ] Conferir: ≈ **311 linhas, 270 com fatura > 0**.
- [ ] Conferir caso âncora: UC `521206860` (Romeu) `2026-01` = **1663.71**.

### 4. Importar a fatura-fonte em produção
```bash
php artisan faturamento:importar-fatura-fonte storage/reconstrucao/fatura-fonte.csv
```
Idempotente (pode re-rodar). Confirmar a contagem importada.

### 5. Dry-run em produção (não grava)
```bash
php artisan faturamento:corrigir-fatura --dry-run
```
Revisar `storage/reconstrucao/correcao-fatura-antes-depois.csv`:
- [ ] Romeu (`521206860`) Jan/2026: `valor_antes` 4110,00 → `valor_depois` **2446,29**.
- [ ] Os 4 lançamentos manuais aparecem com `fatura_origem = prod` e `delta = 0,00`:
      `521206860/2026-05`, `113906836/2026-05`, `562606800/2026-05`, `6656137/2026-06`.
- [ ] Meses com `fatura_origem = zero` = lista para revisão manual (≈222; sem fatura no histórico).
- [ ] Nenhum mês `prod` com delta ≠ 0 (lançamentos manuais não podem ser alterados).

### 6. Aplicar (grava, em transação)
```bash
php artisan faturamento:corrigir-fatura
```

### 7. Refazer a auditoria (prova histórica)
Rodar `storage/reconstrucao/reconstruir.php` contra o dump antigo restaurado (já com
PAGA TUDO) e arquivar `relatorio.html` + CSV **junto do dump** como evidência do
pago-antigo × correto. (O relatório é gitignored — preservar manualmente.)

### 8. Validar a tela
- [ ] Abrir a tela do Romeu (forçar refresh / limpar cache): Janeiro/2026 = **2.446,29**.
- [ ] Conferir 2-3 outras usinas contra o CSV antes×depois.

## Rollback
Restaurar o backup do passo 1. (A correção é idempotente; re-rodar o passo 6 após
reimportar a fatura-fonte reproduz o mesmo estado, mas o caminho seguro de reversão é o backup.)

## Notas
- `--usina=<UC>` em qualquer comando limita a uma usina (útil para validar 1 caso antes do lote).
- O motor paga a expiração (PAGA TUDO) — a expiração continua registrada no ledger e
  compõe o Valor Final do mês do vencimento. Nada é "removido" do histórico de crédito.
