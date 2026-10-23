<script setup lang="ts">
import { computed, ref } from 'vue'
import { CircleCheck, CircleX, Mail } from '@lucide/vue'
import BaseModal from './BaseModal.vue'
import LoadingSpinner from './LoadingSpinner.vue'
import { ordersService } from '../services/orders'
import { errorMessage } from '../services/api'
import { useToastStore } from '../stores/toasts'
import type { FinalStatus, TravelOrder } from '../types'

const props = defineProps<{ order: TravelOrder; status: FinalStatus }>()
const emit = defineEmits<{ close: []; updated: []; stale: [] }>()
const approve = computed(() => props.status === 'aprovado')
const busy = ref(false)
const failure = ref('')
const toasts = useToastStore()
async function confirm() {
  if (busy.value) return
  busy.value = true
  failure.value = ''
  try {
    await ordersService.updateStatus(props.order.id, props.status)
    toasts.show(
      `Pedido #${props.order.id} ${props.status}. A notificação será enviada ao solicitante.`,
    )
    emit('updated')
  } catch (error) {
    failure.value = errorMessage(error)
    emit('stale')
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <BaseModal
    :title="approve ? 'Tudo pronto para aprovar?' : 'Cancelar este pedido?'"
    :eyebrow="`PEDIDO #${String(order.id).padStart(4, '0')}`"
    :busy="busy"
    @close="$emit('close')"
  >
    <div class="modal-body confirm-body">
      <span class="confirm-icon" :class="approve ? 'confirm-approve' : 'confirm-cancel'"
        ><component :is="approve ? CircleCheck : CircleX" :size="30"
      /></span>
      <p>
        Você está prestes a <strong>{{ approve ? 'aprovar' : 'cancelar' }}</strong> a viagem de
        <strong>{{ order.requester_name }}</strong> para <strong>{{ order.destination }}</strong
        >.
      </p>
      <p class="muted">
        {{
          approve
            ? 'Após a aprovação, este pedido não poderá ser cancelado.'
            : 'Esta ação é definitiva. O pedido não poderá ser reaberto.'
        }}
      </p>
      <div class="notification-note">
        <Mail :size="17" />O solicitante receberá uma notificação por e-mail.
      </div>
      <div v-if="failure" class="form-error" role="alert">{{ failure }}</div>
    </div>
    <footer class="modal-footer">
      <button
        class="button button-secondary"
        data-autofocus
        :disabled="busy"
        @click="$emit('close')"
      >
        Voltar</button
      ><button
        class="button"
        :class="approve ? 'button-primary' : 'button-danger'"
        :disabled="busy"
        @click="confirm"
      >
        <LoadingSpinner v-if="busy" />{{
          busy ? 'Atualizando…' : approve ? 'Confirmar aprovação' : 'Confirmar cancelamento'
        }}
      </button>
    </footer>
  </BaseModal>
</template>
