import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { parseDecimalPtBr, formatDecimalPtBr } from '../src/utils/numberFormat.js'

describe('parseDecimalPtBr', () => {
  test('converte "1.234,56" em 1234.56', () => {
    assert.equal(parseDecimalPtBr('1.234,56'), 1234.56)
  })

  test('string vazia retorna null (nunca 0 automático)', () => {
    assert.equal(parseDecimalPtBr(''), null)
    assert.equal(parseDecimalPtBr('   '), null)
  })

  test('null e undefined retornam null', () => {
    assert.equal(parseDecimalPtBr(null), null)
    assert.equal(parseDecimalPtBr(undefined), null)
  })

  test('negativo com vírgula: "-10,5" -> -10.5', () => {
    assert.equal(parseDecimalPtBr('-10,5'), -10.5)
  })

  test('inteiro simples: "42" -> 42', () => {
    assert.equal(parseDecimalPtBr('42'), 42)
  })

  test('somente vírgula decimal: "0,75" -> 0.75', () => {
    assert.equal(parseDecimalPtBr('0,75'), 0.75)
  })

  test('milhares múltiplos: "1.234.567,89" -> 1234567.89', () => {
    assert.equal(parseDecimalPtBr('1.234.567,89'), 1234567.89)
  })

  test('ignora caracteres não numéricos (ex.: "R$ 1.500,00")', () => {
    assert.equal(parseDecimalPtBr('R$ 1.500,00'), 1500)
  })

  test('entrada inválida retorna null', () => {
    assert.equal(parseDecimalPtBr('abc'), null)
    assert.equal(parseDecimalPtBr('-'), null)
  })
})

describe('formatDecimalPtBr', () => {
  test('formata 1234.56 como "1.234,56"', () => {
    assert.equal(formatDecimalPtBr(1234.56), '1.234,56')
  })

  test('null/undefined/NaN viram string vazia', () => {
    assert.equal(formatDecimalPtBr(null), '')
    assert.equal(formatDecimalPtBr(undefined), '')
    assert.equal(formatDecimalPtBr(NaN), '')
  })

  test('negativo: -10.5 -> "-10,50"', () => {
    assert.equal(formatDecimalPtBr(-10.5), '-10,50')
  })

  test('casas decimais configuráveis', () => {
    assert.equal(formatDecimalPtBr(1234.5678, 3), '1.234,568')
    assert.equal(formatDecimalPtBr(1000, 0), '1.000')
  })

  test('ida e volta: parse(format(x)) === x', () => {
    const valores = [0, 0.5, -10.5, 1234.56, 987654.32]
    for (const valor of valores) {
      assert.equal(parseDecimalPtBr(formatDecimalPtBr(valor)), valor)
    }
  })
})
