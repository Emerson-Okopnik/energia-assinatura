import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL

function authHeaders() {
  return { Authorization: `Bearer ${localStorage.getItem('token')}` }
}

/** GET /auditoria/usinas — lista leve + totais. */
export async function obterAuditoriaUsinas() {
  const { data } = await axios.get(`${baseURL}/auditoria/usinas`, { headers: authHeaders() })
  return data
}

/** GET /auditoria/usinas/{id} — detalhe mês a mês (lazy, ao abrir o modal). */
export async function obterAuditoriaUsina(usiId) {
  const { data } = await axios.get(`${baseURL}/auditoria/usinas/${usiId}`, { headers: authHeaders() })
  return data
}
