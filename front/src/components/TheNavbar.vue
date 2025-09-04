<template>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid px-4">
      <a class="navbar-brand" href="/Home"><img src="/src/assets/logo-branco.png" alt="logo-branco" width="180px"></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
        aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
          <!-- Itens visíveis apenas se autenticado -->
          <li class="nav-item" v-if="isAuthenticated">
            <router-link class="nav-link text-white route-principal" to="/calculo-geracao">Faturar Usina</router-link>
          </li>

          <li class="nav-item dropdown position-relative" v-if="isAuthenticated">
            <div class="nav-link dropdown-toggle text-white" @mouseover="dropdowns.usina = true"
              @mouseleave="checkMouseLeave('usina', $event)">
              <router-link class="text-white route-principal" to="/cadastro-usina">Cadastrar Usina</router-link>
              <ul class="dropdown-menu" :class="{ show: dropdowns.usina }" @mouseenter="dropdowns.usina = true"
                @mouseleave="dropdowns.usina = false">
                <li><router-link class="dropdown-item route-secundaria" to="/cadastro-usina">Cadastrar
                    Usina</router-link></li>
                <li><router-link class="dropdown-item route-secundaria" to="/usinas">Usinas</router-link></li>
              </ul>
            </div>
          </li>

          <li class="nav-item dropdown position-relative" v-if="isAuthenticated">
            <div class="nav-link dropdown-toggle text-white" @mouseover="dropdowns.consumidor = true"
              @mouseleave="checkMouseLeave('consumidor', $event)">
              <router-link class="text-white route-principal" to="/cadastro-consumidor">Cadastrar
                Consumidor</router-link>
              <ul class="dropdown-menu" :class="{ show: dropdowns.consumidor }"
                @mouseenter="dropdowns.consumidor = true" @mouseleave="dropdowns.consumidor = false">
                <li><router-link class="dropdown-item route-secundaria" to="/cadastro-consumidor">Cadastrar
                    Consumidor</router-link></li>
                <li><router-link class="dropdown-item route-secundaria" to="/consumidores">Consumidores</router-link>
                </li>
              </ul>
            </div>
          </li>

          <li class="nav-item" v-if="isAuthenticated">
            <router-link class="nav-link text-white route-principal" to="/distribuicao">Distribuir
              Créditos</router-link>
          </li>
          <li class="nav-item" v-if="isAuthenticated">
            <router-link class="nav-link text-white route-principal" to="/relatorio">Relatórios</router-link>
          </li>
        </ul>

        <ul class="navbar-nav align-items-center">
          <!-- Se autenticado, mostra nome e botão de sair -->
          <li class="nav-item dropdown" v-if="isAuthenticated">
            <a class="nav-link dropdown-toggle text-white route-principal" href="#" id="userDropdown" role="button"
              data-bs-toggle="dropdown" aria-expanded="false">
             <span class="navbar-text text-white">{{ user.name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
              <li><button class="dropdown-item route-secundaria" @click="logout">Sair</button></li>
            </ul>
          </li>

          <!-- Se NÃO autenticado, mostra Login e Register -->
          <li class="nav-item" v-if="!isAuthenticated">
            <router-link class="nav-link text-white route-principal" to="/Login">Login</router-link>
          </li>
          <li class="nav-item" v-if="!isAuthenticated">
            <router-link class="nav-link text-white route-principal" to="/Register">Register</router-link>
          </li>
        </ul>

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
      dropdowns: {
        usina: false,
        consumidor: false,
      }
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
  async created() {
    if (this.isAuthenticated) {
      try {
        const response = await axios.get(`${baseURL}/user`, {
          headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`,
          },
        });
        this.user = response.data;
      } catch (error) {
        console.error('Erro ao buscar dados do usuário:', error);
      }
    }
  },
  methods: {
    openDropdown(menu) {
      this.dropdowns[menu] = true;
    },
    closeDropdown(menu) {
      this.dropdowns[menu] = false;
    },
    logout() {
      localStorage.removeItem('token');
      this.isAuthenticated = false;
      this.user = 'Usuário';
      this.$router.push({ name: 'Login' }).then(() => {
        window.location.reload();
      });
    },
    checkMouseLeave(menu, event) {
      const related = event.relatedTarget;
      if (!event.currentTarget.contains(related)) {
        this.dropdowns[menu] = false;
      }
    }
  },
};
</script>

<style scoped>
/* --- NAVBAR CLEAN DARK STYLE COM HOVER LARANJA --- */

.navbar {
  background-color: #1f1f1f !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}

.navbar-brand img {
  height: 40px;
  width: auto;
}

/* Link principal */
.nav-link.route-principal {
  color: #ffffff;
  font-weight: 500;
  padding: 8px 16px;
  transition: background-color 0.3s, color 0.3s;
  border-radius: 6px;
}

.nav-link.route-principal:hover {
  color: #f28c1f !important;
  background-color: rgba(242, 140, 31, 0.1);
  border-radius: 8px;
}

/* Dropdown */
.dropdown-menu {
  background-color: #2b2b2b;
  border-radius: 8px;
  padding: 0.5rem 0;
  border: none;
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
  min-width: 180px;
}

.dropdown-item.route-secundaria {
  color: #f1f1f1;
  padding: 8px 16px;
  font-size: 0.9rem;
  transition: background-color 0.2s ease, color 0.2s;
}

.dropdown-item.route-secundaria:hover {
  background-color: #f28c1f;
  color: white;
}

/* Hover dropdown trigger */
.navbar-nav .dropdown:hover > .dropdown-menu {
  display: block;
}

/* Toggler ícone responsivo */
.navbar-toggler {
  border: none;
  color: #fff;
}

.navbar-toggler:focus {
  box-shadow: none;
}

/* Nome do usuário */
.navbar-text {
  font-weight: 500;
  color: #ffffff;
  margin-right: 8px;
}

a {
  text-decoration: none;
}

/* Estilo responsivo */
@media (max-width: 992px) {
  .navbar-nav .nav-item {
    margin-bottom: 0.5rem;
  }

  .dropdown-menu {
    position: static;
    box-shadow: none;
    border-radius: 0;
  }

  .dropdown-menu.show {
    display: block;
  }
}
</style>
