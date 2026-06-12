<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import TheAppShell from './components/layout/TheAppShell.vue'

const route = useRoute()

const ehLogin = computed(() => ['Login', 'Register'].includes(route.name))
</script>

<template>
  <TheAppShell v-if="!ehLogin">
    <router-view />
  </TheAppShell>
  <router-view v-else />
</template>
