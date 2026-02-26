<script setup>
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import TheNavbar from './components/TheNavbar.vue';

const route = useRoute();
const showNavigation = computed(() => Boolean(route.meta.requiresAuth));
</script>

<template>
  <div class="app-root">
    <TheNavbar v-if="showNavigation" />
    <main :class="['app-main', { 'app-main-auth': showNavigation }]">
      <router-view />
    </main>
  </div>
</template>

<style scoped>
.app-root {
  min-height: 100vh;
}

.app-main {
  min-height: 100vh;
}

.app-main-auth {
  padding-top: 72px;
  padding-left: 12px;
  padding-right: 12px;
  background-color: #ffffff;
}

@media (min-width: 992px) {
  .app-main-auth {
    padding-left: 280px;
    padding-right: 28px;
  }
}

.app-main-auth :deep(.container) {
  max-width: 1180px;
  margin-left: auto;
  margin-right: auto;
}
</style>
