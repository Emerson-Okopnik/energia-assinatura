<template>
  <div class="login-page">
    <div class="login-shell">
      <!-- Formulário -->
      <div class="login-form-side">
        <div class="login-form-wrap">
          <h1 class="login-title">Entrar na sua conta</h1>

          <form class="login-form" @submit.prevent="login">
            <BaseField label="E-mail">
              <template #default="{ id, describedBy }">
                <input
                  :id="id"
                  v-model="email"
                  type="email"
                  class="login-input"
                  placeholder="Digite seu e-mail"
                  autocomplete="email"
                  :aria-describedby="describedBy"
                  required
                />
              </template>
            </BaseField>

            <BaseField label="Senha">
              <template #default="{ id, describedBy }">
                <div class="login-password">
                  <input
                    :id="id"
                    v-model="password"
                    :type="showPassword ? 'text' : 'password'"
                    class="login-input"
                    placeholder="Digite sua senha"
                    autocomplete="current-password"
                    :aria-describedby="describedBy"
                    required
                  />
                  <button
                    type="button"
                    class="login-password__toggle"
                    :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                    :aria-pressed="showPassword"
                    @click="togglePassword"
                  >
                    <svg v-if="showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a13.2 13.2 0 0 1-2.16 3.19m-3.3 2.4A9.3 9.3 0 0 1 12 18C5.5 18 2 11 2 11a13.3 13.3 0 0 1 4.06-4.94" />
                      <path d="M9.88 9.88a3 3 0 0 0 4.24 4.24" />
                      <path d="m2 2 20 20" />
                    </svg>
                  </button>
                </div>
              </template>
            </BaseField>

            <div class="login-options">
              <a href="#">Esqueceu a senha?</a>
            </div>

            <p v-if="errorMessage" class="login-error" role="alert">
              <svg class="login-error__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v6" />
                <path d="M12 16h.01" />
              </svg>
              {{ errorMessage }}
            </p>

            <BaseButton
              type="submit"
              variant="primary"
              glow
              class="login-submit"
              :loading="loading"
            >
              Entrar
            </BaseButton>
          </form>
        </div>
      </div>

      <!-- Painel da marca (animado) -->
      <aside class="login-brand-side">
        <div class="login-brand">
          <img class="login-brand__logo" :src="logo" alt="Líder Energy" />
        </div>
      </aside>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import '@/assets/css/form-login.css';
import logo from '@/assets/brand/logo-black.png';
import { setAuthSession } from '@/utils/auth.js';
import BaseField from '@/components/base/BaseField.vue';
import BaseButton from '@/components/base/BaseButton.vue';

export default {
  components: { BaseField, BaseButton },
  data() {
    return {
      logo,
      email: '',
      password: '',
      showPassword: false,
      loading: false,
      errorMessage: '',
    };
  },
  methods: {
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
    async login() {
      if (this.loading) return;
      this.loading = true;
      this.errorMessage = '';

      try {
        const baseURL = import.meta.env.VITE_API_URL;
        const response = await axios.post(`${baseURL}/login`, {
          email: this.email,
          password: this.password,
        });

        setAuthSession(response.data.token);
        this.$router.replace({ name: 'Home' });
      } catch (error) {
        this.errorMessage =
          error.response?.data?.error ||
          'Não foi possível entrar. Verifique suas credenciais.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
