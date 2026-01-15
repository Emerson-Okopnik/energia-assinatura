import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import axios from 'axios'
import {
  clearAuthSession,
  getAuthToken,
  getTokenExpiry,
  isAuthorizationError,
  isTokenExpired,
  onAuthChange,
} from './utils/auth.js'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'

const token = getAuthToken()
if (token && isTokenExpired(token)) {
  clearAuthSession()
} else if (token) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

let isRedirectingAfterAuthError = false
let tokenExpiryTimer = null

function handleAuthFailure() {
  if (isRedirectingAfterAuthError) {
    return
  }
  isRedirectingAfterAuthError = true
  clearAuthSession()

  const routeName = router.currentRoute.value?.name
  if (routeName !== 'Login') {
    router
      .replace({ name: 'Login' })
      .catch(() => {})
      .finally(() => {
        isRedirectingAfterAuthError = false
      })
  } else {
    isRedirectingAfterAuthError = false
  }
}

function scheduleTokenExpiry(tokenValue) {
  if (tokenExpiryTimer) {
    clearTimeout(tokenExpiryTimer)
    tokenExpiryTimer = null
  }
  if (!tokenValue) {
    return
  }
  const expiry = getTokenExpiry(tokenValue)
  if (!expiry) {
    return
  }
  const delay = expiry - Date.now()
  if (delay <= 0) {
    handleAuthFailure()
    return
  }
  tokenExpiryTimer = setTimeout(() => {
    handleAuthFailure()
  }, delay)
}

scheduleTokenExpiry(token)

onAuthChange(detail => {
  scheduleTokenExpiry(detail?.token)
})

axios.interceptors.request.use(
  config => {
    const currentToken = getAuthToken()
    if (currentToken && isTokenExpired(currentToken)) {
      handleAuthFailure()
      return Promise.reject(new Error('Token expirado'))
    }
    return config
  }
)

axios.interceptors.response.use(
  response => response,
  error => {
    if (isAuthorizationError(error)) {
      handleAuthFailure()
    }

    return Promise.reject(error)
  }
)

const app = createApp(App)

app.use(router)

app.mount('#app')
