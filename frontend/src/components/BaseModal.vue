<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, useId } from 'vue'
import { X } from '@lucide/vue'

const props = withDefaults(
  defineProps<{ title: string; eyebrow?: string; busy?: boolean; wide?: boolean }>(),
  { busy: false, wide: false },
)
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLElement | null>(null)
const titleId = useId()
let previousFocus: HTMLElement | null = null
let originalOverflow = ''

function close() {
  if (!props.busy) emit('close')
}
function keydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
  }
  if (event.key !== 'Tab') return
  const focusable = dialog.value?.querySelectorAll<HTMLElement>(
    'button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href], [tabindex="0"]',
  )
  if (!focusable?.length) {
    event.preventDefault()
    return
  }
  const first = focusable[0]!
  const last = focusable[focusable.length - 1]!
  if (
    event.shiftKey &&
    (document.activeElement === first || document.activeElement === dialog.value)
  ) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

onMounted(async () => {
  previousFocus = document.activeElement as HTMLElement
  originalOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  document.addEventListener('keydown', keydown)
  await nextTick()
  const initial = dialog.value?.querySelector<HTMLElement>('[data-autofocus]')
  ;(initial || dialog.value)?.focus()
})
onUnmounted(() => {
  document.body.style.overflow = originalOverflow
  document.removeEventListener('keydown', keydown)
  previousFocus?.focus()
})
</script>
<template>
  <Teleport to="body">
    <div class="modal-backdrop" @mousedown.self="close">
      <section
        ref="dialog"
        class="modal"
        :class="{ 'modal-wide': wide }"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :aria-busy="busy"
        tabindex="-1"
      >
        <header class="modal-header">
          <div>
            <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
            <h2 :id="titleId">{{ title }}</h2>
          </div>
          <button class="icon-button" aria-label="Fechar janela" :disabled="busy" @click="close">
            <X :size="21" />
          </button>
        </header>
        <slot />
      </section>
    </div>
  </Teleport>
</template>
