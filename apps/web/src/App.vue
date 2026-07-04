<script setup lang="ts">
import { ref, onMounted } from 'vue'

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

type Health = { status: string; service: string; time: string }

const health = ref<Health | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const res = await fetch(`${apiUrl}/api/health`)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    health.value = (await res.json()) as Health
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
})
</script>

<template>
  <main style="font-family: system-ui; max-width: 640px; margin: 4rem auto; padding: 0 1rem">
    <h1>AI Helpdesk — Vue SPA</h1>
    <p>This page calls the Laravel API to prove the two apps are talking.</p>

    <section style="margin-top: 1.5rem; padding: 1rem; border: 1px solid #ccc; border-radius: 8px">
      <h2 style="margin-top: 0">API health</h2>
      <p v-if="health" style="color: green">
        ✅ {{ health.status }} — {{ health.service }} ({{ health.time }})
      </p>
      <p v-else-if="error" style="color: crimson">❌ Could not reach API: {{ error }}</p>
      <p v-else>… checking {{ apiUrl }}/api/health</p>
    </section>
  </main>
</template>
