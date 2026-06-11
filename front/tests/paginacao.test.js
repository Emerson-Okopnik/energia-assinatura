import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { ELIPSE, calcularPaginas, calcularPaginasVisiveis } from '../src/utils/paginacao.js'

describe('calcularPaginas', () => {
  it('total 0: uma única página vazia, slice 0..0', () => {
    const r = calcularPaginas(0, 12, 1)
    assert.equal(r.totalPaginas, 1)
    assert.equal(r.paginaAtual, 1)
    assert.equal(r.inicio, 0)
    assert.equal(r.fim, 0)
    assert.deepEqual(r.paginasVisiveis, [1])
  })

  it('total negativo é tratado como 0', () => {
    const r = calcularPaginas(-5, 12, 3)
    assert.equal(r.totalPaginas, 1)
    assert.equal(r.inicio, 0)
    assert.equal(r.fim, 0)
  })

  it('1 página: todos os itens cabem, sem reticências', () => {
    const r = calcularPaginas(8, 12, 1)
    assert.equal(r.totalPaginas, 1)
    assert.equal(r.inicio, 0)
    assert.equal(r.fim, 8)
    assert.deepEqual(r.paginasVisiveis, [1])
  })

  it('várias páginas: calcula slice da página intermediária', () => {
    // 50 itens, 12 por página -> 5 páginas (ceil 4.16)
    const r = calcularPaginas(50, 12, 3)
    assert.equal(r.totalPaginas, 5)
    assert.equal(r.paginaAtual, 3)
    assert.equal(r.inicio, 24)
    assert.equal(r.fim, 36)
  })

  it('última página parcial: fim limitado ao total', () => {
    const r = calcularPaginas(50, 12, 5)
    assert.equal(r.inicio, 48)
    assert.equal(r.fim, 50)
  })

  it('muitas páginas com elipse dos dois lados', () => {
    // 20 páginas, atual 10 -> [1, …, 9, 10, 11, …, 20]
    const r = calcularPaginas(20 * 12, 12, 10)
    assert.equal(r.totalPaginas, 20)
    assert.deepEqual(r.paginasVisiveis, [1, ELIPSE, 9, 10, 11, ELIPSE, 20])
  })

  it('página acima do range é clampada para a última', () => {
    const r = calcularPaginas(50, 12, 999)
    assert.equal(r.totalPaginas, 5)
    assert.equal(r.paginaAtual, 5)
    assert.equal(r.inicio, 48)
    assert.equal(r.fim, 50)
  })

  it('página abaixo do range (0 ou negativa) é clampada para 1', () => {
    const r = calcularPaginas(50, 12, 0)
    assert.equal(r.paginaAtual, 1)
    assert.equal(r.inicio, 0)
    assert.equal(r.fim, 12)

    const r2 = calcularPaginas(50, 12, -3)
    assert.equal(r2.paginaAtual, 1)
  })

  it('itensPorPagina inválido cai para 1', () => {
    const r = calcularPaginas(3, 0, 1)
    assert.equal(r.totalPaginas, 3)
    assert.equal(r.fim, 1)
  })

  it('parâmetros não numéricos não lançam', () => {
    const r = calcularPaginas(NaN, undefined, NaN)
    assert.equal(r.totalPaginas, 1)
    assert.equal(r.paginaAtual, 1)
    assert.equal(r.inicio, 0)
    assert.equal(r.fim, 0)
  })
})

describe('calcularPaginasVisiveis', () => {
  it('até 7 páginas mostra todas sem reticências', () => {
    assert.deepEqual(calcularPaginasVisiveis(7, 4), [1, 2, 3, 4, 5, 6, 7])
    assert.deepEqual(calcularPaginasVisiveis(1, 1), [1])
  })

  it('atual no início: elipse só à direita', () => {
    // 20 páginas, atual 2 -> [1, 2, 3, …, 20]
    assert.deepEqual(calcularPaginasVisiveis(20, 2), [1, 2, 3, ELIPSE, 20])
  })

  it('atual no fim: elipse só à esquerda', () => {
    // 20 páginas, atual 19 -> [1, …, 18, 19, 20]
    assert.deepEqual(calcularPaginasVisiveis(20, 19), [1, ELIPSE, 18, 19, 20])
  })

  it('salto de exatamente 2 mostra o número em vez de elipse', () => {
    // 20 páginas, atual 3 -> vizinhas {2,3,4} + {1,20};
    // entre 1 e 2 não há salto; entre 4 e 20 há salto grande -> elipse.
    assert.deepEqual(calcularPaginasVisiveis(20, 3), [1, 2, 3, 4, ELIPSE, 20])
    // atual 4 -> {1} e {3,4,5}: salto de 1 para 3 é exatamente 2 -> mostra 2.
    assert.deepEqual(calcularPaginasVisiveis(20, 4), [1, 2, 3, 4, 5, ELIPSE, 20])
  })
})
