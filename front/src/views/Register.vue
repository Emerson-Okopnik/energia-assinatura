<template>
  <div class="d-flex align-items-center justify-content-center vh-100">
    <div class="card p-4" style="width: 24rem;">
      <h2 class="text-center mb-4">Register</h2>
      <form @submit.prevent="register">
        <div class="form-group mb-3">
          <label for="email">Nome</label>
          <input type="name" v-model="name" class="form-control" id="name" placeholder="Digite seu Nome" required />
        </div>
        <div class="form-group mb-3">
          <label for="email">Email</label>
          <input type="email" v-model="email" class="form-control" id="email" placeholder="Digite seu email" required />
        </div>
        <div class="form-group mb-3">
          <label for="password">Senha</label>
          <input type="password" v-model="password" class="form-control" id="password" placeholder="Digite sua senha" required />
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-2">Registrar</button>
      </form>
      <button @click="goToLogin" class="btn btn-link w-100">Já tem uma conta? Faça login</button>
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
