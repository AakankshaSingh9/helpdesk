<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

// Generic confirmation modal. Controlled by the parent via `open`; emits
// `confirm` / `cancel`. Extra body content (e.g. an inline error) goes in the
// default slot. Accessible: role="dialog", focus moves to the confirm action on
// open, Escape and backdrop click cancel (unless a request is in flight).
const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    description?: string
    confirmLabel?: string
    cancelLabel?: string
    /** Destructive styling for the confirm button (red). */
    destructive?: boolean
    /** Request in flight — disables the buttons and blocks dismissal. */
    loading?: boolean
  }>(),
  {
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    destructive: false,
    loading: false,
  },
)

const emit = defineEmits<{ confirm: []; cancel: [] }>()

const confirmButton = ref<InstanceType<typeof Button> | null>(null)

function cancel() {
  if (!props.loading) emit('cancel')
}

// Focus the primary action when the dialog opens.
watch(
  () => props.open,
  async (open) => {
    if (!open) return
    await nextTick()
    ;(confirmButton.value?.$el as HTMLElement | undefined)?.focus()
  },
)

// Escape closes the dialog while it's open.
function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.open) cancel()
}
window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-150"
      leave-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4 backdrop-blur-sm"
        @click.self="cancel"
      >
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="confirm-dialog-title"
          class="w-full max-w-md rounded-lg border border-line bg-card p-6 shadow-lg"
        >
          <h2 id="confirm-dialog-title" class="text-lg font-semibold text-ink">{{ title }}</h2>
          <p v-if="description" class="mt-2 text-sm text-muted">{{ description }}</p>

          <slot />

          <div class="mt-6 flex justify-end gap-2">
            <Button variant="outline" size="sm" :disabled="loading" @click="cancel">
              {{ cancelLabel }}
            </Button>
            <Button
              ref="confirmButton"
              :variant="destructive ? 'destructive' : 'default'"
              size="sm"
              :disabled="loading"
              @click="emit('confirm')"
            >
              <Loader2 v-if="loading" class="animate-spin" :size="16" />
              {{ confirmLabel }}
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
