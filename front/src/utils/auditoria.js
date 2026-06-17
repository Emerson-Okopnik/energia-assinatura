import { formatReais } from './formatters.js'

/**
 * Classifica a diferença entre o efetivamente pago e o atual (correto).
 * @param {number|null} pago @param {number|null} atual
 * @returns {{tipo:'a_mais'|'a_menos'|'igual'|'inconclusivo', valor:number}}
 */
export function rotuloDiferenca(pago, atual) {
  if (pago === null || pago === undefined || atual === null || atual === undefined) {
    return { tipo: 'inconclusivo', valor: 0 }
  }
  const dif = Number((pago - atual).toFixed(2))
  if (Math.abs(dif) < 0.01) return { tipo: 'igual', valor: 0 }
  return dif < 0 ? { tipo: 'a_menos', valor: -dif } : { tipo: 'a_mais', valor: dif }
}

/**
 * Linha da conta do "Atual" nos termos do app:
 * Fixo + Injetado + Crédito − CUO [+ Crédito expirado] = Valor final.
 * @param {{fixo:number,injetado:number,credito:number,cuo:number,credito_expirado:number,valor_final:number}} t
 * @returns {string}
 */
export function linhaConta(t) {
  if (!t) return ''
  const partes = [
    `Fixo ${formatReais(t.fixo)}`,
    `+ Injetado ${formatReais(t.injetado)}`,
    `+ Crédito ${formatReais(t.credito)}`,
    `− CUO ${formatReais(t.cuo)}`,
  ]
  if (Number(t.credito_expirado) > 0) {
    partes.push(`+ Crédito expirado ${formatReais(t.credito_expirado)}`)
  }
  return `${partes.join(' ')} = ${formatReais(t.valor_final)}`
}
