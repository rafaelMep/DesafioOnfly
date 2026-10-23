<script setup lang="ts">
import { CheckCircle2, AlertCircle, X } from '@lucide/vue'
import { useToastStore } from '../stores/toasts'
const toasts = useToastStore()
</script>
<template>
  <div class="toast-region" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toasts.items"
        :key="toast.id"
        class="toast"
        :class="`toast-${toast.kind}`"
        :role="toast.kind === 'error' ? 'alert' : 'status'"
      >
        <component
          :is="toast.kind === 'success' ? CheckCircle2 : AlertCircle"
          :size="21"
          aria-hidden="true"
        />
        <span>{{ toast.message }}</span>
        <button class="icon-button" aria-label="Fechar mensagem" @click="toasts.remove(toast.id)">
          <X :size="17" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
