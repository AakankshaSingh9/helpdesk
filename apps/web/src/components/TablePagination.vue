<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import type { PageMeta } from '../lib/pagination'

// Server-driven pager. The parent owns the page number and refetches; this just
// renders the range + prev/next and emits the page to move to.
const props = defineProps<{ meta: PageMeta | null; disabled?: boolean }>()
const emit = defineEmits<{ 'update:page': [page: number] }>()

const canPrev = computed(() => !!props.meta && props.meta.current_page > 1)
const canNext = computed(() => !!props.meta && props.meta.current_page < props.meta.last_page)

function go(page: number) {
  if (props.disabled) return
  emit('update:page', page)
}
</script>

<template>
  <div
    v-if="meta && meta.total > 0"
    class="mt-4 flex items-center justify-between gap-4 text-sm text-muted"
  >
    <p>
      Showing <span class="font-medium text-ink">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}</span>
      of <span class="font-medium text-ink">{{ meta.total }}</span>
    </p>

    <div v-if="meta.last_page > 1" class="flex items-center gap-1">
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded border border-line bg-card px-2.5 py-1.5 font-medium text-ink transition-colors duration-150 hover:bg-app disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="!canPrev || disabled"
        @click="go(meta.current_page - 1)"
      >
        <ChevronLeft :size="15" :stroke-width="1.75" />
        Prev
      </button>
      <span class="px-2 tabular-nums">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded border border-line bg-card px-2.5 py-1.5 font-medium text-ink transition-colors duration-150 hover:bg-app disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="!canNext || disabled"
        @click="go(meta.current_page + 1)"
      >
        Next
        <ChevronRight :size="15" :stroke-width="1.75" />
      </button>
    </div>
  </div>
</template>
