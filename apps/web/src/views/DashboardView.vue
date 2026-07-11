<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  AlertTriangle,
  ArrowLeft,
  BarChart3,
  CheckCircle2,
  CircleDot,
  Inbox,
  Loader2,
} from 'lucide-vue-next'
import { api, ApiError } from '../lib/api'

type CategoryRow = { category: string; label: string; count: number }

type DashboardData = {
  stats: { active: number; resolved: number; priority: number; total: number }
  byCategory: CategoryRow[]
}

type Agent = { id: string; name: string; email: string; role: string }

// ── Filters ────────────────────────────────────────────────────────────────
// Each is a query param the /api/dashboard endpoint understands; changing any
// refetches so the cards and chart recompute against the same criteria.
const range = ref<'7' | '30' | '90' | 'all'>('30')
const category = ref<'' | 'general' | 'technical' | 'refund' | 'uncategorised'>('')
const status = ref<'' | 'open' | 'resolved' | 'closed'>('')
const assignedTo = ref<string>('') // '' = all; 'unassigned'; or an agent id

const data = ref<DashboardData | null>(null)
const agents = ref<Agent[]>([])
const loading = ref(true)
const refreshing = ref(false)
const error = ref<string | null>(null)

let loadToken = 0

async function load(): Promise<void> {
  const token = ++loadToken
  data.value === null ? (loading.value = true) : (refreshing.value = true)
  error.value = null
  try {
    const params = new URLSearchParams()
    if (range.value !== 'all') params.set('range', range.value)
    if (category.value) params.set('category', category.value)
    if (status.value) params.set('status', status.value)
    if (assignedTo.value) params.set('assigned_to', assignedTo.value)
    const res = await api<{ data: DashboardData }>(`/api/dashboard?${params.toString()}`)
    if (token !== loadToken) return // superseded by a newer request
    data.value = res.data
  } catch (e) {
    if (token !== loadToken) return
    error.value =
      e instanceof ApiError ? e.message : 'Something went wrong loading the dashboard. Please try again.'
  } finally {
    if (token === loadToken) {
      loading.value = false
      refreshing.value = false
    }
  }
}

async function loadAgents(): Promise<void> {
  try {
    const res = await api<{ data: Agent[] }>('/api/agents')
    agents.value = res.data
  } catch {
    agents.value = [] // non-fatal: the picker just won't populate
  }
}

// Any filter change refetches. Selects change infrequently, so no debounce.
watch([range, category, status, assignedTo], load)

onMounted(() => {
  load()
  loadAgents()
})

const stats = computed(() => data.value?.stats ?? { active: 0, resolved: 0, priority: 0, total: 0 })

const cards = computed(() => [
  {
    key: 'active',
    label: 'Active tickets',
    value: stats.value.active,
    icon: CircleDot,
    tint: 'bg-emerald-500/10 text-emerald-600',
    hint: 'Currently open',
  },
  {
    key: 'resolved',
    label: 'Resolved tickets',
    value: stats.value.resolved,
    icon: CheckCircle2,
    tint: 'bg-accent/10 text-accent',
    hint: 'Marked resolved',
  },
  {
    key: 'priority',
    label: 'Priority (open refunds)',
    value: stats.value.priority,
    icon: AlertTriangle,
    tint: 'bg-amber-500/10 text-amber-600',
    hint: 'Open refund requests',
  },
  {
    key: 'total',
    label: 'Total tickets',
    value: stats.value.total,
    icon: Inbox,
    tint: 'bg-app text-muted',
    hint: 'In this view',
  },
])

const byCategory = computed(() => data.value?.byCategory ?? [])
// Scale bars to the busiest category so the longest bar fills the track.
const maxCount = computed(() => Math.max(1, ...byCategory.value.map((c) => c.count)))
const hasChartData = computed(() => byCategory.value.some((c) => c.count > 0))

// Per-category bar colour, so the chart reads at a glance.
const barColour: Record<string, string> = {
  general: 'bg-accent',
  technical: 'bg-emerald-500',
  refund: 'bg-amber-500',
  uncategorised: 'bg-slate-400',
}

function resetFilters(): void {
  range.value = '30'
  category.value = ''
  status.value = ''
  assignedTo.value = ''
}

const filtersActive = computed(
  () => range.value !== '30' || category.value !== '' || status.value !== '' || assignedTo.value !== '',
)
</script>

<template>
  <main class="mx-auto max-w-5xl px-6 py-10">
    <RouterLink
      to="/home"
      class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition-colors duration-150 hover:text-accent"
    >
      <ArrowLeft :size="16" :stroke-width="1.5" />
      <span>Back to workspace</span>
    </RouterLink>

    <header class="mb-6">
      <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-accent">Overview</p>
      <h1 class="text-3xl tracking-tight text-ink">Dashboard</h1>
      <p class="mt-2 text-muted">Ticket volume and status at a glance.</p>
    </header>

    <!-- Filters -->
    <section
      class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-line bg-card p-4"
      aria-label="Dashboard filters"
    >
      <div class="flex flex-col gap-1">
        <label for="f-range" class="text-xs font-medium text-muted">Date range</label>
        <select
          id="f-range"
          v-model="range"
          class="rounded border border-line bg-card px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
        >
          <option value="7">Last 7 days</option>
          <option value="30">Last 30 days</option>
          <option value="90">Last 90 days</option>
          <option value="all">All time</option>
        </select>
      </div>

      <div class="flex flex-col gap-1">
        <label for="f-category" class="text-xs font-medium text-muted">Category</label>
        <select
          id="f-category"
          v-model="category"
          class="rounded border border-line bg-card px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
        >
          <option value="">All categories</option>
          <option value="general">General</option>
          <option value="technical">Technical</option>
          <option value="refund">Refund</option>
          <option value="uncategorised">Uncategorised</option>
        </select>
      </div>

      <div class="flex flex-col gap-1">
        <label for="f-status" class="text-xs font-medium text-muted">Status</label>
        <select
          id="f-status"
          v-model="status"
          class="rounded border border-line bg-card px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
        >
          <option value="">All statuses</option>
          <option value="open">Open</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
      </div>

      <div class="flex flex-col gap-1">
        <label for="f-agent" class="text-xs font-medium text-muted">Assigned agent</label>
        <select
          id="f-agent"
          v-model="assignedTo"
          class="rounded border border-line bg-card px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
        >
          <option value="">All agents</option>
          <option value="unassigned">Unassigned</option>
          <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
        </select>
      </div>

      <button
        v-if="filtersActive"
        type="button"
        class="ml-auto self-end rounded border border-line bg-card px-3 py-1.5 text-sm font-medium text-muted transition-colors duration-150 hover:text-accent"
        @click="resetFilters"
      >
        Reset
      </button>
      <Loader2 v-if="refreshing" class="mb-1.5 animate-spin text-muted" :size="16" />
    </section>

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
      Loading dashboard…
    </div>

    <template v-else>
      <!-- Stat cards -->
      <section
        class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4 transition-opacity duration-150"
        :class="refreshing ? 'opacity-60' : ''"
      >
        <article
          v-for="card in cards"
          :key="card.key"
          class="rounded-lg border border-line bg-card p-5"
        >
          <div class="mb-3 flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-muted">{{ card.label }}</span>
            <span class="grid h-8 w-8 place-items-center rounded" :class="card.tint" aria-hidden="true">
              <component :is="card.icon" :size="16" :stroke-width="1.75" />
            </span>
          </div>
          <p class="text-3xl font-semibold tracking-tight text-ink">{{ card.value }}</p>
          <p class="mt-1 text-xs text-muted">{{ card.hint }}</p>
        </article>
      </section>

      <!-- Bar chart: tickets by category -->
      <section
        class="rounded-lg border border-line bg-card p-5 transition-opacity duration-150"
        :class="refreshing ? 'opacity-60' : ''"
      >
        <h2 class="mb-4 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-muted">
          <BarChart3 :size="14" :stroke-width="1.75" />
          Tickets by category
        </h2>

        <p v-if="!hasChartData" class="py-6 text-center text-sm text-muted">
          No tickets match the current filters.
        </p>

        <ul v-else class="space-y-3">
          <li v-for="row in byCategory" :key="row.category" class="flex items-center gap-3">
            <span class="w-28 shrink-0 text-sm text-muted">{{ row.label }}</span>
            <div class="h-6 flex-1 overflow-hidden rounded bg-app">
              <div
                class="flex h-full min-w-[2px] items-center justify-end rounded px-2 transition-[width] duration-300"
                :class="barColour[row.category] ?? 'bg-accent'"
                :style="{ width: `${Math.round((row.count / maxCount) * 100)}%` }"
              >
                <span v-if="row.count > 0" class="text-xs font-semibold text-white">{{ row.count }}</span>
              </div>
            </div>
            <span v-if="row.count === 0" class="w-6 shrink-0 text-xs text-muted">0</span>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>
