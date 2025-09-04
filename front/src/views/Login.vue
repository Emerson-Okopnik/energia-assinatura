<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl w-full grid grid-cols-1 lg:grid-cols-2 gap-8 bg-white rounded-2xl shadow-xl overflow-hidden">
      <!-- Converted to Tailwind layout with responsive grid -->
      <div class="flex flex-col justify-center items-center p-8 bg-gradient-to-br from-primary-50 to-primary-100">
        <div class="text-center">
          <img src="/src/assets/logo-consorcio-lider-energy.png" alt="Logo" class="h-24 w-auto mx-auto mb-6" />
          <p class="text-gray-600 text-lg leading-relaxed">
            Acesse sua conta para aproveitar todos os recursos com segurança e facilidade.
          </p>
        </div>
      </div>

      <!-- Updated form section with Tailwind classes -->
      <div class="p-8 flex flex-col justify-center">
        <div class="mb-8">
          <h2 class="text-3xl font-bold text-gray-900">Entrar</h2>
        </div>

        <form @submit.prevent="login" class="space-y-6">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
            <input 
              type="email" 
              id="email" 
              v-model="email" 
              placeholder="Digite seu email" 
              required 
              class="input-field"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Senha</label>
            <div class="relative">
              <input 
                :type="showPassword ? 'text' : 'password'" 
                id="password" 
                v-model="password" 
                placeholder="Digite sua senha" 
                required
                class="input-field pr-10"
              />
              <button
                type="button"
                @click="togglePassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                title="Mostrar/ocultar senha"
              >
                <i :class="['fas', showPassword ? 'fa-eye' : 'fa-eye-slash']"></i>
              </button>
            </div>
          </div>

          <div class="flex justify-end">
            <a href="#" class="text-sm text-primary-600 hover:text-primary-500 font-medium">
              Esqueceu a senha?
            </a>
          </div>

          <button type="submit" class="btn-primary w-full">
            Entrar
          </button>
        </form>

        <p class="mt-8 text-center text-sm text-gray-600">
          Ainda não tem uma conta?
          <a @click.prevent="goToRegister" href="#" class="text-primary-600 hover:text-primary-500 font-medium">
            Crie agora
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
      email: '',
      password: '',
      showPassword: false
    };
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
    async login() {
      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const response = await axios.post(`${baseURL}/login`, {
          email: this.email,
          password: this.password,
        });

        const token = response.data.token;
        localStorage.setItem('token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        this.$router.push({ name: 'Home' }).then(() => window.location.reload());

      } catch (error) {
        let mensagem = 'Erro desconhecido. Verifique suas credenciais.';
        if (error.response?.data?.error) {
          mensagem = error.response.data.error;
        }

        Swal.fire({
          icon: 'error',
          title: 'Erro ao entrar',
          html: mensagem,
          confirmButtonColor: '#d33',
          confirmButtonText: 'Entendi'
        });
      }
    },
    goToRegister() {
      this.$router.push({ name: 'Register' });
    },
  },
};
</script>
