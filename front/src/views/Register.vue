<template>
  <div class="auth-wrapper">
    <div class="auth-box">
      <h2 class="auth-title">Criar Conta</h2>
      <form @submit.prevent="register" class="auth-form">
        <label for="name">Nome</label>
        <input type="text" v-model="name" id="name" placeholder="Digite seu nome" required />

        <label for="email">Email</label>
        <input type="email" v-model="email" id="email" placeholder="Digite seu email" required />

        <label for="password">Senha</label>
        <input type="password" v-model="password" id="password" placeholder="Digite sua senha" required />

        <button type="submit" class="auth-button">Registrar</button>
      </form>

      <p class="auth-footer">
        Já tem uma conta?
        <a @click.prevent="goToLogin" href="#">Faça login</a>
      </p>
    </div>
  </div>
</template>

<script>
  import axios from 'axios';
  import Swal from 'sweetalert2';
  import '@/assets/css/form-auth.css';

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

            // Extrai todas as mensagens (ex: email, password) do objeto data
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
