/**
 * Lógica pura do detalhamento do lançamento (fonte única — consumida pela UI).
 */

/**
 * Converte energia em dinheiro pela tarifa. Null se a tarifa não for um número.
 * @param {number} kwh @param {number|null} tarifa @returns {number|null}
 */
export function valorEmReais(kwh, tarifa) {
  if (!Number.isFinite(tarifa)) return null
  return Number((kwh * tarifa).toFixed(2))
}

/**
 * Partes da fórmula do valor final (sem o total). CUO entra negativo (é subtraído).
 * "Crédito expirado" (receita de expiração, PAGA TUDO) só entra quando > 0.
 * @param {object} termos @returns {Array<{label: string, valor: number}>}
 */
export function partesFormula(termos) {
  if (!termos) return []
  const partes = [
    { label: 'Fixo', valor: Number(termos.valor_fixo_reais) || 0 },
    { label: 'Injetado', valor: Number(termos.valor_variavel_reais) || 0 },
    { label: 'Crédito', valor: Number(termos.credito_reais) || 0 },
    { label: 'CUO', valor: -(Number(termos.cuo_reais) || 0) },
  ]
  const expiracao = Number(termos.receita_expiracao_reais) || 0
  if (expiracao > 0) {
    partes.push({ label: 'Crédito expirado', valor: expiracao })
  }
  return partes
}
