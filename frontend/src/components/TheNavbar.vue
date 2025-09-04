<template>
  <!-- Converted Bootstrap navbar to Tailwind with responsive design -->
  <nav class="navbar bg-gray-900 shadow-lg fixed top-0 left-0 right-0 z-50">
    <div class="container mx-auto px-4 flex items-center justify-between h-16">
      <!-- Logo -->
      <router-link to="/Home" class="flex items-center">
        <img src="/src/assets/logo-branco.png" alt="logo-branco" class="h-10 w-auto" />
      </router-link>

      <!-- Mobile menu button -->
      <button 
        @click="mobileMenuOpen = !mobileMenuOpen"
        class="lg:hidden text-white hover:text-primary-400 focus:outline-none focus:text-primary-400"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <!-- Desktop Navigation -->
      <div class="hidden lg:flex lg:items-center lg:space-x-6">
        <!-- Main Navigation Items -->
        <div v-if="isAuthenticated" class="flex items-center space-x-6">
          <router-link 
            to="/calculo-geracao" 
            class="nav-link-primary"
          >
            Faturar Usina
          </router-link>

          <!-- Usina Dropdown -->
          <div class="relative group">
            <router-link 
              to="/cadastro-usina" 
              class="nav-link-primary flex items-center"
            >
              Cadastrar Usina
              <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </router-link>
            <div class="dropdown-menu">
              <router-link to="/cadastro-usina" class="dropdown-item">Cadastrar Usina</router-link>
              <router-link to="/usinas" class="dropdown-item">Usinas</router-link>
            </div>
          </div>

          <!-- Consumidor Dropdown -->
          <div class="relative group">
            <router-link 
              to="/cadastro-consumidor" 
              class="nav-link-primary flex items-center"
            >
              Cadastrar Consumidor
              <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </router-link>
            <div class="dropdown-menu">
              <router-link to="/cadastro-consumidor" class="dropdown-item">Cadastrar Consumidor</router-link>
              <router-link to="/consumidores" class="dropdown-item">Consumidores</router-link>
            </div>
          </div>

          <router-link to="/distribuicao" class="nav-link-primary">Distribuir Créditos</router-link>
          <router-link to="/relatorio" class="nav-link-primary">Relatórios</router-link>
        </div>

        <!-- User Menu / Auth Links -->
        <div class="flex items-center space-x-4">
          <div v-if="isAuthenticated" class="relative group">
            <button class="nav-link-primary flex items-center">
              {{ user.name }}
              <svg class="ml-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div class="dropdown-menu right-0">
              <button @click="logout" class="dropdown-item w-full text-left">Sair</button>
            </div>
          </div>

          <div v-if="!isAuthenticated" class="flex items-center space-x-4">
            <router-link to="/Login" class="nav-link-primary">Login</router-link>
            <router-link to="/Register" class="nav-link-primary">Register</router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <div v-if="mobileMenuOpen" class="lg:hidden bg-gray-800 border-t border-gray-700">
      <div class="px-4 py-2 space-y-2">
        <div v-if="isAuthenticated">
          <router-link to="/calculo-geracao" class="mobile-nav-link">Faturar Usina</router-link>
          <router-link to="/cadastro-usina" class="mobile-nav-link">Cadastrar Usina</router-link>
          <router-link to="/usinas" class="mobile-nav-link pl-8">Usinas</router-link>
          <router-link to="/cadastro-consumidor" class="mobile-nav-link">Cadastrar Consumidor</router-link>
          <router-link to="/consumidores" class="mobile-nav-link pl-8">Consumidores</router-link>
          <router-link to="/distribuicao" class="mobile-nav-link">Distribuir Créditos</router-link>
          <router-link to="/relatorio" class="mobile-nav-link">Relatórios</router-link>
          <div class="border-t border-gray-700 pt-2 mt-2">
            <div class="text-white text-sm px-3 py-2">{{ user.name }}</div>
            <button @click="logout" class="mobile-nav-link text-red-400">Sair</button>
          </div>
        </div>
        <div v-if="!isAuthenticated">
          <router-link to="/Login" class="mobile-nav-link">Login</router-link>
          <router-link to="/Register" class="mobile-nav-link">Register</router-link>
        </div>
      </div>
    </div>
  </nav>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      user: {},
      isAuthenticated: false,
      mobileMenuOpen: false
    };
  },
  mounted() {
    this.isAuthenticated = !!localStorage.getItem('token');
    const baseURL = import.meta.env.VITE_API_URL;

    if (this.isAuthenticated) {
      axios.get(`${baseURL}/user`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('token')}`,
        },
      })
      .then(response => {
        this.user = response.data;
      })
      .catch(error => {
        console.error('Erro ao buscar dados do usuário:', error);
      });
    }
  },
  methods: {
    logout() {
      localStorage.removeItem('token');
      this.isAuthenticated = false;
      this.user = {};
      this.mobileMenuOpen = false;
      this.$router.push({ name: 'Login' }).then(() => {
        window.location.reload();
      });
    }
  },
};
</script>

<style scoped>
.nav-link-primary {
  @apply text-white font-medium px-3 py-2 rounded-md transition-colors duration-200 hover:text-primary-400 hover:bg-primary-500/10;
}

.dropdown-menu {
  @apply absolute top-full left-0 mt-1 w-48 bg-gray-800 rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50;
}

.dropdown-item {
  @apply block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-primary-500 transition-colors duration-200;
}

.mobile-nav-link {
  @apply block px-3 py-2 text-white hover:text-primary-400 hover:bg-gray-700 rounded-md transition-colors duration-200;
}
</style>
