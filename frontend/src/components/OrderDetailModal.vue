<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { MapPin, CalendarDays, UserRound, Info, Check, X, ArrowRight } from '@lucide/vue'
import BaseModal from './BaseModal.vue'
import LoadingSpinner from './LoadingSpinner.vue'
import StatusBadge from './StatusBadge.vue'
import { ordersService } from '../services/orders'
import { errorMessage } from '../services/api'
import { canManageOrder, formatDate, tripDuration } from '../utils/format'
import { useAuthStore } from '../stores/auth'
import type { FinalStatus, TravelOrder } from '../types'

const props = defineProps<{ id: number }>()
defineEmits<{ close: []; status: [order: TravelOrder, status: FinalStatus] }>()
const auth = useAuthStore()
const order = ref<TravelOrder | null>(null)
const error = ref('')
const loading = ref(true)
async function load() {
  loading.value = true
  error.value = ''
  try {
    order.value = await ordersService.show(props.id)
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
<template>
  <BaseModal
    :title="`Pedido #${String(id).padStart(4, '0')}`"
    eyebrow="DETALHES DA VIAGEM"
    @close="$emit('close')"
  >
    <div v-if="loading" class="detail-loading">
      <LoadingSpinner label="Carregando detalhes" />
      <p>Buscando os detalhes da sua viagem…</p>
    </div>
    <div v-else-if="error" class="modal-body">
      <p class="form-error" role="alert">{{ error }}</p>
      <button class="button button-secondary" @click="load">Tentar novamente</button>
    </div>
    <template v-else-if="order">
      <div class="modal-body">
        <div class="detail-destination">
          <span class="detail-destination-icon"><MapPin :size="28" /></span>
          <div>
            <small>SEU DESTINO</small>
            <h3>{{ order.destination }}</h3>
          </div>
        </div>
        <div class="detail-status">
          <StatusBadge :status="order.status" /><span
            >Criado em {{ formatDate(order.created_at) }}</span
          >
        </div>
        <div class="detail-route">
          <div>
            <span>IDA</span><strong>{{ formatDate(order.departure_date) }}</strong>
          </div>
          <span class="route-line"><ArrowRight :size="19" /></span>
          <div>
            <span>VOLTA</span><strong>{{ formatDate(order.return_date) }}</strong>
          </div>
        </div>
        <dl class="detail-list">
          <div>
            <dt><UserRound :size="16" />Solicitante</dt>
            <dd>{{ order.requester_name }}</dd>
          </div>
          <div>
            <dt><CalendarDays :size="16" />Duração</dt>
            <dd>{{ tripDuration(order.departure_date, order.return_date) }} dia(s)</dd>
          </div>
        </dl>
        <div class="info-box">
          <Info :size="18" />
          <p v-if="order.status === 'aprovado'">
            Esta viagem já foi aprovada. Para preservar a regra de negócio, o pedido não pode mais
            ser cancelado.
          </p>
          <p v-else-if="order.status === 'cancelado'">
            Este pedido foi cancelado e não pode ser reaberto. Você pode criar um novo pedido quando
            precisar.
          </p>
          <p v-else-if="order.user_id === auth.user?.id">
            Seu pedido aguarda análise. Somente outro administrador pode aprová-lo ou cancelá-lo.
          </p>
          <p v-else>O solicitante será notificado por e-mail após a aprovação ou o cancelamento.</p>
        </div>
      </div>
      <footer class="modal-footer">
        <button class="button button-secondary" @click="$emit('close')">Fechar</button
        ><template v-if="canManageOrder(auth.user, order)"
          ><button
            class="button button-danger-outline"
            @click="$emit('status', order, 'cancelado')"
          >
            <X :size="16" />Cancelar pedido</button
          ><button class="button button-primary" @click="$emit('status', order, 'aprovado')">
            <Check :size="17" />Aprovar pedido
          </button></template
        >
      </footer>
    </template>
  </BaseModal>
</template>
