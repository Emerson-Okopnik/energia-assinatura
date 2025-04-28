<template>
  <div class="d-flex align-items-center justify-content-center vh-100">
    <div class="card p-4" style="width: 24rem;">
      <h2 class="text-center mb-4">Login</h2>
      <form @submit.prevent="login">
        <div class="form-group mb-3">
          <label for="email">Email</label>
          <input type="email" v-model="email" class="form-control" id="email" placeholder="Digite seu email" required />
        </div>
        <div class="form-group mb-3">
          <label for="password">Senha</label>
          <input type="password" v-model="password" class="form-control" id="password" placeholder="Digite sua senha" required />
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-2">Entrar</button>
      </form>
      <button @click="goToRegister" class="btn btn-link w-100">Não tem uma conta? Registre-se</button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      email: '',
      password: '',
    };
  },
  methods: {
    async login() {
      try {
        const response = await axios.post('http://localhost:8000/api/login', {
          email: this.email,
          password: this.password,
        });

        const token = response.data.token;

        // Salva o token no localStorage
        localStorage.setItem('token', token);

        // Define o header Authorization para futuras requisições
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        // Redireciona para a página inicial
        this.$router.push({ name: 'Home' }).then(() => {
          window.location.reload();
        });
      } catch (error) {
        console.error('Erro no login:', error);
        alert('Login falhou. Verifique suas credenciais.');
      }
    },
    goToRegister() {
      this.$router.push({ name: 'Register' });
    },
  },
};
</script>
