<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowLeft, Search, ShieldCheck, BadgeCheck, Trash2 } from 'lucide-vue-next'
import { api, ApiError } from '../lib/api'
import type { PageMeta } from '../lib/pagination'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import TablePagination from '../components/TablePagination.vue'

type UserRow = {
  id: string
  name: string
  email: string
  role: string
  emailVerified: boolean
  createdAt: string | null
}

const users = ref<UserRow[]>([])
const meta = ref<PageMeta | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const search = ref('')
const page = ref(1)

let searchToken = 0

async function load(): Promise<void> {
  const token = ++searchToken
  loading.value = true
  error.value = null
  try {
    // Only add params that differ from defaults, keeping the common URL clean.
    const params = new URLSearchParams()
    const term = search.value.trim()
    if (term) params.set('search', term)
    if (page.value > 1) params.set('page', String(page.value))
    const query = params.toString() ? `?${params.toString()}` : ''
    const res = await api<{ data: UserRow[]; meta?: PageMeta }>(`/api/users${query}`)
    // Ignore a response that a newer search has already superseded.
    if (token !== searchToken) return
    users.value = res.data
    meta.value = res.meta ?? null
  } catch (e) {
    if (token !== searchToken) return
    error.value =
      e instanceof ApiError ? e.message : 'Something went wrong loading users. Please try again.'
  } finally {
    if (token === searchToken) loading.value = false
  }
}

// Debounce search input so we don't fire a request per keystroke. A new search
// always returns to the first page.
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

const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]!.toUpperCase())
    .join('') || '?'

const formatDate = (iso: string | null) =>
  iso
    ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
    : '—'

const count = computed(() => meta.value?.total ?? users.value.length)

// ── Deletion ────────────────────────────────────────────────────────────────
// The user awaiting confirmation (drives the modal), plus request state.
const pendingDelete = ref<UserRow | null>(null)
const deleting = ref(false)
const deleteError = ref<string | null>(null)

function requestDelete(user: UserRow): void {
  deleteError.value = null
  pendingDelete.value = user
}

function cancelDelete(): void {
  if (deleting.value) return
  pendingDelete.value = null
  deleteError.value = null
}

async function confirmDelete(): Promise<void> {
  const target = pendingDelete.value
  if (!target) return

  deleting.value = true
  deleteError.value = null
  try {
    await api(`/api/users/${target.id}`, { method: 'DELETE' })
    pendingDelete.value = null
    // Step back a page if we just removed the last row on a non-first page,
    // then refetch so the total and pagination stay correct.
    if (users.value.length === 1 && page.value > 1) page.value -= 1
    await load()
  } catch (e) {
    deleteError.value =
      e instanceof ApiError ? e.message : 'Could not delete this user. Please try again.'
  } finally {
    deleting.value = false
  }
}
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
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-widest text-accent">Admin</p>
        <h1 class="text-3xl tracking-tight text-ink">Users</h1>
        <p class="mt-2 text-muted">
          Everyone with access to the helpdesk.
          <span v-if="!loading && !error">{{ count }} {{ count === 1 ? 'user' : 'users' }}.</span>
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
          placeholder="Search name or email"
          class="w-full rounded border border-line bg-card py-2 pl-9 pr-3 text-sm text-ink placeholder:text-muted focus:border-accent focus:outline-none sm:w-64"
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
      Loading users…
    </div>

    <!-- Empty -->
    <div
      v-else-if="count === 0"
      class="rounded border border-line bg-card p-10 text-center text-sm text-muted"
    >
      No users match your search.
    </div>

    <!-- Table -->
    <template v-else>
      <div class="overflow-x-auto rounded border border-line bg-card">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-line text-xs uppercase tracking-wide text-muted">
            <th class="px-4 py-3 font-medium">User</th>
            <th class="px-4 py-3 font-medium">Role</th>
            <th class="hidden px-4 py-3 font-medium sm:table-cell">Joined</th>
            <th class="px-4 py-3 font-medium"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.id"
            class="border-b border-line last:border-0 transition-colors duration-150 hover:bg-app"
          >
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <span
                  class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent text-xs font-semibold text-white"
                  aria-hidden="true"
                  >{{ initials(user.name) }}</span
                >
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5 font-medium text-ink">
                    <span class="truncate">{{ user.name }}</span>
                    <BadgeCheck
                      v-if="user.emailVerified"
                      class="shrink-0 text-accent"
                      :size="15"
                      :stroke-width="2"
                      aria-label="Email verified"
                    />
                  </div>
                  <div class="truncate text-muted">{{ user.email }}</div>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span
                v-if="user.role === 'admin'"
                class="inline-flex items-center gap-1 rounded-full bg-accent/10 px-2 py-0.5 text-xs font-medium text-accent"
              >
                <ShieldCheck :size="13" :stroke-width="2" />
                Admin
              </span>
              <span
                v-else
                class="inline-flex items-center rounded-full bg-app px-2 py-0.5 text-xs font-medium capitalize text-muted"
                >{{ user.role }}</span
              >
            </td>
            <td class="hidden px-4 py-3 text-muted sm:table-cell">{{ formatDate(user.createdAt) }}</td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="user.role !== 'admin'"
                type="button"
                class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-muted transition-colors duration-150 hover:bg-red-500/10 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40"
                @click="requestDelete(user)"
              >
                <Trash2 :size="14" :stroke-width="1.75" />
                <span class="sr-only sm:not-sr-only">Delete</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
      <TablePagination :meta="meta" :disabled="loading" @update:page="goToPage" />
    </template>

    <ConfirmDialog
      :open="pendingDelete !== null"
      title="Delete user"
      :description="
        pendingDelete
          ? `Delete ${pendingDelete.name} (${pendingDelete.email})? They'll lose access immediately. This can be undone by an administrator.`
          : undefined
      "
      confirm-label="Delete"
      destructive
      :loading="deleting"
      @confirm="confirmDelete"
      @cancel="cancelDelete"
    >
      <p v-if="deleteError" class="mt-3 text-sm text-red-600" role="alert">{{ deleteError }}</p>
    </ConfirmDialog>
  </main>
</template>
