<template>
  <div class="login-centered">
    <div class="login-box">
      <!-- Ilustração -->
      <div class="login-illustration">
        <img src="/src/assets/logo-consorcio-lider-energy.png" alt="Logo" />
        <p class="tip-text">Acesse sua conta para aproveitar todos os recursos com segurança e facilidade.</p>
      </div>

      <!-- Formulário -->
      <div class="login-form">
        <div class="greeting">
          <h2>Entrar</h2>
        </div>

        <form @submit.prevent="login" class="auth-form">
          <label for="email">Email</label>
          <input type="email" id="email" v-model="email" placeholder="Digite seu email" required />

          <label for="password">Senha</label>
          <div class="password-wrapper">
            <input :type="showPassword ? 'text' : 'password'" id="password" v-model="password" placeholder="Digite sua senha" required/>
            <i :class="['fas', showPassword ? 'fa-eye' : 'fa-eye-slash']" @click="togglePassword" class="toggle-password" title="Mostrar/ocultar senha"></i>
          </div>

          <div class="options">
            <a href="#">Esqueceu a senha?</a>
          </div>

          <button type="submit" class="auth-button">Entrar</button>
        </form>

        <p class="auth-footer">
          Ainda não tem uma conta?
          <a @click.prevent="goToRegister" href="#">Crie agora</a>
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import Swal from 'sweetalert2';
import '@/assets/css/form-login.css';

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
