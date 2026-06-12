// Fonte ÚNICA de formatação (pt-BR) do frontend.
// Convenção do projeto (REGRAS_DE_CALCULO.md §1):
//   - Dinheiro: "R$ 1.561,11"
//   - Energia:  "9.858,00 kWh"
//   - Número:   "1.234,56"
// Funções puras, sem efeito colateral. Tolera null/undefined/NaN -> 0.

function toNumero(valor) {
  const numero = typeof valor === 'number' ? valor : parseFloat(valor)
  return Number.isFinite(numero) ? numero : 0
}

function formatarPtBr(valor, casas) {
  return toNumero(valor).toLocaleString('pt-BR', {
    minimumFractionDigits: casas,
    maximumFractionDigits: casas,
  })
}

export function formatNumero(valor, casas = 2) {
  return formatarPtBr(valor, casas)
}

export function formatReais(valor, casas = 2) {
  return `R$ ${formatarPtBr(valor, casas)}`
}

export function formatKwh(valor, casas = 2) {
  return `${formatarPtBr(valor, casas)} kWh`
}
