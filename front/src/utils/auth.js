import axios from 'axios'

const AUTH_TOKEN_KEY = 'token'
const AUTH_REFRESH_TOKEN_KEY = 'refreshToken'
const AUTH_USER_KEY = 'user'
const AUTH_EVENT = 'auth:changed'

export function getAuthToken() {
  return localStorage.getItem(AUTH_TOKEN_KEY)
}

export function getRefreshToken() {
  return localStorage.getItem(AUTH_REFRESH_TOKEN_KEY)
}

export function getAuthUser() {
  const raw = localStorage.getItem(AUTH_USER_KEY)
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
    const decoded = atob(payload)
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
  localStorage.setItem(AUTH_TOKEN_KEY, token)
  if (options.refreshToken) {
    localStorage.setItem(AUTH_REFRESH_TOKEN_KEY, options.refreshToken)
  }
  if (options.user) {
    localStorage.setItem(AUTH_USER_KEY, JSON.stringify(options.user))
  }
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
  notifyAuthChange()
}

export function clearAuthSession() {
  localStorage.removeItem(AUTH_TOKEN_KEY)
  localStorage.removeItem(AUTH_REFRESH_TOKEN_KEY)
  localStorage.removeItem(AUTH_USER_KEY)
  localStorage.removeItem('accessToken')
  localStorage.removeItem('refresh_token')
  sessionStorage.removeItem(AUTH_TOKEN_KEY)
  sessionStorage.removeItem(AUTH_REFRESH_TOKEN_KEY)
  sessionStorage.removeItem(AUTH_USER_KEY)
  sessionStorage.removeItem('accessToken')
  sessionStorage.removeItem('refresh_token')
  delete axios.defaults.headers.common['Authorization']
  notifyAuthChange()
}

export function notifyAuthChange() {
  window.dispatchEvent(new CustomEvent(AUTH_EVENT, {
    detail: {
      isAuthenticated: isAuthenticated(),
      token: getAuthToken(),
    },
  }))
}

export function onAuthChange(handler) {
  const listener = event => handler(event.detail)
  window.addEventListener(AUTH_EVENT, listener)
  return () => window.removeEventListener(AUTH_EVENT, listener)
}

export function isAuthorizationError(error) {
  const status = error?.response?.status
  return status === 401 || status === 403 || status === 419
}
