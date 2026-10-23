<script setup lang="ts">
import { ArrowRight, ArrowDown, MapPin, SearchX, Plane, RefreshCw, CalendarDays } from '@lucide/vue'
import type { FinalStatus, Paginated, TravelOrder, User } from '../types'
import { formatDate, initials } from '../utils/format'
import StatusBadge from './StatusBadge.vue'
import OrderActions from './OrderActions.vue'

defineProps<{
  orders: TravelOrder[]
  meta: Paginated<TravelOrder>['meta']
  user: User | null
  loading: boolean
  error: string
  filtered: boolean
}>()
defineEmits<{
  detail: [id: number]
  status: [order: TravelOrder, status: FinalStatus]
  retry: []
  create: []
  reset: []
  page: [page: number]
}>()
</script>
<template>
  <div :aria-busy="loading" class="orders-content">
    <div v-if="loading" class="table-loading" role="status" aria-label="Carregando pedidos">
      <div v-for="row in 5" :key="row" class="skeleton-row">
        <span class="skeleton"></span><span class="skeleton"></span><span class="skeleton"></span
        ><span class="skeleton"></span>
      </div>
    </div>
    <div v-else-if="error" class="empty-state" role="alert">
      <span class="empty-icon"><RefreshCw :size="28" /></span>
      <h3>Não conseguimos carregar os pedidos</h3>
      <p>{{ error }}</p>
      <button class="button button-primary" @click="$emit('retry')">Tentar novamente</button>
    </div>
    <div v-else-if="!orders.length" class="empty-state">
      <span class="empty-icon"><component :is="filtered ? SearchX : Plane" :size="30" /></span>
      <h3>{{ filtered ? 'Nenhuma viagem por aqui' : 'Seu próximo destino começa aqui' }}</h3>
      <p>
        {{
          filtered
            ? 'Não encontramos pedidos com esses filtros. Que tal ajustar a busca?'
            : 'Crie seu primeiro pedido e acompanhe cada etapa da sua viagem.'
        }}
      </p>
      <button class="button button-primary" @click="filtered ? $emit('reset') : $emit('create')">
        {{ filtered ? 'Limpar filtros' : 'Criar primeiro pedido' }}
      </button>
    </div>
    <template v-else>
      <ul class="mobile-order-list" aria-label="Pedidos de viagem">
        <li v-for="order in orders" :key="order.id" class="mobile-order-card">
          <div class="mobile-order-heading">
            <button
              class="order-id"
              :aria-label="`Ver detalhes do pedido ${order.id}`"
              @click="$emit('detail', order.id)"
            >
              #{{ String(order.id).padStart(4, '0') }}</button
            ><StatusBadge :status="order.status" />
          </div>
          <button class="mobile-destination" @click="$emit('detail', order.id)">
            <MapPin :size="18" /><span>{{ order.destination }}</span>
          </button>
          <p class="mobile-trip-dates">
            <CalendarDays :size="14" />{{ formatDate(order.departure_date)
            }}<ArrowRight :size="13" />{{ formatDate(order.return_date) }}
          </p>
          <div class="mobile-order-footer">
            <span class="requester"
              ><span class="avatar avatar-small">{{ initials(order.requester_name) }}</span
              >{{ order.user_id === user?.id ? 'Você' : order.requester_name }}</span
            ><OrderActions
              :order="order"
              :user="user"
              @detail="$emit('detail', $event)"
              @status="(item, status) => $emit('status', item, status)"
            />
          </div>
        </li>
      </ul>
      <div class="table-scroll">
        <table class="orders-table">
          <caption class="sr-only">
            Pedidos de viagem, do mais recente ao mais antigo
          </caption>
          <thead>
            <tr>
              <th scope="col">Pedido <ArrowDown :size="12" /></th>
              <th scope="col">Destino</th>
              <th scope="col">Solicitante</th>
              <th scope="col">Período da viagem</th>
              <th scope="col">Status</th>
              <th scope="col" class="actions-heading">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td>
                <button
                  class="order-id"
                  :aria-label="`Ver detalhes do pedido ${order.id}`"
                  @click="$emit('detail', order.id)"
                >
                  #{{ String(order.id).padStart(4, '0') }}</button
                ><span class="cell-subtext">{{ formatDate(order.created_at) }}</span>
              </td>
              <td>
                <button class="destination-button" @click="$emit('detail', order.id)">
                  <span class="destination-icon"><MapPin :size="17" /></span
                  ><span>{{ order.destination }}<small>Viagem corporativa</small></span>
                </button>
              </td>
              <td>
                <span class="requester"
                  ><span
                    class="avatar avatar-small"
                    :class="{ 'avatar-own': user?.id === order.user_id }"
                    >{{ initials(order.requester_name) }}</span
                  ><span
                    >{{ order.requester_name
                    }}<small v-if="user?.id === order.user_id">Você</small></span
                  ></span
                >
              </td>
              <td>
                <span class="trip-dates"
                  ><CalendarDays :size="14" />{{ formatDate(order.departure_date) }}</span
                ><span class="cell-subtext return-date"
                  ><ArrowRight :size="12" />{{ formatDate(order.return_date) }}</span
                >
              </td>
              <td><StatusBadge :status="order.status" /></td>
              <td>
                <OrderActions
                  :order="order"
                  :user="user"
                  @detail="$emit('detail', $event)"
                  @status="(item, status) => $emit('status', item, status)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer class="table-footer">
        <p>
          Mostrando <strong>{{ meta.from }}–{{ meta.to }}</strong> de
          <strong>{{ meta.total }}</strong> pedidos
        </p>
        <nav class="pagination" aria-label="Paginação dos pedidos">
          <button
            class="button button-secondary button-small"
            :disabled="meta.current_page <= 1"
            aria-label="Página anterior"
            @click="$emit('page', meta.current_page - 1)"
          >
            Anterior</button
          ><span aria-live="polite"
            >{{ meta.current_page }} <span>de</span> {{ meta.last_page }}</span
          ><button
            class="button button-secondary button-small"
            :disabled="meta.current_page >= meta.last_page"
            aria-label="Próxima página"
            @click="$emit('page', meta.current_page + 1)"
          >
            Próxima
          </button>
        </nav>
      </footer>
    </template>
  </div>
</template>
