// Camada de acesso à API do fluxo "Faturar Usina".
// REGRA: endpoints, métodos HTTP, params e payloads são SEMANTICAMENTE
// idênticos aos usados pela página anterior (CalculoGeracao.vue).
// Nenhum cálculo acontece no frontend — só leitura/gravação.
import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL

function authHeaders(extra = {}) {
  return {
    Authorization: `Bearer ${localStorage.getItem('token')}`,
    ...extra,
  }
}

/** GET /usina — lista de usinas (com cliente e dado_geracao). */
export async function listarUsinas() {
  const { data } = await axios.get(`${baseURL}/usina`, { headers: authHeaders() })
  return data
}

/** GET /usina/{id} — usina com comercializacao e dado_geracao. */
export async function obterUsina(usiId) {
  const { data } = await axios.get(`${baseURL}/usina/${usiId}`, { headers: authHeaders() })
  return data
}

/**
 * DELETE /usina/{id} — exclui a usina.
 * Mesmo endpoint/método já usados pela antiga Usinas.vue (gestão de usinas);
 * não introduz contrato novo no backend.
 */
export async function excluirUsina(usiId) {
  const { data } = await axios.delete(`${baseURL}/usina/${usiId}`, { headers: authHeaders() })
  return data
}

/** GET /creditos-distribuidos-usina/usina/{id}/ano/{ano} — faturamento anual consolidado. */
export async function obterFaturamentoAnual(usiId, ano) {
  const { data } = await axios.get(
    `${baseURL}/creditos-distribuidos-usina/usina/${usiId}/ano/${ano}`,
    { headers: authHeaders() }
  )
  return data?.[0] ?? null
}

/** GET /dados-geracao-real-usina/usina/{id} — geração real por ano. */
export async function obterGeracaoReal(usiId, ano) {
  const { data } = await axios.get(
    `${baseURL}/dados-geracao-real-usina/usina/${usiId}`,
    { headers: authHeaders() }
  )
  const lista = Array.isArray(data) ? data : []
  const registroAno = lista.find((item) => Number(item.ano) === Number(ano))
  return registroAno?.dados_geracao_real || {}
}

/** GET /usinas/{id}/ultimo-revertivel — { he_id, ano, mes, mes_nome } | null. */
export async function obterUltimoRevertivel(usiId) {
  const { data } = await axios.get(
    `${baseURL}/usinas/${usiId}/ultimo-revertivel`,
    { headers: authHeaders() }
  )
  return data ?? null
}

/** GET /usinas/{id}/historico-estorno — trilha de auditoria (50 últimos). */
export async function obterHistoricoEstorno(usiId) {
  const { data } = await axios.get(
    `${baseURL}/usinas/${usiId}/historico-estorno`,
    { headers: authHeaders() }
  )
  return Array.isArray(data) ? data : []
}

/** GET /usinas/{id}/inputs-salvos/{ano} — { [mes]: { fatura_energia, consumo } }. */
export async function obterInputsSalvos(usiId, ano) {
  const { data } = await axios.get(
    `${baseURL}/usinas/${usiId}/inputs-salvos/${ano}`,
    { headers: authHeaders() }
  )
  return data?.data ?? {}
}

/** GET /usinas/{id}/projecao/{ano} — Expectativa anual (12 meses projetados). */
export async function obterProjecao(usiId, ano) {
  const { data } = await axios.get(
    `${baseURL}/usinas/${usiId}/projecao/${ano}`,
    { headers: authHeaders() }
  )
  return data?.data ?? []
}

/**
 * GET /usinas/{id}/faturamento/{ano}/mes/{mes}/preview — simulação do mês.
 * `consumo` só entra nos params quando informado (senão o backend usa o salvo).
 */
export async function obterPreview(usiId, ano, mesIndex, { geracaoBrutaKwh, faturaEnergia, adicionalCuo, consumo }) {
  const params = {
    geracao_bruta_kwh: Number(geracaoBrutaKwh) || 0,
    fatura_energia: Number(faturaEnergia) || 0,
    adicional_cuo: Number(adicionalCuo) || 0,
  }
  if (consumo !== null && consumo !== undefined && consumo !== '') {
    params.consumo = Number(consumo) || 0
  }
  const { data } = await axios.get(
    `${baseURL}/usinas/${usiId}/faturamento/${ano}/mes/${mesIndex}/preview`,
    { headers: authHeaders(), params }
  )
  return data?.data ?? data ?? null
}

/**
 * POST /usinas/{id}/consumo/{ano}/mes/{mes} — UPSERT do consumo do mês.
 * O backend atualiza só o mês no registro do ano (não zera os outros 11
 * meses nem cria duplicata) e recalcula a média — correção F2.
 */
export async function salvarConsumoMes(usiId, ano, mesIndex, consumo) {
  const { data } = await axios.post(
    `${baseURL}/usinas/${usiId}/consumo/${ano}/mes/${mesIndex}`,
    { consumo: parseFloat(consumo || 0) },
    { headers: authHeaders() }
  )
  return data
}

/** POST /usinas/{id}/faturamento/{ano}/mes/{mes}/calculo — persiste o lançamento. */
export async function salvarCalculo(usiId, ano, mesIndex, { geracaoBrutaKwh, consumo, faturaEnergia, adicionalCuo }) {
  const idempotencyKey = self.crypto?.randomUUID?.() || Date.now().toString()
  const payload = {
    geracao_bruta_kwh: parseFloat(geracaoBrutaKwh || 0),
    consumo: parseFloat(consumo || 0),
    fatura_energia: parseFloat(faturaEnergia || 0),
    adicional_cuo: parseFloat(adicionalCuo || 0),
  }
  const { data } = await axios.post(
    `${baseURL}/usinas/${usiId}/faturamento/${ano}/mes/${mesIndex}/calculo`,
    payload,
    { headers: authHeaders({ 'Idempotency-Key': idempotencyKey }) }
  )
  return data
}

/** POST /usinas/{id}/faturamento/{ano}/mes/{mes}/estorno — reverte o lançamento. */
export async function estornarMes(usiId, ano, mesIndex) {
  const { data } = await axios.post(
    `${baseURL}/usinas/${usiId}/faturamento/${ano}/mes/${mesIndex}/estorno`,
    {},
    { headers: authHeaders() }
  )
  return data
}

/** GET /gerar-pdf-usina/{id} — PDF da fatura (blob). */
export async function gerarPdfUsina(usiId, { observacoes, mes, ano, fatura, adicionalCuo }) {
  const response = await axios.get(`${baseURL}/gerar-pdf-usina/${usiId}`, {
    headers: authHeaders(),
    params: {
      observacoes,
      mes,
      ano,
      fatura,
      adicional_cuo: Number(adicionalCuo) || 0,
    },
    responseType: 'blob',
  })
  return response.data
}
