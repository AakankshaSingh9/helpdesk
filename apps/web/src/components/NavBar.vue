<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { LifeBuoy, LogOut, Moon, Sun } from 'lucide-vue-next'
import { auth } from '../stores/auth'
import { useTheme } from '../composables/useTheme'

const router = useRouter()

const { isDark, toggle } = useTheme()

async function signOut() {
  await auth.logout()
  await router.replace({ name: 'login' })
}

// Initials for the avatar chip, e.g. "Ada Lovelace" → "AL".
const initials = computed(() => {
  const name = auth.state.user?.name ?? ''
  return (
    name
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((p) => p[0]!.toUpperCase())
      .join('') || '?'
  )
})
</script>

<template>
  <nav
    class="sticky top-0 z-10 flex items-center justify-between border-b border-line bg-card/80 px-6 py-3 backdrop-blur-md"
  >
    <div class="flex items-center gap-2 font-semibold text-ink">
      <span
        class="grid h-8 w-8 place-items-center rounded bg-accent/10 text-accent"
        aria-hidden="true"
      >
        <LifeBuoy :size="18" :stroke-width="1.5" />
      </span>
      <span>AI Helpdesk</span>
    </div>

    <div class="flex items-center gap-3.5" v-if="auth.state.user">
      <button
        type="button"
        class="inline-flex cursor-pointer items-center rounded border border-line bg-card p-1.5 text-ink transition-colors duration-150 hover:bg-app hover:text-accent"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
      >
        <Sun v-if="isDark" :size="18" :stroke-width="1.5" />
        <Moon v-else :size="18" :stroke-width="1.5" />
      </button>
      <div class="flex items-center gap-2">
        <span
          class="grid h-8 w-8 place-items-center rounded-full bg-accent text-xs font-semibold text-white"
          aria-hidden="true"
          >{{ initials }}</span
        >
        <span class="hidden text-sm font-medium text-ink sm:inline">{{
          auth.state.user.name
        }}</span>
      </div>
      <button
        class="inline-flex cursor-pointer items-center gap-1.5 rounded border border-line bg-card px-3 py-1.5 text-sm font-medium text-ink transition-colors duration-150 hover:bg-app hover:text-accent"
        @click="signOut"
      >
        <LogOut :size="18" :stroke-width="1.5" />
        <span class="hidden sm:inline">Sign out</span>
      </button>
    </div>
  </nav>
</template>
