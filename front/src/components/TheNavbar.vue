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

  if (this.isAuthenticated) {
    axios.get('http://localhost:8000/api/user', {
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
        const response = await axios.get('http://localhost:8000/api/user', {
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
.navbar {
  margin-bottom: 20px;
  white-space: nowrap;
}

.dropdown-menu {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  z-index: 1000;
}

.dropdown-menu.show {
  display: block;
}

.navbar-nav .dropdown:hover>.dropdown-menu {
  display: block;
}

a {
  text-decoration: none;
}

.route-principal:hover {
  background-color: #2f3438;
}

.route-secundaria:hover {
  background-color: #8787881e;
}
</style>
