import axios from 'axios'

const AUTH_TOKEN_KEY = 'token'

export function getAuthToken() {
  return localStorage.getItem(AUTH_TOKEN_KEY)
}
export function isAuthenticated() {
  return !!getAuthToken()
}

export function setAuthSession(token) {
  localStorage.setItem(AUTH_TOKEN_KEY, token)
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

export function clearAuthSession() {
  localStorage.removeItem(AUTH_TOKEN_KEY)
  delete axios.defaults.headers.common['Authorization']
}

export function isAuthorizationError(error) {
  const status = error?.response?.status
  return status === 401 || status === 419
}