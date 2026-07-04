<script setup lang="ts">
import { useRoute } from 'vue-router'
import { computed } from 'vue'
import NavBar from './components/NavBar.vue'

const route = useRoute()
// The login screen is a standalone full-page layout — no nav bar there.
const showNav = computed(() => route.name !== 'login')
</script>

<template>
  <div class="min-h-screen bg-app text-ink antialiased">
    <NavBar v-if="showNav" />
    <router-view />
  </div>
</template>
