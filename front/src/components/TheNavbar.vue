<template>
  <div v-if="isAuthenticated" class="navigation-shell">
    <header class="topbar">
      <button
        type="button"
        class="menu-toggle"
        aria-label="Abrir menu lateral"
        @click="toggleSidebar"
      >
        <span></span>
        <span></span>
        <span></span>
      </button>

      <router-link to="/Home" class="brand">
        <img src="/src/assets/logo-branco.png" alt="Logo Lider Energy" />
      </router-link>

      <div class="topbar-actions">
        <span class="user-name">{{ user.name || 'Usuario' }}</span>
        <button type="button" class="logout-btn" @click="logout">Sair</button>
      </div>
    </header>

    <aside class="sidebar" :class="{ open: sidebarOpen }">
      <nav class="sidebar-nav">
        <router-link
          v-for="item in menuItems"
          :key="item.to"
          :to="item.to"
          class="sidebar-link"
          @click="closeSidebar"
        >
          {{ item.label }}
        </router-link>
      </nav>
    </aside>

    <button
      v-if="sidebarOpen"
      type="button"
      class="sidebar-overlay"
      aria-label="Fechar menu lateral"
      @click="closeSidebar"
    />
  </div>
</template>

<script>
import axios from 'axios';
import {
  clearAuthSession,
  getAuthToken,
  isAuthenticated as hasActiveSession,
  isAuthorizationError,
  onAuthChange
} from '@/utils/auth.js';

const baseURL = import.meta.env.VITE_API_URL;
const DESKTOP_BREAKPOINT = 992;

export default {
  data() {
    return {
      user: {},
      isAuthenticated: false,
      authUnsubscribe: null,
      sidebarOpen: false,
      menuItems: [
        { to: '/calculo-geracao', label: 'Faturar Usina' },
        { to: '/cadastro-usina', label: 'Cadastrar Usina' },
        { to: '/usinas', label: 'Listagem de Usinas' },
        { to: '/cadastro-consumidor', label: 'Cadastrar Consumidor' },
        { to: '/consumidores', label: 'Listagem de Consumidores' },
        { to: '/distribuicao', label: 'Distribuir Creditos' },
        { to: '/relatorio', label: 'Relatorios' }
      ]
    };
  },
  async created() {
    await this.syncAuthState();
  },
  mounted() {
    this.handleResize();
    window.addEventListener('resize', this.handleResize);
    this.authUnsubscribe = onAuthChange(() => {
      this.syncAuthState();
    });
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.handleResize);
    if (this.authUnsubscribe) {
      this.authUnsubscribe();
      this.authUnsubscribe = null;
    }
  },
  watch: {
    $route() {
      this.closeSidebar();
    }
  },
  methods: {
    async syncAuthState() {
      this.isAuthenticated = hasActiveSession();

      if (this.isAuthenticated) {
        await this.fetchAuthenticatedUser();
        this.handleResize();
        return;
      }

      this.user = {};
      this.sidebarOpen = false;
    },
    async fetchAuthenticatedUser() {
      try {
        const response = await axios.get(`${baseURL}/user`, {
          headers: {
            Authorization: `Bearer ${getAuthToken()}`
          }
        });
        this.user = response.data;
      } catch (error) {
        console.error('Erro ao buscar dados do usuario:', error);
        if (isAuthorizationError(error)) {
          clearAuthSession();
          this.isAuthenticated = false;
          this.user = {};
          this.sidebarOpen = false;
        }
      }
    },
    handleResize() {
      if (window.innerWidth >= DESKTOP_BREAKPOINT) {
        this.sidebarOpen = true;
        return;
      }
      this.sidebarOpen = false;
    },
    toggleSidebar() {
      if (window.innerWidth >= DESKTOP_BREAKPOINT) return;
      this.sidebarOpen = !this.sidebarOpen;
    },
    closeSidebar() {
      if (window.innerWidth < DESKTOP_BREAKPOINT) {
        this.sidebarOpen = false;
      }
    },
    logout() {
      clearAuthSession();
      this.isAuthenticated = false;
      this.user = {};
      this.sidebarOpen = false;
      this.$router.replace({ name: 'Login' });
    }
  }
};
</script>

<style scoped>
.navigation-shell {
  position: relative;
  z-index: 1200;
  --topbar-bg-start: #101010;
  --topbar-bg-end: #1a1a1a;
  --topbar-border: rgba(242, 140, 31, 0.35);
  --sidebar-bg: #0b0b0b;
  --sidebar-border: #262626;
  --sidebar-link: #f3f4f6;
  --sidebar-hover-bg: rgba(242, 140, 31, 0.22);
  --sidebar-active-bg: #f28c1f;
  --sidebar-active-text: #111827;
  --text-strong: #f8fafc;
}

.topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 0 16px;
  background: linear-gradient(90deg, var(--topbar-bg-start), var(--topbar-bg-end));
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.3);
  border-bottom: 1px solid var(--topbar-border);
  z-index: 1202;
}

.menu-toggle {
  width: 40px;
  height: 40px;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
}

.menu-toggle span {
  width: 18px;
  height: 2px;
  background-color: #f8fafc;
  border-radius: 10px;
}

.brand {
  text-decoration: none;
  flex-shrink: 0;
}

.brand img {
  height: 32px;
  width: auto;
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-name {
  color: var(--text-strong);
  font-size: 0.9rem;
  font-weight: 600;
  white-space: nowrap;
}

.logout-btn {
  border: 0;
  border-radius: 8px;
  padding: 7px 12px;
  background-color: #f28c1f;
  color: #111827;
  font-weight: 600;
  font-size: 0.86rem;
  transition: background-color 0.2s ease;
}

.logout-btn:hover {
  background-color: #d97706;
}

.sidebar {
  position: fixed;
  top: 64px;
  left: 0;
  width: 260px;
  height: calc(100vh - 64px);
  background: linear-gradient(180deg, var(--sidebar-bg), #171717);
  border-right: 1px solid var(--sidebar-border);
  padding: 14px 10px 18px;
  overflow-y: auto;
  transform: translateX(-100%);
  transition: transform 0.25s ease;
  z-index: 1201;
}

.sidebar.open {
  transform: translateX(0);
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 228px;
  margin: 0 auto;
}

.sidebar-link {
  text-decoration: none;
  padding: 10px 12px;
  border-radius: 8px;
  color: var(--sidebar-link);
  font-size: 0.93rem;
  font-weight: 500;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.sidebar-link:hover {
  background-color: var(--sidebar-hover-bg);
  color: #ffffff;
}

.sidebar-link.router-link-active {
  background-color: var(--sidebar-active-bg);
  color: var(--sidebar-active-text);
  font-weight: 700;
}

.sidebar-overlay {
  position: fixed;
  inset: 64px 0 0 0;
  border: 0;
  background-color: rgba(15, 23, 42, 0.45);
  z-index: 1200;
}

@media (min-width: 992px) {
  .menu-toggle {
    display: none;
  }

  .topbar {
    padding-left: 22px;
    padding-right: 18px;
  }

  .sidebar {
    transform: translateX(0);
  }

  .sidebar-overlay {
    display: none;
  }
}

@media (max-width: 991.98px) {
  .user-name {
    display: none;
  }
}
</style>
