<template>
  <header class="app-navbar" ref="navRef">
    <nav class="app-navbar-inner" aria-label="Navegação principal">
      <router-link class="app-navbar-brand" to="/Home">
        <img src="@/assets/logo-consorcio-lider-energy.png" alt="Líder Energy" />
      </router-link>

      <button
        class="app-navbar-toggle"
        type="button"
        :aria-expanded="mobileOpen ? 'true' : 'false'"
        aria-controls="app-navbar-menu"
        aria-label="Abrir menu de navegação"
        @click="mobileOpen = !mobileOpen"
      >
        <svg v-if="!mobileOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="4" y1="6" x2="20" y2="6" />
          <line x1="4" y1="12" x2="20" y2="12" />
          <line x1="4" y1="18" x2="20" y2="18" />
        </svg>
        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>

      <div id="app-navbar-menu" class="app-navbar-menu" :class="{ 'is-open': mobileOpen }">
        <ul class="app-navbar-links" v-if="isAuthenticated">
          <li>
            <router-link class="app-nav-link" to="/faturar" @click="closeAll">Faturar Usina</router-link>
          </li>

          <li class="app-nav-dropdown" :class="{ 'is-open': openDropdown === 'usina' }">
            <button
              type="button"
              class="app-nav-link app-nav-trigger"
              :class="{ 'router-link-active': isUsinaSection }"
              :aria-expanded="openDropdown === 'usina' ? 'true' : 'false'"
              aria-haspopup="true"
              @click="toggleDropdown('usina')"
            >
              Usinas
              <svg class="app-nav-caret" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </button>
            <ul class="app-nav-menu" v-show="openDropdown === 'usina'">
              <li><router-link class="app-nav-menu-item" to="/cadastro-usina" @click="closeAll">Cadastrar usina</router-link></li>
              <li><router-link class="app-nav-menu-item" to="/usinas" @click="closeAll">Lista de usinas</router-link></li>
            </ul>
          </li>

          <li class="app-nav-dropdown" :class="{ 'is-open': openDropdown === 'consumidor' }">
            <button
              type="button"
              class="app-nav-link app-nav-trigger"
              :class="{ 'router-link-active': isConsumidorSection }"
              :aria-expanded="openDropdown === 'consumidor' ? 'true' : 'false'"
              aria-haspopup="true"
              @click="toggleDropdown('consumidor')"
            >
              Consumidores
              <svg class="app-nav-caret" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </button>
            <ul class="app-nav-menu" v-show="openDropdown === 'consumidor'">
              <li><router-link class="app-nav-menu-item" to="/cadastro-consumidor" @click="closeAll">Cadastrar consumidor</router-link></li>
              <li><router-link class="app-nav-menu-item" to="/consumidores" @click="closeAll">Lista de consumidores</router-link></li>
            </ul>
          </li>

          <li>
            <router-link class="app-nav-link" to="/distribuicao" @click="closeAll">Distribuir Créditos</router-link>
          </li>
          <li>
            <router-link class="app-nav-link" to="/relatorio" @click="closeAll">Relatórios</router-link>
          </li>
        </ul>
        <ul class="app-navbar-links" v-else></ul>

        <ul class="app-navbar-links app-navbar-right">
          <li class="app-nav-dropdown" :class="{ 'is-open': openDropdown === 'user' }" v-if="isAuthenticated">
            <button
              type="button"
              class="app-nav-link app-nav-trigger"
              :aria-expanded="openDropdown === 'user' ? 'true' : 'false'"
              aria-haspopup="true"
              @click="toggleDropdown('user')"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
              <span>{{ user.name || 'Conta' }}</span>
              <svg class="app-nav-caret" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </button>
            <ul class="app-nav-menu app-nav-menu-end" v-show="openDropdown === 'user'">
              <li>
                <button type="button" class="app-nav-menu-item" @click="logout">Sair</button>
              </li>
            </ul>
          </li>

          <li v-if="!isAuthenticated">
            <router-link class="app-nav-link" to="/Login" @click="closeAll">Entrar</router-link>
          </li>
        </ul>
      </div>
    </nav>
  </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import {
  clearAuthSession,
  getAuthToken,
  isAuthenticated as hasActiveSession,
  isAuthorizationError,
  onAuthChange,
} from '@/utils/auth.js'

const baseURL = import.meta.env.VITE_API_URL

const route = useRoute()
const router = useRouter()

const navRef = ref(null)
const user = ref({})
const isAuthenticated = ref(false)
const openDropdown = ref(null)
const mobileOpen = ref(false)
let authUnsubscribe = null

const isUsinaSection = computed(() =>
  ['/cadastro-usina', '/usinas'].some(p => route.path.startsWith(p))
)
const isConsumidorSection = computed(() =>
  ['/cadastro-consumidor', '/consumidores'].some(p => route.path.startsWith(p))
)

function toggleDropdown(name) {
  openDropdown.value = openDropdown.value === name ? null : name
}

function closeAll() {
  openDropdown.value = null
  mobileOpen.value = false
}

function onDocumentClick(event) {
  if (navRef.value && !navRef.value.contains(event.target)) {
    openDropdown.value = null
  }
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    openDropdown.value = null
  }
}

async function fetchAuthenticatedUser() {
  try {
    const response = await axios.get(`${baseURL}/user`, {
      headers: {
        Authorization: `Bearer ${getAuthToken()}`,
      },
    })
    user.value = response.data
  } catch (error) {
    console.error('Erro ao buscar dados do usuário:', error)
    if (isAuthorizationError(error)) {
      clearAuthSession()
      isAuthenticated.value = false
      user.value = {}
    }
  }
}

async function syncAuthState() {
  isAuthenticated.value = hasActiveSession()

  if (isAuthenticated.value) {
    await fetchAuthenticatedUser()
    return
  }

  user.value = {}
}

function logout() {
  closeAll()
  clearAuthSession()
  isAuthenticated.value = false
  user.value = {}
  router.replace({ name: 'Login' })
}

onMounted(() => {
  syncAuthState()
  authUnsubscribe = onAuthChange(() => {
    syncAuthState()
  })
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  if (authUnsubscribe) {
    authUnsubscribe()
    authUnsubscribe = null
  }
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
.app-navbar {
  position: sticky;
  top: 0;
  z-index: 1030;
  /* anula o padding de 2rem do #app para o header ocupar a largura toda */
  margin: -2rem -2rem 0;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--color-mist);
}

.app-navbar-inner {
  max-width: var(--max-w-app);
  margin: 0 auto;
  padding: var(--space-2) var(--space-6);
  display: flex;
  align-items: center;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.app-navbar-brand {
  display: inline-flex;
  align-items: center;
  padding: var(--space-1) 0;
  border-radius: var(--radius-md);
}

.app-navbar-brand img {
  height: 40px;
  width: auto;
  display: block;
}

/* ---------- Toggle (mobile) ---------- */
.app-navbar-toggle {
  display: none;
  margin-left: auto;
  border: 1px solid var(--color-mist);
  background: var(--color-paper);
  color: var(--color-ink);
  border-radius: var(--radius-md);
  padding: var(--space-2);
  line-height: 0;
  cursor: pointer;
  transition: background-color var(--dur-hover) var(--ease-standard);
}

.app-navbar-toggle:hover {
  background: var(--color-linen);
}

/* ---------- Menu ---------- */
.app-navbar-menu {
  display: flex;
  align-items: center;
  flex: 1 1 auto;
  gap: var(--space-4);
  min-width: 0;
}

.app-navbar-links {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  list-style: none;
  margin: 0;
  padding: 0;
}

.app-navbar-right {
  margin-left: auto;
}

/* ---------- Links ---------- */
.app-nav-link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border: none;
  background: transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--color-graphite);
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard);
}

.app-nav-link:hover {
  color: var(--color-ink);
  background: rgba(243, 147, 37, 0.08);
}

.app-nav-link.router-link-active {
  background: rgba(243, 147, 37, 0.1);
  color: var(--color-primary-deep);
  font-weight: var(--fw-bold);
}

.app-nav-caret {
  transition: transform var(--dur-hover) var(--ease-standard);
}

.app-nav-dropdown.is-open .app-nav-caret {
  transform: rotate(180deg);
}

/* ---------- Dropdown ---------- */
.app-nav-dropdown {
  position: relative;
}

.app-nav-menu {
  position: absolute;
  top: calc(100% + var(--space-1));
  left: 0;
  min-width: 220px;
  margin: 0;
  padding: var(--space-2);
  list-style: none;
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
  z-index: 1040;
}

.app-nav-menu-end {
  left: auto;
  right: 0;
}

.app-nav-menu-item {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  color: var(--color-ink);
  text-decoration: none;
  cursor: pointer;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard);
}

.app-nav-menu-item:hover {
  background: var(--color-primary-soft);
  color: var(--color-primary-deep);
}

.app-nav-menu-item.router-link-active {
  color: var(--color-primary-deep);
  font-weight: var(--fw-bold);
}

/* ---------- Responsivo ---------- */
@media (max-width: 992px) {
  .app-navbar-toggle {
    display: inline-flex;
  }

  .app-navbar-menu {
    display: none;
    flex-direction: column;
    align-items: stretch;
    flex-basis: 100%;
    gap: var(--space-2);
    padding: var(--space-3) 0;
  }

  .app-navbar-menu.is-open {
    display: flex;
  }

  .app-navbar-links {
    flex-direction: column;
    align-items: stretch;
    gap: var(--space-1);
  }

  .app-navbar-right {
    margin-left: 0;
    border-top: 1px solid var(--color-mist);
    padding-top: var(--space-2);
  }

  .app-nav-link {
    width: 100%;
    justify-content: flex-start;
  }

  .app-nav-menu {
    position: static;
    box-shadow: none;
    border: none;
    background: var(--color-linen);
    margin-top: var(--space-1);
  }
}
</style>
