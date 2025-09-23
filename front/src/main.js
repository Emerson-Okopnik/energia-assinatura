import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import axios from 'axios'
import {
  clearAuthSession,
  getAuthToken,
  isAuthorizationError,
} from './utils/auth.js'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'

const token = getAuthToken()
if (token) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

let isRedirectingAfterAuthError = false

axios.interceptors.response.use(
  response => response,
  error => {
    if (isAuthorizationError(error) && !isRedirectingAfterAuthError) {
      isRedirectingAfterAuthError = true
      clearAuthSession()

      const routeName = router.currentRoute.value?.name
      if (routeName !== 'Login') {
        router
          .push({ name: 'Login' })
          .catch(() => {})
          .finally(() => {
            isRedirectingAfterAuthError = false
          })
      } else {
        isRedirectingAfterAuthError = false
      }
    }

    return Promise.reject(error)
  }
)

const app = createApp(App)

app.use(router)

app.mount('#app')
