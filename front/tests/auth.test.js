import axios from 'axios'
import assert from 'node:assert/strict'
import { afterEach, beforeEach, describe, it } from 'node:test'
import {
  clearAuthSession,
  getAuthToken,
  isAuthenticated,
  isAuthorizationError,
  setAuthSession,
} from '../src/utils/auth.js'

function createMemoryStorage() {
  const store = new Map()
  return {
    getItem(key) {
      return store.has(key) ? store.get(key) : null
    },
    setItem(key, value) {
      store.set(key, String(value))
    },
    removeItem(key) {
      store.delete(key)
    },
    clear() {
      store.clear()
    },
  }
}

describe('auth utils', () => {
  let originalLocalStorage
  let storage
  let originalCommonHeaders

  beforeEach(() => {
    storage = createMemoryStorage()
    originalLocalStorage = globalThis.localStorage
    globalThis.localStorage = storage
    originalCommonHeaders = axios.defaults.headers.common
    axios.defaults.headers.common = {}
  })

  afterEach(() => {
    globalThis.localStorage = originalLocalStorage
    axios.defaults.headers.common = originalCommonHeaders
  })

  it('persists token and header when creating a session', () => {
    setAuthSession('abc123')

    assert.equal(getAuthToken(), 'abc123')
    assert.equal(axios.defaults.headers.common.Authorization, 'Bearer abc123')
    assert.equal(storage.getItem('token'), 'abc123')
  })

  it('clears storage and headers when destroying a session', () => {
    storage.setItem('token', 'abc123')
    axios.defaults.headers.common.Authorization = 'Bearer abc123'

    clearAuthSession()

    assert.equal(getAuthToken(), null)
    assert.ok(!('Authorization' in axios.defaults.headers.common))
    assert.equal(storage.getItem('token'), null)
  })

  it('reports authentication state based on token presence', () => {
    assert.equal(isAuthenticated(), false)

    storage.setItem('token', 'xyz')

    assert.equal(isAuthenticated(), true)
  })

  it('detects API authorization errors', () => {
    const unauthorized = { response: { status: 401 } }
    const expired = { response: { status: 419 } }
    const other = { response: { status: 500 } }

    assert.equal(isAuthorizationError(unauthorized), true)
    assert.equal(isAuthorizationError(expired), true)
    assert.equal(isAuthorizationError(other), false)
    assert.equal(isAuthorizationError(new Error('network')), false)
  })
})