import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { valorEmReais, partesFormula } from '../src/utils/detalhamentoFatura.js'

describe('valorEmReais', () => {
  test('kwh × tarifa', () => assert.equal(valorEmReais(124, 0.51), 63.24))
  test('tarifa null → null', () => assert.equal(valorEmReais(124, null), null))
  test('tarifa NaN → null', () => assert.equal(valorEmReais(124, NaN), null))
  test('0 kwh → 0', () => assert.equal(valorEmReais(0, 0.51), 0))
})

describe('partesFormula', () => {
  const base = {
    valor_fixo_reais: 3894.36,
    valor_variavel_reais: 2017.56,
    credito_reais: 672.69,
    cuo_reais: 1020.01,
    receita_expiracao_reais: 0,
  }

  test('sem expiração: 4 partes (fixo, injetado, crédito, -cuo)', () => {
    const p = partesFormula(base)
    assert.deepEqual(
      p.map((x) => x.label),
      ['Fixo', 'Injetado', 'Crédito', 'CUO']
    )
    assert.equal(p[3].valor, -1020.01) // CUO negativo
  })

  test('com expiração > 0: inclui "Crédito expirado" no fim', () => {
    const p = partesFormula({ ...base, receita_expiracao_reais: 63.24 })
    assert.equal(p.length, 5)
    assert.equal(p[4].label, 'Crédito expirado')
    assert.equal(p[4].valor, 63.24)
  })

  test('expiração 0 não entra', () => {
    const p = partesFormula(base)
    assert.equal(p.some((x) => x.label === 'Crédito expirado'), false)
  })
})
