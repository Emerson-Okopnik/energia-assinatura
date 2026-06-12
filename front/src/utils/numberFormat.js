// Parsing e formatação de números em pt-BR (vírgula decimal, ponto de milhar).
// Funções puras, usadas pelo NumberInput.vue e testadas em tests/numberFormat.test.js.
//
// Convenção:
//   parseDecimalPtBr('1.234,56') -> 1234.56
//   parseDecimalPtBr('')         -> null  (vazio NUNCA vira 0 automático)
//   formatDecimalPtBr(1234.56)   -> '1.234,56'
//   formatDecimalPtBr(null)      -> ''

/**
 * Converte uma string pt-BR em Number.
 * Pontos são separadores de milhar, vírgula é o separador decimal.
 * Retorna null para vazio/inválido (nunca 0 automático).
 * @param {string|null|undefined} str
 * @returns {number|null}
 */
export function parseDecimalPtBr(str) {
  if (str === null || str === undefined) return null
  const limpo = String(str).trim()
  if (limpo === '') return null
  // Mantém apenas dígitos, sinal, ponto e vírgula
  const somenteValidos = limpo.replace(/[^\d.,-]/g, '')
  if (somenteValidos === '' || somenteValidos === '-') return null
  // Ponto = milhar (descarta), vírgula = decimal
  const normalizado = somenteValidos.replace(/\./g, '').replace(',', '.')
  const numero = Number(normalizado)
  return Number.isFinite(numero) ? numero : null
}

/**
 * Formata um Number no padrão pt-BR (ex.: 1234.56 -> '1.234,56').
 * Retorna '' para null/undefined/NaN.
 * @param {number|null|undefined} num
 * @param {number} [casas=2]
 * @returns {string}
 */
export function formatDecimalPtBr(num, casas = 2) {
  if (num === null || num === undefined) return ''
  const numero = typeof num === 'number' ? num : Number(num)
  if (!Number.isFinite(numero)) return ''
  return numero.toLocaleString('pt-BR', {
    minimumFractionDigits: casas,
    maximumFractionDigits: casas,
  })
}
