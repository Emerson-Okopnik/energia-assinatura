import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { consumoValido, faturaValida } from '../src/utils/validacaoLancamento.js'

describe('consumoValido (obrigatório informar; 0 é válido)', () => {
  test('0 é válido', () => assert.equal(consumoValido(0), true))
  test('positivo é válido', () => assert.equal(consumoValido(134), true))
  test('null é inválido', () => assert.equal(consumoValido(null), false))
  test('NaN é inválido', () => assert.equal(consumoValido(NaN), false))
})

describe('faturaValida (obrigatória e > 0)', () => {
  test('positivo é válido', () => assert.equal(faturaValida(98.77), true))
  test('0 é inválido', () => assert.equal(faturaValida(0), false))
  test('negativo é inválido', () => assert.equal(faturaValida(-5), false))
  test('null é inválido', () => assert.equal(faturaValida(null), false))
  test('NaN é inválido', () => assert.equal(faturaValida(NaN), false))
})
