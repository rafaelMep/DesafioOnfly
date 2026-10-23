<script setup lang="ts">
import type { TravelStatus } from '../types'
import { Check, Clock3, X } from '@lucide/vue'
defineProps<{ status: TravelStatus }>()
const labels = { solicitado: 'Solicitado', aprovado: 'Aprovado', cancelado: 'Cancelado' }
const icons = { solicitado: Clock3, aprovado: Check, cancelado: X }
</script>
<template>
  <span class="status-badge" :class="`status-${status}`">
    <component :is="icons[status]" :size="13" aria-hidden="true" />{{ labels[status] }}
  </span>
</template>
