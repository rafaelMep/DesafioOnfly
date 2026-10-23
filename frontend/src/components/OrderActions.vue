<script setup lang="ts">
import { Check, X, Eye } from '@lucide/vue'
import type { FinalStatus, TravelOrder, User } from '../types'
import { canManageOrder } from '../utils/format'

defineProps<{ order: TravelOrder; user: User | null }>()
defineEmits<{ detail: [id: number]; status: [order: TravelOrder, status: FinalStatus] }>()
</script>

<template>
  <div class="table-actions">
    <template v-if="canManageOrder(user, order)">
      <button
        class="icon-button action-approve"
        :aria-label="`Aprovar pedido ${order.id}`"
        title="Aprovar pedido"
        @click="$emit('status', order, 'aprovado')"
      >
        <Check :size="17" />
      </button>
      <button
        class="icon-button action-cancel"
        :aria-label="`Cancelar pedido ${order.id}`"
        title="Cancelar pedido"
        @click="$emit('status', order, 'cancelado')"
      >
        <X :size="17" />
      </button>
    </template>
    <button
      class="icon-button"
      :aria-label="`Abrir detalhes do pedido ${order.id}`"
      title="Ver detalhes"
      @click="$emit('detail', order.id)"
    >
      <Eye :size="17" />
    </button>
  </div>
</template>
