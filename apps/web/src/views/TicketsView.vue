<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ArrowLeft, ChevronDown, ChevronUp, ChevronsUpDown, Inbox, Search } from 'lucide-vue-next'
import {
  getCoreRowModel,
  useVueTable,
  type ColumnDef,
  type SortingState,
} from '@tanstack/vue-table'
import { api, ApiError } from '../lib/api'
import type { PageMeta } from '../lib/pagination'
import TablePagination from '../components/TablePagination.vue'

type TicketRow = {
  id: number
  reference: string
  subject: string
  status: 'open' | 'resolved' | 'closed'
  category: string | null
  contact: { id: number; name: string | null; email: string } | null
  messageCount: number
  updatedAt: string | null
}

const router = useRouter()
const tickets = ref<TicketRow[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(true) // first load only — blanks the table
const refreshing = ref(false) // re-sort / paginate — keeps the table, dims it
const error = ref<string | null>(null)
const search = ref('')
const page = ref(1)

// Sorting is owned by the table but applied on the server. Each column id here
// matches a key the API's sort allowlist accepts (see TicketController).
const sorting = ref<SortingState>([{ id: 'updatedAt', desc: true }])

// Column metadata carries the responsive header classes so the header cells line
// up with the hand-rendered body cells below.
type Meta = { headerClass?: string }

// Each column needs an accessor (not just an id) for TanStack v8 to treat it as
// sortable — a bare display column reports getCanSort() === false. The accessor
// values themselves go unused (manualSorting: true; the server does the sorting).
// Column ids must match the keys the API's sort allowlist accepts.
const columns: ColumnDef<TicketRow>[] = [
  { id: 'subject', accessorKey: 'subject', header: 'Ticket' },
  { id: 'status', accessorKey: 'status', header: 'Status' },
  {
    id: 'contact',
    accessorFn: (row) => row.contact?.name ?? row.contact?.email ?? '',
    header: 'Contact',
    meta: { headerClass: 'hidden sm:table-cell' } satisfies Meta,
  },
  {
    id: 'updatedAt',
    accessorKey: 'updatedAt',
    header: 'Updated',
    meta: { headerClass: 'hidden sm:table-cell' } satisfies Meta,
  },
]

const table = useVueTable({
  get data() {
    return tickets.value
  },
  columns,
  state: {
    get sorting() {
      return sorting.value
    },
  },
  manualSorting: true, // the server sorts; don't re-sort client-side
  enableSortingRemoval: false, // clicking cycles asc ↔ desc, never "unsorted"
  onSortingChange: (updater) => {
    sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater
  },
  getCoreRowModel: getCoreRowModel(),
})

let loadToken = 0

async function load(): Promise<void> {
  const token = ++loadToken
  const initial = tickets.value.length === 0 && meta.value === null
  initial ? (loading.value = true) : (refreshing.value = true)
  error.value = null
  try {
    const s = sorting.value[0]
    const params = new URLSearchParams()
    if (s) {
      params.set('sort', s.id)
      params.set('direction', s.desc ? 'desc' : 'asc')
    }
    const term = search.value.trim()
    if (term) params.set('search', term)
    if (page.value > 1) params.set('page', String(page.value))
    const res = await api<{ data: TicketRow[]; meta?: PageMeta }>(`/api/tickets?${params.toString()}`)
    if (token !== loadToken) return // superseded by a newer request
    tickets.value = res.data
    meta.value = res.meta ?? null
  } catch (e) {
    if (token !== loadToken) return
    error.value =
      e instanceof ApiError ? e.message : 'Something went wrong loading tickets. Please try again.'
  } finally {
    if (token === loadToken) {
      loading.value = false
      refreshing.value = false
    }
  }
}

// Sorting and searching both return to the first page before refetching.
watch(sorting, () => {
  page.value = 1
  load()
}, { deep: true })

let debounce: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    page.value = 1
    load()
  }, 250)
})

function goToPage(next: number): void {
  page.value = next
  load()
}

onMounted(load)

const count = computed(() => meta.value?.total ?? tickets.value.length)

const statusClasses: Record<TicketRow['status'], string> = {
  open: 'bg-emerald-500/10 text-emerald-600',
  resolved: 'bg-accent/10 text-accent',
  closed: 'bg-app text-muted',
}

const formatDate = (iso: string | null) =>
  iso
    ? new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      })
    : '—'

const open = (t: TicketRow) => router.push({ name: 'ticket', params: { id: String(t.id) } })
</script>

<template>
  <main class="mx-auto max-w-4xl px-6 py-10">
    <RouterLink
      to="/home"
      class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition-colors duration-150 hover:text-accent"
    >
      <ArrowLeft :size="16" :stroke-width="1.5" />
      <span>Back to workspace</span>
    </RouterLink>

    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-accent">Support</p>
        <h1 class="text-3xl tracking-tight text-ink">Tickets</h1>
        <p class="mt-2 text-muted">
          Conversations raised from inbound support email.
          <span v-if="!loading && !error">{{ count }} {{ count === 1 ? 'ticket' : 'tickets' }}.</span>
        </p>
      </div>

      <div class="relative">
        <Search
          class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"
          :size="16"
          :stroke-width="1.5"
        />
        <input
          v-model="search"
          type="search"
          placeholder="Search subject, ref or contact"
          class="w-full rounded border border-line bg-card py-2 pl-9 pr-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:outline-none sm:w-72"
        />
      </div>
    </header>

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
      Loading tickets…
    </div>

    <!-- Empty -->
    <div
      v-else-if="count === 0"
      class="rounded border border-line bg-card p-10 text-center text-sm text-muted"
    >
      <Inbox class="mx-auto mb-3 text-muted" :size="28" :stroke-width="1.25" />
      <template v-if="search.trim()">No tickets match your search.</template>
      <template v-else>No tickets yet. Inbound support email will appear here.</template>
    </div>

    <!-- Table -->
    <template v-else>
      <div
        class="overflow-x-auto rounded border border-line bg-card transition-opacity duration-150"
        :class="refreshing ? 'opacity-60' : ''"
      >
      <table class="w-full text-left text-sm">
        <thead>
          <tr
            v-for="headerGroup in table.getHeaderGroups()"
            :key="headerGroup.id"
            class="border-b border-line text-xs uppercase tracking-wide text-muted"
          >
            <th
              v-for="header in headerGroup.headers"
              :key="header.id"
              scope="col"
              class="px-4 py-3 font-medium"
              :class="(header.column.columnDef.meta as Meta | undefined)?.headerClass"
              :aria-sort="
                header.column.getIsSorted() === 'asc'
                  ? 'ascending'
                  : header.column.getIsSorted() === 'desc'
                    ? 'descending'
                    : 'none'
              "
            >
              <button
                type="button"
                class="group inline-flex items-center gap-1 uppercase tracking-wide transition-colors duration-150 hover:text-ink"
                :class="header.column.getIsSorted() ? 'text-ink' : ''"
                @click="header.column.getToggleSortingHandler()?.($event)"
              >
                {{ header.column.columnDef.header }}
                <ChevronUp
                  v-if="header.column.getIsSorted() === 'asc'"
                  :size="13"
                  :stroke-width="2.5"
                />
                <ChevronDown
                  v-else-if="header.column.getIsSorted() === 'desc'"
                  :size="13"
                  :stroke-width="2.5"
                />
                <ChevronsUpDown
                  v-else
                  :size="13"
                  :stroke-width="2"
                  class="text-muted/50 transition-colors duration-150 group-hover:text-muted"
                />
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in table.getRowModel().rows"
            :key="row.original.id"
            class="cursor-pointer border-b border-line last:border-0 transition-colors duration-150 hover:bg-app"
            @click="open(row.original)"
          >
            <td class="px-4 py-3">
              <div class="font-medium text-ink">{{ row.original.subject }}</div>
              <div class="mt-0.5 flex items-center gap-2 text-xs text-muted">
                <span class="font-mono">{{ row.original.reference }}</span>
                <span>·</span>
                <span
                  >{{ row.original.messageCount }}
                  {{ row.original.messageCount === 1 ? 'message' : 'messages' }}</span
                >
              </div>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                :class="statusClasses[row.original.status]"
                >{{ row.original.status }}</span
              >
            </td>
            <td class="hidden px-4 py-3 text-muted sm:table-cell">
              {{ row.original.contact?.name ?? row.original.contact?.email ?? '—' }}
            </td>
            <td class="hidden px-4 py-3 text-muted sm:table-cell">
              {{ formatDate(row.original.updatedAt) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
      <TablePagination :meta="meta" :disabled="refreshing" @update:page="goToPage" />
    </template>
  </main>
</template>
