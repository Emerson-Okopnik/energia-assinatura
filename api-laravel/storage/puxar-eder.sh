#!/usr/bin/env bash
# Abre túnel SSH pelo bastion até o RDS e puxa os dados do Eder (UC 562606800).
# Uso: preencha as variáveis abaixo e rode  ->  bash puxar-eder.sh
# A saída é salva em  storage/eder-dump.txt  (que o Claude vai ler).
set -euo pipefail

# ─── PREENCHA ESTES VALORES ───────────────────────────────────────────────
SSH_KEY="${SSH_KEY:-$HOME/.ssh/id_rsa}"   # caminho da sua chave SSH privada
BASTION_IP="${BASTION_IP:-}"              # terraform output bastion_public_ip
BASTION_USER="${BASTION_USER:-ubuntu}"    # bastion é Ubuntu 22.04
RDS_HOST="${RDS_HOST:-}"                  # db_endpoint SEM a porta (só o host)
RDS_PORT="${RDS_PORT:-5432}"
DB_USER="${DB_USER:-}"                    # usuário mestre do RDS
DB_NAME="${DB_NAME:-}"                    # nome do banco
export PGPASSWORD="${PGPASSWORD:-}"       # senha do banco (ou deixe vazio p/ digitar)
LOCAL_PORT="${LOCAL_PORT:-5433}"          # porta local do túnel (evita colidir c/ 5432)
# ──────────────────────────────────────────────────────────────────────────

OUT="$(dirname "$0")/eder-dump.txt"
UC="562606800"

if [[ -z "$BASTION_IP" || -z "$RDS_HOST" || -z "$DB_USER" || -z "$DB_NAME" ]]; then
  echo "ERRO: preencha BASTION_IP, RDS_HOST, DB_USER e DB_NAME no topo do script." >&2
  exit 1
fi

echo ">> Abrindo túnel SSH ${LOCAL_PORT} -> ${RDS_HOST}:${RDS_PORT} via ${BASTION_USER}@${BASTION_IP} ..."
ssh -i "$SSH_KEY" -f -N -o ExitOnForwardFailure=yes -o StrictHostKeyChecking=accept-new \
    -L "${LOCAL_PORT}:${RDS_HOST}:${RDS_PORT}" "${BASTION_USER}@${BASTION_IP}"
TUNNEL_PID=$(pgrep -f "${LOCAL_PORT}:${RDS_HOST}:${RDS_PORT}" | head -1 || true)
trap '[[ -n "${TUNNEL_PID:-}" ]] && kill "$TUNNEL_PID" 2>/dev/null || true' EXIT

sleep 2
echo ">> Túnel ativo (pid ${TUNNEL_PID}). Consultando dados do Eder (UC ${UC}) ..."

psql -h localhost -p "$LOCAL_PORT" -U "$DB_USER" -d "$DB_NAME" -X -A -F $'\t' --pset=footer=off <<SQL | tee "$OUT"
\echo === USINA ===
SELECT * FROM usina WHERE uc = '${UC}';
\echo === COMERCIALIZACAO ===
SELECT c.* FROM comercializacao c JOIN usina u ON u.com_id = c.com_id WHERE u.uc = '${UC}';
\echo === DADOS_GERACAO (projetada: media, menor_geracao, 12 meses) ===
SELECT d.* FROM dados_geracao d JOIN usina u ON u.dger_id = d.dger_id WHERE u.uc = '${UC}';
\echo === PACOTE ANUAL (creditos / reserva / faturamento) ===
SELECT cdu.ano, cd.*, var.*, fa.*
  FROM creditos_distribuidos_usina cdu
  JOIN usina u ON u.usi_id = cdu.usi_id
  LEFT JOIN creditos_distribuidos cd ON cd.cd_id = cdu.cd_id
  LEFT JOIN valor_acumulado_reserva var ON var.var_id = cdu.var_id
  LEFT JOIN faturamento_usina fa ON fa.fa_id = cdu.fa_id
  WHERE u.uc = '${UC}' ORDER BY cdu.ano;
\echo === GERACAO REAL (mes a mes) ===
SELECT dgru.ano, dgr.*
  FROM dados_geracao_real_usina dgru
  JOIN usina u ON u.usi_id = dgru.usi_id
  LEFT JOIN dados_geracao_real dgr ON dgr.dgr_id = dgru.dgr_id
  WHERE u.uc = '${UC}' ORDER BY dgru.ano;
\echo === HISTORICO ESTORNO ===
SELECT he.* FROM historico_estorno he
  JOIN usina u ON u.usi_id = he.usi_id WHERE u.uc = '${UC}' ORDER BY he.created_at;
SQL

echo ""
echo ">> Pronto. Dados salvos em: ${OUT}"
echo ">> Me avise para eu ler o arquivo."
