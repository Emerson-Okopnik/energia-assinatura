<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
      <!-- Converted to Tailwind card layout -->
      <div class="card">
        <div class="text-center mb-8">
          <h2 class="text-3xl font-bold text-gray-900">Criar Conta</h2>
        </div>
        
        <form @submit.prevent="register" class="space-y-6">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nome</label>
            <input 
              type="text" 
              v-model="name" 
              id="name" 
              placeholder="Digite seu nome" 
              required 
              class="input-field"
            />
          </div>

          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
            <input 
              type="email" 
              v-model="email" 
              id="email" 
              placeholder="Digite seu email" 
              required 
              class="input-field"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Senha</label>
            <input 
              type="password" 
              v-model="password" 
              id="password" 
              placeholder="Digite sua senha" 
              required 
              class="input-field"
            />
          </div>

          <button type="submit" class="btn-primary w-full">
            Registrar
          </button>
        </form>

        <p class="mt-8 text-center text-sm text-gray-600">
          Já tem uma conta?
          <a @click.prevent="goToLogin" href="#" class="text-primary-600 hover:text-primary-500 font-medium">
            Faça login
          </a>
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
  data() {
    return {
      name: '',
      email: '',
      password: '',
    };
  },
  methods: {
    async register() {
      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const response = await axios.post(`${baseURL}/register`, {
          name: this.name,
          email: this.email,
          password: this.password,
        });

        const token = response.data.token;
        localStorage.setItem('token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        this.$router.push({ name: 'Home' }).then(() => window.location.reload());
      } catch (error) {
        console.error('Erro no cadastro:', error);
        let mensagem = 'Erro desconhecido. Verifique seus dados.';

        if (error.response?.status === 400) {
          const data = error.response.data;
          mensagem = Object.values(data).flat().join('<br>');
        }

        Swal.fire({
          icon: 'error',
          title: 'Erro ao registrar',
          html: mensagem,
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      }
    },
    goToLogin() {
      this.$router.push({ name: 'Login' });
    },
  },
};
</script>
