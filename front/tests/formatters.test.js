import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { formatKwh, formatNumero, formatReais } from '../src/utils/formatters.js'

describe('formatters', () => {
  it('formatReais formata em pt-BR com prefixo R$', () => {
    assert.equal(formatReais(1561.11), 'R$ 1.561,11')
    assert.equal(formatReais(1234.56), 'R$ 1.234,56')
    assert.equal(formatReais(0), 'R$ 0,00')
  })

  it('formatKwh formata em pt-BR com sufixo kWh', () => {
    assert.equal(formatKwh(9858), '9.858,00 kWh')
    assert.equal(formatKwh(1234.56), '1.234,56 kWh')
  })

  it('formatNumero formata em pt-BR sem prefixo nem sufixo', () => {
    assert.equal(formatNumero(1234.56), '1.234,56')
    assert.equal(formatNumero(9858), '9.858,00')
  })

  it('tolera null/undefined/NaN tratando como 0', () => {
    assert.equal(formatReais(null), 'R$ 0,00')
    assert.equal(formatReais(undefined), 'R$ 0,00')
    assert.equal(formatReais(NaN), 'R$ 0,00')
    assert.equal(formatKwh(null), '0,00 kWh')
    assert.equal(formatNumero(undefined), '0,00')
  })

  it('aceita string numérica', () => {
    assert.equal(formatReais('1561.11'), 'R$ 1.561,11')
  })

  it('respeita o parâmetro de casas decimais', () => {
    assert.equal(formatReais(1561.1, 0), 'R$ 1.561')
    assert.equal(formatKwh(9858.123, 4), '9.858,1230 kWh')
  })
})
