<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ArrowRight, CalendarDays, Info, Plane, UserRound } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toasts'
import { ordersService } from '../services/orders'
import { errorMessage, fieldErrors } from '../services/api'
import { todayISO, tripDuration } from '../utils/format'
import type { FieldErrors } from '../types'
import BaseModal from './BaseModal.vue'
import LoadingSpinner from './LoadingSpinner.vue'
import DestinationAutocomplete from './DestinationAutocomplete.vue'

const emit = defineEmits<{ close: []; created: [] }>()
const auth = useAuthStore()
const toasts = useToastStore()
const form = reactive({ destination: '', departure_date: '', return_date: '' })
const errors = ref<FieldErrors>({})
const busy = ref(false)
const failure = ref('')
const discard = ref(false)
const today = todayISO()
const duration = computed(() =>
  form.departure_date && form.return_date && form.return_date >= form.departure_date
    ? tripDuration(form.departure_date, form.return_date)
    : null,
)
function close() {
  if (busy.value) return
  if (form.destination || form.departure_date || form.return_date) discard.value = true
  else emit('close')
}
async function submit() {
  if (busy.value) return
  errors.value = {}
  failure.value = ''
  if (!form.destination.trim()) errors.value.destination = ['Informe o destino da sua viagem.']
  else if (form.destination.trim().length > 150)
    errors.value.destination = ['Use até 150 caracteres para o destino.']
  if (!form.departure_date) errors.value.departure_date = ['Escolha a data de ida.']
  else if (form.departure_date < todayISO())
    errors.value.departure_date = ['A data de ida não pode ser anterior a hoje.']
  if (!form.return_date) errors.value.return_date = ['Escolha a data de volta.']
  else if (form.return_date < form.departure_date)
    errors.value.return_date = ['A volta deve ser na mesma data da ida ou depois dela.']
  if (Object.keys(errors.value).length) return
  busy.value = true
  try {
    await ordersService.create({ ...form, destination: form.destination.trim() })
    toasts.show('Pedido criado! Agora é só acompanhar a aprovação por aqui.')
    emit('created')
  } catch (error) {
    errors.value = fieldErrors(error)
    failure.value = errorMessage(error)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <BaseModal
    :title="discard ? 'Descartar este pedido?' : 'Qual é o seu próximo destino?'"
    :eyebrow="discard ? 'ALTERAÇÕES NÃO SALVAS' : 'NOVO PEDIDO DE VIAGEM'"
    :busy="busy"
    @close="discard ? (discard = false) : close()"
  >
    <template v-if="discard"
      ><div class="modal-body">
        <p class="muted">
          As informações preenchidas ainda não foram enviadas. Se sair agora, elas serão perdidas.
        </p>
      </div>
      <footer class="modal-footer">
        <button class="button button-secondary" data-autofocus @click="discard = false">
          Continuar preenchendo</button
        ><button class="button button-danger" @click="emit('close')">Descartar pedido</button>
      </footer></template
    >
    <form v-else novalidate @submit.prevent="submit">
      <div class="modal-body create-form">
        <p class="modal-description">
          Conte os detalhes da viagem. A gente cuida dos próximos passos.
        </p>
        <div class="requester-info">
          <span class="requester-info-icon"><UserRound :size="19" /></span>
          <div>
            <small>Solicitante</small><strong>{{ auth.user?.name }}</strong>
          </div>
          <span class="you-tag">Você</span>
        </div>
        <div class="field">
          <label for="destination">Destino <span class="required">*</span></label>
          <DestinationAutocomplete
            id="destination"
            v-model="form.destination"
            :disabled="busy"
            :invalid="Boolean(errors.destination)"
            :described-by="errors.destination ? 'destination-error' : 'destination-help'"
          />
          <p v-if="errors.destination" id="destination-error" class="field-error">
            {{ errors.destination[0] }}
          </p>
          <small v-else id="destination-help" class="field-hint"
            >Busque cidades no Brasil ou no exterior. Ex.: Belo Horizonte, Londres ou Paris.</small
          >
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="departure">Data de ida <span class="required">*</span></label
            ><input
              id="departure"
              v-model="form.departure_date"
              type="date"
              :min="today"
              :disabled="busy"
              :aria-invalid="Boolean(errors.departure_date)"
              :aria-describedby="errors.departure_date ? 'departure-error' : undefined"
            />
            <p v-if="errors.departure_date" id="departure-error" class="field-error">
              {{ errors.departure_date[0] }}
            </p>
          </div>
          <div class="field">
            <label for="return">Data de volta <span class="required">*</span></label
            ><input
              id="return"
              v-model="form.return_date"
              type="date"
              :min="form.departure_date || today"
              :disabled="busy"
              :aria-invalid="Boolean(errors.return_date)"
              :aria-describedby="errors.return_date ? 'return-error' : undefined"
            />
            <p v-if="errors.return_date" id="return-error" class="field-error">
              {{ errors.return_date[0] }}
            </p>
          </div>
        </div>
        <p v-if="duration" class="duration-hint">
          <CalendarDays :size="15" />{{ duration }}
          {{ duration === 1 ? 'dia de viagem' : 'dias de viagem' }}
        </p>
        <div class="info-box">
          <Info :size="18" />
          <p>
            Seu pedido será enviado como <strong>solicitado</strong>. Você receberá um e-mail quando
            ele for aprovado ou cancelado.
          </p>
        </div>
        <div v-if="failure" class="form-error" role="alert">{{ failure }}</div>
      </div>
      <footer class="modal-footer">
        <button type="button" class="button button-secondary" :disabled="busy" @click="close">
          Voltar</button
        ><button type="submit" class="button button-primary" :disabled="busy">
          <LoadingSpinner v-if="busy" label="Enviando pedido" /><Plane v-else :size="17" />{{
            busy ? 'Enviando pedido…' : 'Enviar pedido'
          }}<ArrowRight v-if="!busy" :size="16" />
        </button>
      </footer>
    </form>
  </BaseModal>
</template>
