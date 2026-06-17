# Runbook — Carga do baseline da auditoria (Fase 1)

Pré: PR da Fase 1 deployado (migration + comando). Backup do banco recomendado.

1. **Migration:** `php artisan migrate --force` (cria auditoria_baseline).
2. **Gerar o CSV do Antes** (offline): restaurar o 1º dump `energia_antes_20260611_164628.dump`
   num Postgres temporário e rodar `extrair_antes.php` (DB_NAME=energia_antes) →
   `auditoria-antes.csv` (≈311 linhas; Romeu 2026-01 ≈ 1059,21).
3. **Gerar o CSV do Pago** (offline): rodar `extrair_pago_planilha.py` com a planilha +
   `fatura-ref.csv` → `auditoria-pago.csv` (501 linhas; 3 swaps; Romeu 2026-01=1058,75,
   2026-05=3850,86). A referência de fatura vem do banco atual (geracao_faturamento_pdf.fatura_energia), que inclui as faturas de maio dos lançamentos manuais — gerar fatura-ref.csv com uc,competencia,fatura.
4. **Copiar os 2 CSVs** para o servidor (SCP via bastion, como na correção de fatura).
5. **Importar:** `php artisan auditoria:importar-baseline --antes=/tmp/auditoria-antes.csv --pago=/tmp/auditoria-pago.csv`
   → conferir contagens de gravados/ignorados.
6. **Conferir:** Romeu (UC 521206860) 2026-01 com valor_sistema_antes≈1059,21 e valor_pago=1058,75.

As 3 pendências (Solar Jungblut/Zito/Luciane) podem aparecer como "ignoradas" se a UC/mês
não casar — registrado, sem ação nesta fase.
