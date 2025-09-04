<script setup>
import { RouterView } from 'vue-router'
import TheNavbar from './components/TheNavbar.vue'
</script>

<template>
  <!-- Added proper layout structure with Tailwind classes -->
  <div class="min-h-screen bg-gray-50">
    <TheNavbar />
    <!-- Main content area with proper spacing for fixed navbar -->
    <main class="pt-16">
      <router-view />
    </main>
  </div>
</template>

<style>
/* Removed custom CSS in favor of Tailwind utilities */
</style>
