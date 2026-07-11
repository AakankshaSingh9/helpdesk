<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { Inbox, Sparkles, BookOpen, Users, Ticket } from 'lucide-vue-next'
import { auth } from '../stores/auth'

const firstName = () => auth.state.user?.name?.split(/\s+/)[0] ?? 'there'

const cards = [
  {
    icon: Inbox,
    title: 'Ticket queue',
    body: 'Incoming support tickets will appear here for triage and reply.',
  },
  {
    icon: Sparkles,
    title: 'AI drafting',
    body: 'Suggested replies and classification, powered by the RAG knowledge base.',
  },
  {
    icon: BookOpen,
    title: 'Knowledge base',
    body: "Curated articles that ground the assistant's answers in your docs.",
  },
]
</script>

<template>
  <main class="mx-auto max-w-4xl px-6 py-10">
    <header class="mb-8">
      <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-accent">Workspace</p>
      <h1 class="text-3xl tracking-tight text-ink">Welcome back, {{ firstName() }} 👋</h1>
      <p class="mt-2 text-muted">You're signed in to the AI Helpdesk.</p>

      <div class="mt-4 flex flex-wrap gap-2">
        <RouterLink
          to="/tickets"
          class="inline-flex items-center gap-1.5 rounded border border-line bg-card px-3 py-1.5 text-sm font-medium text-ink transition-colors duration-150 hover:bg-app hover:text-accent"
        >
          <Ticket :size="18" :stroke-width="1.5" />
          <span>View tickets</span>
        </RouterLink>

        <RouterLink
          v-if="auth.isAdmin"
          to="/users"
          class="inline-flex items-center gap-1.5 rounded border border-line bg-card px-3 py-1.5 text-sm font-medium text-ink transition-colors duration-150 hover:bg-app hover:text-accent"
        >
          <Users :size="18" :stroke-width="1.5" />
          <span>Manage users</span>
        </RouterLink>
      </div>
    </header>

    <section class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4">
      <article
        v-for="card in cards"
        :key="card.title"
        class="rounded border border-line bg-card p-5 shadow-none transition duration-150 hover:-translate-y-0.5 hover:shadow-md"
      >
        <span
          class="mb-3 grid h-9 w-9 place-items-center rounded bg-accent/10 text-accent"
          aria-hidden="true"
        >
          <component :is="card.icon" :size="18" :stroke-width="1.5" />
        </span>
        <h2 class="mb-1.5 text-base text-ink">{{ card.title }}</h2>
        <p class="mb-4 text-sm text-muted">{{ card.body }}</p>
        <span class="rounded-full bg-accent/10 px-2 py-0.5 text-xs font-medium text-accent"
          >Coming soon</span
        >
      </article>
    </section>
  </main>
</template>
