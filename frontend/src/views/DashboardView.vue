<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  ArrowUpRight,
  ChevronRight,
  LogOut,
  Menu,
  Plus,
  RefreshCw,
  ShieldCheck,
  Plane,
  MapPin,
  Info,
} from '@lucide/vue'
import AppSidebar from '../components/AppSidebar.vue'
import OrderStats from '../components/OrderStats.vue'
import OrderFilters from '../components/OrderFilters.vue'
import OrderTable from '../components/OrderTable.vue'
import CreateOrderModal from '../components/CreateOrderModal.vue'
import OrderDetailModal from '../components/OrderDetailModal.vue'
import ConfirmStatusModal from '../components/ConfirmStatusModal.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import NotificationBell from '../components/NotificationBell.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toasts'
import { useOrders } from '../composables/useOrders'
import { errorMessage } from '../services/api'
import { initials } from '../utils/format'
import { useRouter } from 'vue-router'
import type { FinalStatus, TravelOrder, TravelStatus } from '../types'

const auth = useAuthStore()
const toasts = useToastStore()
const router = useRouter()
const {
  filters,
  orders,
  summary,
  summaryError,
  meta,
  loading,
  error,
  activeFilters,
  load,
  loadSummary,
  reset,
  refresh,
} = useOrders()
const sidebarOpen = ref(false)
const creating = ref(false)
const detailId = ref<number | null>(null)
const pending = ref<{ order: TravelOrder; status: FinalStatus } | null>(null)
const loggingOut = ref(false)
const firstName = computed(() => auth.user?.name.split(' ')[0] || '')
const date = new Intl.DateTimeFormat('pt-BR', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
}).format(new Date())
const pageTitle = ref<HTMLElement | null>(null)

function selectStatus(status: TravelStatus | '') {
  filters.status = status
  void load()
}
function selectAction(order: TravelOrder, status: FinalStatus) {
  detailId.value = null
  pending.value = { order, status }
}
function created() {
  creating.value = false
  void refresh(1)
}
function updated() {
  pending.value = null
  void refresh()
}
async function logout() {
  if (loggingOut.value) return
  loggingOut.value = true
  try {
    await auth.logout()
    await router.replace('/login')
  } catch (err) {
    toasts.show(errorMessage(err), 'error')
  } finally {
    loggingOut.value = false
  }
}
function changePage(page: number) {
  void load(page)
  pageTitle.value?.focus()
}
onMounted(() => {
  document.title = 'Pedidos de viagem · Onfly Travel'
  void refresh(1)
})
</script>
<template>
  <a href="#main-content" class="skip-link">Pular para o conteúdo</a>
  <div class="app-layout">
    <AppSidebar :open="sidebarOpen" :is-admin="auth.isAdmin" @close="sidebarOpen = false" />
    <div class="main-layout">
      <header class="topbar">
        <div class="breadcrumb">
          <button
            class="icon-button mobile-menu"
            aria-label="Abrir menu"
            @click="sidebarOpen = true"
          >
            <Menu :size="22" /></button
          ><span>Workspace</span><ChevronRight :size="14" /><strong>Pedidos de viagem</strong>
        </div>
        <div class="topbar-right">
          <NotificationBell :is-admin="auth.isAdmin" @open-order="detailId = $event" />
          <span v-if="auth.isAdmin" class="admin-pill"
            ><ShieldCheck :size="13" />Administrador</span
          >
          <div class="topbar-user">
            <span class="avatar">{{ initials(auth.user?.name || '') }}</span
            ><span
              ><strong>{{ auth.user?.name }}</strong
              ><small>{{
                auth.isAdmin ? 'Gestão de viagens' : 'Viajante corporativo'
              }}</small></span
            >
          </div>
          <button
            class="icon-button logout-button"
            aria-label="Sair da conta"
            title="Sair da conta"
            :disabled="loggingOut"
            @click="logout"
          >
            <LoadingSpinner v-if="loggingOut" /><LogOut v-else :size="18" />
          </button>
        </div>
      </header>
      <main id="main-content" class="dashboard">
        <header class="page-heading">
          <div>
            <p class="eyebrow">SUA JORNADA COMEÇA AQUI</p>
            <h1 ref="pageTitle" tabindex="-1">
              {{
                auth.isAdmin
                  ? 'Cada viagem, novas possibilidades.'
                  : `Vamos mais longe, ${firstName}?`
              }}<span class="greeting-dot"></span>
            </h1>
            <p>
              {{
                auth.isAdmin
                  ? 'Acompanhe os pedidos da equipe e facilite os próximos destinos.'
                  : 'Planeje suas viagens e acompanhe cada etapa em um só lugar.'
              }}
            </p>
          </div>
          <span class="current-date">{{ date }}</span>
        </header>
        <section class="journey-banner" aria-label="Visão geral">
          <div class="banner-copy">
            <span class="banner-label"
              ><span></span
              >{{ auth.isAdmin ? 'GESTÃO COM MAIS LEVEZA' : 'SEU PRÓXIMO CAPÍTULO' }}</span
            >
            <h2>
              {{
                auth.isAdmin
                  ? 'Grandes jornadas começam com um sim.'
                  : 'Seu próximo destino está a um pedido de distância.'
              }}
            </h2>
            <p>
              {{
                auth.isAdmin
                  ? 'Analise as solicitações e ajude sua equipe a ir mais longe.'
                  : 'Menos tempo com processos. Mais tempo fazendo acontecer.'
              }}
            </p>
            <button class="banner-link" @click="creating = true">
              Criar um pedido de viagem<ArrowUpRight :size="16" />
            </button>
          </div>
          <div class="banner-art" aria-hidden="true">
            <span class="banner-circle circle-large"></span
            ><span class="banner-circle circle-small"></span><span class="banner-path"></span
            ><MapPin class="banner-pin" :size="29" /><Plane
              class="banner-plane"
              :size="80"
              :stroke-width="1.2"
            /><span class="banner-coordinate">19°55′ S &nbsp; 43°56′ W</span>
          </div>
        </section>
        <div class="section-eyebrow">
          <span>VISÃO GERAL</span
          ><small
            >{{ auth.isAdmin ? 'Todos os pedidos da equipe' : 'Seus pedidos' }} · independente dos
            filtros</small
          >
        </div>
        <OrderStats
          :summary="summary"
          :failed="summaryError"
          :selected="filters.status"
          @select="selectStatus"
          @retry="loadSummary"
        />
        <section class="orders-panel" aria-labelledby="orders-title">
          <header class="orders-panel-header">
            <div>
              <div class="section-title">
                <h2 id="orders-title">
                  {{ auth.isAdmin ? 'Pedidos da equipe' : 'Meus pedidos de viagem' }}
                </h2>
                <span v-if="summary" class="count-badge">{{ summary.total }}</span>
              </div>
              <p>
                {{
                  auth.isAdmin
                    ? 'Todos os destinos e decisões, organizados em um só lugar.'
                    : 'Do planejamento ao embarque, acompanhe tudo por aqui.'
                }}
              </p>
            </div>
            <div class="panel-header-actions">
              <button
                class="icon-button refresh-button"
                aria-label="Atualizar pedidos"
                title="Atualizar pedidos"
                :disabled="loading"
                @click="refresh()"
              >
                <RefreshCw :size="17" :class="{ spinning: loading }" /></button
              ><button class="button button-primary" @click="creating = true">
                <Plus :size="18" />Novo pedido
              </button>
            </div>
          </header>
          <OrderFilters
            :model-value="filters"
            :active="activeFilters"
            :busy="loading"
            @update:model-value="Object.assign(filters, $event)"
            @apply="load()"
            @reset="reset"
          />
          <OrderTable
            :orders="orders"
            :meta="meta"
            :user="auth.user"
            :loading="loading"
            :error="error"
            :filtered="activeFilters"
            @detail="detailId = $event"
            @status="selectAction"
            @retry="load(meta.current_page)"
            @create="creating = true"
            @reset="reset"
            @page="changePage"
          />
        </section>
        <div class="dashboard-note">
          <Info :size="15" />
          <p>
            {{
              auth.isAdmin
                ? 'Você pode aprovar ou cancelar pedidos solicitados de outros usuários. Pedidos próprios e já finalizados não podem ser alterados.'
                : 'Seus pedidos são privados. Apenas você e os administradores responsáveis têm acesso às suas viagens.'
            }}
          </p>
        </div>
        <footer class="dashboard-footer">
          <span>onfly<span>.</span> <small>TRAVEL</small></span>
          <p>Mais simples para você. Melhor para ir além.</p>
          <span class="footer-case">Case técnico · {{ new Date().getFullYear() }}</span>
        </footer>
      </main>
    </div>
    <CreateOrderModal v-if="creating" @close="creating = false" @created="created" />
    <OrderDetailModal
      v-if="detailId !== null"
      :key="detailId"
      :id="detailId"
      @close="detailId = null"
      @status="selectAction"
    />
    <ConfirmStatusModal
      v-if="pending"
      :order="pending.order"
      :status="pending.status"
      @close="pending = null"
      @updated="updated"
      @stale="refresh()"
    />
  </div>
</template>
