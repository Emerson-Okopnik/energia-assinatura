<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import {
  clearAuthSession,
  getAuthToken,
  isAuthenticated as hasActiveSession,
  isAuthorizationError,
  onAuthChange,
} from '@/utils/auth.js'
import AppSidebar from './AppSidebar.vue'

const baseURL = import.meta.env.VITE_API_URL

const route = useRoute()
const router = useRouter()

const mobileOpen = ref(false)
const user = ref({})
const isAuthenticated = ref(false)
const contaMenuAberto = ref(false)
const topbarRef = ref(null)
let authUnsubscribe = null

const tituloPagina = computed(
  () => route.meta?.titulo || route.name || '',
)

const nomeOperador = computed(() => user.value?.name || 'Operador')

const iniciais = computed(() => {
  const partes = String(nomeOperador.value).trim().split(/\s+/)
  const primeira = partes[0]?.[0] || ''
  const ultima = partes.length > 1 ? partes[partes.length - 1][0] : ''
  return (primeira + ultima).toUpperCase() || 'OP'
})

function alternarDrawer() {
  mobileOpen.value = !mobileOpen.value
}

function alternarConta() {
  contaMenuAberto.value = !contaMenuAberto.value
}

function logout() {
  contaMenuAberto.value = false
  clearAuthSession()
  isAuthenticated.value = false
  user.value = {}
  router.replace({ name: 'Login' })
}

async function fetchAuthenticatedUser() {
  try {
    const response = await axios.get(`${baseURL}/user`, {
      headers: { Authorization: `Bearer ${getAuthToken()}` },
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

function onKeydown(event) {
  if (event.key === 'Escape') {
    contaMenuAberto.value = false
  }
}

function onDocumentClick(event) {
  if (
    contaMenuAberto.value &&
    topbarRef.value &&
    !topbarRef.value.contains(event.target)
  ) {
    contaMenuAberto.value = false
  }
}

// Fecha o drawer ao navegar entre rotas.
watch(
  () => route.fullPath,
  () => {
    mobileOpen.value = false
  },
)

onMounted(() => {
  syncAuthState()
  authUnsubscribe = onAuthChange(() => syncAuthState())
  document.addEventListener('keydown', onKeydown)
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  if (authUnsubscribe) {
    authUnsubscribe()
    authUnsubscribe = null
  }
  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <div class="app-shell">
    <AppSidebar v-model:mobileOpen="mobileOpen" :operador="user" />

    <main class="app-main">
      <header ref="topbarRef" class="app-topbar">
        <button
          type="button"
          class="topbar-burger"
          aria-label="Abrir menu de navegação"
          :aria-expanded="mobileOpen ? 'true' : 'false'"
          @click="alternarDrawer"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="4" y1="6" x2="20" y2="6"/>
            <line x1="4" y1="12" x2="20" y2="12"/>
            <line x1="4" y1="18" x2="20" y2="18"/>
          </svg>
        </button>

        <h1 class="topbar-title">{{ tituloPagina }}</h1>

        <div v-if="isAuthenticated" class="topbar-account" :class="{ 'is-open': contaMenuAberto }">
          <span class="topbar-greeting">
            Bem-vindo de volta, <strong>{{ nomeOperador }}</strong>
          </span>
          <button
            type="button"
            class="topbar-avatar-btn"
            :aria-expanded="contaMenuAberto ? 'true' : 'false'"
            aria-haspopup="true"
            aria-label="Menu da conta"
            @click.stop="alternarConta"
          >
            <span class="topbar-avatar" aria-hidden="true">{{ iniciais }}</span>
          </button>
          <ul v-show="contaMenuAberto" class="topbar-menu" role="menu">
            <li role="none">
              <button type="button" class="topbar-menu-item" role="menuitem" @click="logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Sair
              </button>
            </li>
          </ul>
        </div>
      </header>

      <div class="app-content">
        <slot></slot>
      </div>
    </main>
  </div>
</template>

<style scoped>
.app-shell {
  /* Anula o padding de 2rem do #app para ocupar o viewport todo. */
  margin: -2rem;
  display: grid;
  grid-template-columns: 240px 1fr;
  min-height: 100vh;
  background: var(--color-linen);
}

.app-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

/* ---------- Topbar ---------- */
.app-topbar {
  position: sticky;
  top: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-3) var(--space-7);
  background: rgba(255, 255, 255, 0.75);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--color-mist);
}

.topbar-burger {
  display: none;
  border: 1px solid var(--color-mist);
  background: var(--color-paper);
  color: var(--color-ink);
  border-radius: var(--radius-md);
  padding: var(--space-2);
  line-height: 0;
  cursor: pointer;
  transition: background-color var(--dur-hover) var(--ease-standard);
}

.topbar-burger:hover {
  background: var(--color-linen);
}

.topbar-title {
  margin: 0;
  font-family: var(--font-display);
  font-weight: var(--fw-extra);
  font-size: var(--fs-h4);
  letter-spacing: -0.01em;
  color: var(--color-ink);
}

.topbar-account {
  position: relative;
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.topbar-greeting {
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

.topbar-greeting strong {
  color: var(--color-ink);
  font-weight: var(--fw-bold);
}

.topbar-avatar-btn {
  border: none;
  background: transparent;
  padding: 0;
  cursor: pointer;
  border-radius: 50%;
}

.topbar-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--grad-sun);
  color: var(--color-paper);
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
}

.topbar-menu {
  position: absolute;
  top: calc(100% + var(--space-2));
  right: 0;
  min-width: 180px;
  margin: 0;
  padding: var(--space-2);
  list-style: none;
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
  z-index: 30;
}

.topbar-menu-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--color-ink);
  cursor: pointer;
  text-align: left;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard);
}

.topbar-menu-item:hover {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

/* ---------- Conteúdo ---------- */
.app-content {
  flex: 1 1 auto;
  padding: 28px 36px;
}

/* ---------- Responsivo ---------- */
@media (max-width: 992px) {
  .app-shell {
    grid-template-columns: 1fr;
  }

  .topbar-burger {
    display: inline-flex;
  }

  .topbar-greeting {
    display: none;
  }

  .app-content {
    padding: var(--space-5) var(--space-4);
  }
}
</style>
