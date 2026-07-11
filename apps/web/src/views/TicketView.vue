<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowLeft, Loader2, Send, Sparkles, UserRound } from 'lucide-vue-next'
import { api, ApiError } from '../lib/api'

const props = defineProps<{ id: string }>()

type Message = {
  id: number
  direction: 'inbound' | 'outbound'
  fromEmail: string
  fromName: string | null
  body: string
  createdAt: string | null
}

type Assignee = { id: string; name: string; email: string }

type Ticket = {
  id: number
  reference: string
  subject: string
  status: 'open' | 'resolved' | 'closed'
  category: string | null
  contact: { id: number; name: string | null; email: string } | null
  assignee: Assignee | null
  messages: Message[]
  createdAt: string | null
  updatedAt: string | null
}

type Agent = { id: string; name: string; email: string; role: string }

const ticket = ref<Ticket | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

// ── Assignment ───────────────────────────────────────────────────────────────
const agents = ref<Agent[]>([])
const selectedAssignee = ref<string>('') // '' = Unassigned; otherwise an agent id
const assigning = ref(false)
const assignError = ref<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const res = await api<{ data: Ticket }>(`/api/tickets/${props.id}`)
    ticket.value = res.data
    selectedAssignee.value = res.data.assignee?.id ?? ''
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      error.value = 'This ticket could not be found.'
    } else {
      error.value =
        e instanceof ApiError ? e.message : 'Something went wrong loading this ticket. Please try again.'
    }
  } finally {
    loading.value = false
  }
}

async function loadAgents(): Promise<void> {
  try {
    const res = await api<{ data: Agent[] }>('/api/agents')
    agents.value = res.data
  } catch {
    // Non-fatal: the ticket still renders; the picker just won't populate.
    agents.value = []
  }
}

async function assign(): Promise<void> {
  if (!ticket.value) return
  const previous = ticket.value.assignee?.id ?? ''
  const next = selectedAssignee.value
  if (next === previous) return

  assigning.value = true
  assignError.value = null
  try {
    const res = await api<{ data: Ticket }>(`/api/tickets/${props.id}`, {
      method: 'PATCH',
      body: { assigned_to: next || null },
    })
    // The PATCH response omits the message thread — patch just the fields it returns.
    ticket.value.assignee = res.data.assignee
    ticket.value.updatedAt = res.data.updatedAt
  } catch (e) {
    // Roll the picker back to the last known-good value.
    selectedAssignee.value = previous
    assignError.value =
      e instanceof ApiError ? e.message : 'Could not update the assignee. Please try again.'
  } finally {
    assigning.value = false
  }
}

onMounted(() => {
  load()
  loadAgents()
})

const statusClasses: Record<Ticket['status'], string> = {
  open: 'bg-emerald-500/10 text-emerald-600',
  resolved: 'bg-accent/10 text-accent',
  closed: 'bg-app text-muted',
}

const senderLabel = (m: Message) =>
  m.direction === 'outbound' ? 'Support' : (m.fromName ?? m.fromEmail)

const formatDateTime = (iso: string | null) =>
  iso
    ? new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    : ''

const formatDate = (iso: string | null) =>
  iso
    ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
    : '—'

const initials = (m: Message) => {
  const name = senderLabel(m)
  return (
    name
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((p) => p[0]!.toUpperCase())
      .join('') || '?'
  )
}

const messages = computed(() => ticket.value?.messages ?? [])

// ── Reply composer + AI polish (draft only; sending is not wired yet) ─────────
const reply = ref('')
const polishing = ref(false)
const polishNote = ref<string | null>(null)

async function polish(): Promise<void> {
  const draft = reply.value.trim()
  if (!draft || polishing.value) return
  polishing.value = true
  polishNote.value = null
  try {
    const res = await api<{
      data: { polished: string; aiApplied: boolean; reason: 'disabled' | 'failed' | null }
    }>(`/api/tickets/${props.id}/polish`, { method: 'POST', body: { draft } })
    reply.value = res.data.polished
    if (res.data.aiApplied) {
      polishNote.value = 'Polished with AI.'
    } else if (res.data.reason === 'failed') {
      polishNote.value =
        'AI is temporarily unavailable — your draft is unchanged. Please try again later.'
    } else {
      polishNote.value = 'AI is off — set OPENAI_API_KEY and AI_ENABLED to enable Polish.'
    }
  } catch (e) {
    polishNote.value =
      e instanceof ApiError ? e.message : 'Could not polish the draft. Please try again.'
  } finally {
    polishing.value = false
  }
}
</script>

<template>
  <main class="mx-auto max-w-5xl px-6 py-10">
    <RouterLink
      to="/tickets"
      class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition-colors duration-150 hover:text-accent"
    >
      <ArrowLeft :size="16" :stroke-width="1.5" />
      <span>Back to tickets</span>
    </RouterLink>

    <!-- Error -->
    <div
      v-if="error"
      class="rounded border border-line bg-card p-6 text-center text-sm text-muted"
      role="alert"
    >
      {{ error }}
      <button class="ml-1 font-medium text-accent hover:underline" @click="load">Retry</button>
    </div>

    <!-- Loading -->
    <div
      v-else-if="loading"
      class="rounded border border-line bg-card p-10 text-center text-sm text-muted"
    >
      Loading ticket…
    </div>

    <template v-else-if="ticket">
      <header class="mb-6 border-b border-line pb-5">
        <div class="mb-2 flex flex-wrap items-center gap-2">
          <span class="font-mono text-xs text-muted">{{ ticket.reference }}</span>
          <span
            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
            :class="statusClasses[ticket.status]"
            >{{ ticket.status }}</span
          >
          <span
            v-if="ticket.category"
            class="inline-flex items-center rounded-full bg-app px-2 py-0.5 text-xs font-medium capitalize text-muted"
            >{{ ticket.category }}</span
          >
        </div>
        <h1 class="text-2xl tracking-tight text-ink">{{ ticket.subject }}</h1>
        <p class="mt-1.5 text-sm text-muted">
          {{ ticket.contact?.name ?? ticket.contact?.email }}
          <span v-if="ticket.contact?.name" class="text-muted">&lt;{{ ticket.contact.email }}&gt;</span>
        </p>
      </header>

      <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <!-- Details + assignment -->
        <aside class="lg:order-2">
          <div class="rounded-lg border border-line bg-card p-5">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted">Details</h2>

            <div class="mb-4">
              <label for="assignee" class="mb-1.5 block text-xs font-medium text-muted">
                Assigned agent
              </label>
              <div class="relative">
                <UserRound
                  class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-muted"
                  :size="15"
                  :stroke-width="1.5"
                />
                <select
                  id="assignee"
                  v-model="selectedAssignee"
                  :disabled="assigning"
                  class="w-full appearance-none rounded border border-line bg-card py-2 pl-8 pr-8 text-sm text-ink focus:border-accent focus:outline-none disabled:opacity-60"
                  @change="assign"
                >
                  <option value="">Unassigned</option>
                  <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                    {{ agent.name }}
                  </option>
                </select>
                <Loader2
                  v-if="assigning"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 animate-spin text-muted"
                  :size="15"
                />
              </div>
              <p v-if="assignError" class="mt-1.5 text-xs text-red-600" role="alert">
                {{ assignError }}
              </p>
            </div>

            <dl class="space-y-3 border-t border-line pt-4 text-sm">
              <div class="flex justify-between gap-3">
                <dt class="text-muted">Status</dt>
                <dd class="capitalize text-ink">{{ ticket.status }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-muted">Category</dt>
                <dd class="capitalize text-ink">{{ ticket.category ?? 'Uncategorised' }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="shrink-0 text-muted">Contact</dt>
                <dd class="truncate text-right text-ink">
                  {{ ticket.contact?.name ?? ticket.contact?.email ?? '—' }}
                </dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-muted">Opened</dt>
                <dd class="text-ink">{{ formatDate(ticket.createdAt) }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-muted">Updated</dt>
                <dd class="text-ink">{{ formatDate(ticket.updatedAt) }}</dd>
              </div>
            </dl>
          </div>
        </aside>

        <!-- Conversation thread -->
        <section class="lg:order-1">
          <ol class="space-y-4">
            <li
              v-for="message in messages"
              :key="message.id"
              class="flex gap-3"
              :class="message.direction === 'outbound' ? 'flex-row-reverse' : ''"
            >
              <span
                class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-semibold"
                :class="message.direction === 'outbound' ? 'bg-accent text-white' : 'bg-app text-muted'"
                aria-hidden="true"
                >{{ initials(message) }}</span
              >
              <div
                class="min-w-0 flex-1 rounded-lg border border-line p-4"
                :class="message.direction === 'outbound' ? 'bg-accent/5' : 'bg-card'"
              >
                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                  <span class="truncate text-sm font-medium text-ink">{{ senderLabel(message) }}</span>
                  <span class="shrink-0 text-xs text-muted">{{ formatDateTime(message.createdAt) }}</span>
                </div>
                <p class="whitespace-pre-wrap break-words text-sm text-ink">{{ message.body }}</p>
              </div>
            </li>
          </ol>

          <!-- Reply composer -->
          <div class="mt-6 rounded-lg border border-line bg-card p-4">
            <label for="reply" class="mb-2 block text-sm font-medium text-ink">Reply</label>
            <textarea
              id="reply"
              v-model="reply"
              rows="4"
              placeholder="Write a reply to the customer…"
              class="w-full resize-y rounded border border-line bg-app p-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:outline-none"
            />
            <p v-if="polishNote" class="mt-2 text-xs text-muted">{{ polishNote }}</p>
            <div class="mt-3 flex items-center justify-end gap-2">
              <button
                type="button"
                :disabled="!reply.trim() || polishing"
                class="inline-flex items-center gap-1.5 rounded border border-line bg-card px-3 py-1.5 text-sm font-medium text-ink transition-colors duration-150 hover:bg-app hover:text-accent disabled:cursor-not-allowed disabled:opacity-40"
                @click="polish"
              >
                <Loader2 v-if="polishing" class="animate-spin" :size="15" />
                <Sparkles v-else :size="15" :stroke-width="1.75" />
                Polish
              </button>
              <button
                type="button"
                disabled
                title="Outbound email sending isn't wired up yet"
                class="inline-flex cursor-not-allowed items-center gap-1.5 rounded bg-accent px-3 py-1.5 text-sm font-medium text-white opacity-50"
              >
                <Send :size="15" :stroke-width="1.75" />
                Send reply
              </button>
            </div>
          </div>
        </section>
      </div>
    </template>
  </main>
</template>
