import axios from 'axios'

const AUTH_TOKEN_KEY = 'token'
const AUTH_REFRESH_TOKEN_KEY = 'refreshToken'
const AUTH_USER_KEY = 'user'
const AUTH_EVENT = 'auth:changed'

function resolveStorage(name) {
  const storage = globalThis?.[name]
  if (!storage) {
    return null
  }
  const hasMethods = typeof storage.getItem === 'function'
    && typeof storage.setItem === 'function'
    && typeof storage.removeItem === 'function'
  return hasMethods ? storage : null
}

function decodeBase64(value) {
  if (typeof atob === 'function') {
    return atob(value)
  }
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(value, 'base64').toString('binary')
  }
  return null
}

export function getAuthToken() {
  const storage = resolveStorage('localStorage')
  return storage ? storage.getItem(AUTH_TOKEN_KEY) : null
}

export function getRefreshToken() {
  const storage = resolveStorage('localStorage')
  return storage ? storage.getItem(AUTH_REFRESH_TOKEN_KEY) : null
}

export function getAuthUser() {
  const storage = resolveStorage('localStorage')
  const raw = storage ? storage.getItem(AUTH_USER_KEY) : null
  if (!raw) {
    return null
  }
  try {
    return JSON.parse(raw)
  } catch (error) {
    return null
  }
}

function decodeJwtPayload(token) {
  if (!token || typeof token !== 'string') {
    return null
  }
  const parts = token.split('.')
  if (parts.length !== 3) {
    return null
  }
  try {
    let payload = parts[1].replace(/-/g, '+').replace(/_/g, '/')
    const padLength = payload.length % 4
    if (padLength) {
      payload += '='.repeat(4 - padLength)
    }
    const decoded = decodeBase64(payload)
    if (!decoded) {
      return null
    }
    return JSON.parse(decoded)
  } catch (error) {
    return null
  }
}

export function getTokenExpiry(token = getAuthToken()) {
  const payload = decodeJwtPayload(token)
  if (!payload?.exp) {
    return null
  }
  return payload.exp * 1000
}

export function isTokenExpired(token = getAuthToken()) {
  const expiry = getTokenExpiry(token)
  if (!expiry) {
    return false
  }
  return Date.now() >= expiry - 5000
}

export function isAuthenticated() {
  const token = getAuthToken()
  return !!token && !isTokenExpired(token)
}

export function setAuthSession(token, options = {}) {
  if (!token) {
    return
  }
  const storage = resolveStorage('localStorage')
  if (storage) {
    storage.setItem(AUTH_TOKEN_KEY, token)
  }
  if (options.refreshToken) {
    storage?.setItem(AUTH_REFRESH_TOKEN_KEY, options.refreshToken)
  }
  if (options.user) {
    storage?.setItem(AUTH_USER_KEY, JSON.stringify(options.user))
  }
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
  notifyAuthChange()
}

export function clearAuthSession() {
  const storage = resolveStorage('localStorage')
  const session = resolveStorage('sessionStorage')
  storage?.removeItem(AUTH_TOKEN_KEY)
  storage?.removeItem(AUTH_REFRESH_TOKEN_KEY)
  storage?.removeItem(AUTH_USER_KEY)
  storage?.removeItem('accessToken')
  storage?.removeItem('refresh_token')
  session?.removeItem(AUTH_TOKEN_KEY)
  session?.removeItem(AUTH_REFRESH_TOKEN_KEY)
  session?.removeItem(AUTH_USER_KEY)
  session?.removeItem('accessToken')
  session?.removeItem('refresh_token')
  delete axios.defaults.headers.common['Authorization']
  notifyAuthChange()
}

export function notifyAuthChange() {
  if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
    return
  }
  window.dispatchEvent(new CustomEvent(AUTH_EVENT, {
    detail: {
      isAuthenticated: isAuthenticated(),
      token: getAuthToken(),
    },
  }))
}

export function onAuthChange(handler) {
  if (typeof window === 'undefined') {
    return () => {}
  }
  const listener = event => handler(event.detail)
  window.addEventListener(AUTH_EVENT, listener)
  return () => window.removeEventListener(AUTH_EVENT, listener)
}

export function isAuthorizationError(error) {
  const status = error?.response?.status
  return status === 401 || status === 403 || status === 419
}
