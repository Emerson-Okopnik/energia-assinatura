/**
 * Regras de validade dos inputs de lançamento de faturamento (fonte única).
 *
 * - Consumo da usina: obrigatório INFORMAR (não pode null/NaN); 0 é um valor válido.
 * - Fatura de energia: obrigatória e MAIOR QUE ZERO (sem fatura não há CUO correto).
 *
 * Funções puras — testáveis isoladamente e consumidas pelos computeds do modal.
 */

/** @param {number|null} valor @returns {boolean} */
export function consumoValido(valor) {
  return valor !== null && Number.isFinite(valor)
}

/** @param {number|null} valor @returns {boolean} */
export function faturaValida(valor) {
  return valor !== null && Number.isFinite(valor) && valor > 0
}
