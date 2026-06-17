import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { rotuloDiferenca, linhaConta } from '../src/utils/auditoria.js'

describe('rotuloDiferenca (pago × atual)', () => {
  test('pago < atual => a_menos', () => {
    const r = rotuloDiferenca(1058.75, 2446.29)
    assert.equal(r.tipo, 'a_menos'); assert.ok(Math.abs(r.valor - 1387.54) < 0.01)
  })
  test('pago > atual => a_mais', () => {
    assert.equal(rotuloDiferenca(500, 400).tipo, 'a_mais')
  })
  test('iguais => igual', () => { assert.equal(rotuloDiferenca(100, 100).tipo, 'igual') })
  test('atual null => inconclusivo', () => { assert.equal(rotuloDiferenca(100, null).tipo, 'inconclusivo') })
})

describe('linhaConta', () => {
  test('com expiração inclui o termo', () => {
    const s = linhaConta({ fixo: 2129.54, injetado: 1575.16, credito: 0, cuo: 2645.95, credito_expirado: 1387.54, valor_final: 2446.29 })
    assert.ok(s.includes('Crédito expirado'))
    assert.ok(s.includes('= R$'))
  })
  test('sem expiração não inclui o termo', () => {
    const s = linhaConta({ fixo: 100, injetado: 50, credito: 0, cuo: 20, credito_expirado: 0, valor_final: 130 })
    assert.ok(!s.includes('Crédito expirado'))
  })
})
