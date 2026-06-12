// Cálculo puro de paginação client-side.
// Usado por Paginacao.vue e testado em tests/paginacao.test.js.
//
// calcularPaginas(total, itensPorPagina, paginaAtual) ->
//   {
//     totalPaginas,        // nº de páginas (mínimo 1)
//     paginaAtual,         // página normalizada (clampada ao range [1, totalPaginas])
//     inicio,              // índice 0-based do primeiro item da página (slice início)
//     fim,                 // índice 0-based exclusivo do último item (slice fim)
//     paginasVisiveis[],   // sequência de páginas + reticências (ex.: [1, '…', 4, 5, 6, '…', 20])
//   }
//
// Convenções:
//   - total <= 0  -> totalPaginas 1, inicio 0, fim 0, paginasVisiveis [1]
//   - paginaAtual fora do range é clampada (nunca lança).

export const ELIPSE = '…'

/**
 * @param {number} total Total de itens da coleção.
 * @param {number} itensPorPagina Itens exibidos por página (>= 1).
 * @param {number} paginaAtual Página solicitada (1-based).
 * @returns {{totalPaginas:number, paginaAtual:number, inicio:number, fim:number, paginasVisiveis:Array<number|string>}}
 */
export function calcularPaginas(total, itensPorPagina, paginaAtual) {
  const tot = Number.isFinite(total) && total > 0 ? Math.floor(total) : 0
  const porPagina =
    Number.isFinite(itensPorPagina) && itensPorPagina >= 1 ? Math.floor(itensPorPagina) : 1

  const totalPaginas = Math.max(1, Math.ceil(tot / porPagina))

  // Clampa a página solicitada ao intervalo válido.
  let pagina = Number.isFinite(paginaAtual) ? Math.floor(paginaAtual) : 1
  if (pagina < 1) pagina = 1
  if (pagina > totalPaginas) pagina = totalPaginas

  const inicio = tot === 0 ? 0 : (pagina - 1) * porPagina
  const fim = Math.min(inicio + porPagina, tot)

  return {
    totalPaginas,
    paginaAtual: pagina,
    inicio,
    fim,
    paginasVisiveis: calcularPaginasVisiveis(totalPaginas, pagina),
  }
}

/**
 * Gera a sequência de páginas a exibir com reticências.
 * Mostra sempre 1 e a última; uma janela de vizinhas ao redor da atual.
 * Ex.: total 20, atual 5 -> [1, '…', 4, 5, 6, '…', 20]
 * @param {number} totalPaginas
 * @param {number} paginaAtual
 * @returns {Array<number|string>}
 */
export function calcularPaginasVisiveis(totalPaginas, paginaAtual) {
  // Até 7 páginas: exibe todas, sem reticências.
  if (totalPaginas <= 7) {
    return Array.from({ length: totalPaginas }, (_, i) => i + 1)
  }

  const vizinhas = 1 // quantas páginas mostrar de cada lado da atual
  const paginas = new Set([1, totalPaginas])
  for (let p = paginaAtual - vizinhas; p <= paginaAtual + vizinhas; p++) {
    if (p >= 1 && p <= totalPaginas) paginas.add(p)
  }

  const ordenadas = Array.from(paginas).sort((a, b) => a - b)

  // Insere reticências onde há saltos > 1.
  const resultado = []
  let anterior = 0
  for (const p of ordenadas) {
    if (anterior && p - anterior > 1) {
      // Salto de exatamente 2 -> mostra a página intermediária em vez de '…'.
      if (p - anterior === 2) resultado.push(anterior + 1)
      else resultado.push(ELIPSE)
    }
    resultado.push(p)
    anterior = p
  }

  return resultado
}
