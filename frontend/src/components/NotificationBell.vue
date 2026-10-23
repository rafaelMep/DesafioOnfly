<script setup lang="ts">
import { computed, nextTick, onMounted, onBeforeUnmount, ref, useId } from 'vue'
import {
  Bell,
  CheckCheck,
  ChevronLeft,
  ChevronRight,
  Inbox,
  RefreshCw,
  X,
  ArrowUpRight,
  CircleCheck,
  CircleX,
} from '@lucide/vue'
import LoadingSpinner from './LoadingSpinner.vue'
import { useNotifications } from '../composables/useNotifications'
import { useToastStore } from '../stores/toasts'
import type { InboxNotification } from '../services/notifications'

const emit = defineEmits<{ openOrder: [id: number] }>()
withDefaults(defineProps<{ isAdmin?: boolean }>(), { isAdmin: false })
const {
  unreadCount,
  countUnavailable,
  items,
  meta,
  loading,
  error,
  unreadOnly,
  marking,
  hasNew,
  load,
  markRead,
  markAllRead,
  filter,
} = useNotifications()
const toasts = useToastStore()
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const open = ref(false)
const panelId = useId()
const label = computed(() =>
  countUnavailable.value
    ? 'Notificações — falha ao atualizar'
    : `Notificações${unreadCount.value ? `, ${unreadCount.value} não lidas` : ''}`,
)

async function toggle() {
  open.value = !open.value
  if (open.value) {
    void load()
    await nextTick()
    closeButton.value?.focus()
  }
}
function close(restoreFocus = false) {
  open.value = false
  if (restoreFocus) trigger.value?.focus()
}
function outside(event: PointerEvent) {
  if (open.value && event.target instanceof Node && !root.value?.contains(event.target)) close()
}
function keydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) {
    event.preventDefault()
    event.stopPropagation()
    close(true)
  }
}
function focusOut(event: FocusEvent) {
  if (event.relatedTarget instanceof Node && !root.value?.contains(event.relatedTarget)) close()
}
async function select(item: InboxNotification) {
  if (marking.value) return
  close(true)
  emit('openOrder', item.travel_order_id)
  try {
    await markRead(item)
  } catch {
    toasts.show(
      'O pedido foi aberto, mas não foi possível marcar a notificação como lida. Tente novamente no sininho.',
      'error',
    )
  }
}
function timestamp(value: string): string {
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}
onMounted(() => document.addEventListener('pointerdown', outside))
onBeforeUnmount(() => document.removeEventListener('pointerdown', outside))
</script>

<template>
  <div ref="root" class="notification-center" @keydown="keydown" @focusout="focusOut">
    <button
      ref="trigger"
      class="icon-button notification-trigger"
      :aria-label="label"
      :aria-expanded="open"
      :aria-controls="panelId"
      aria-haspopup="dialog"
      title="Notificações"
      @click="toggle"
    >
      <Bell :size="19" />
      <span v-if="unreadCount" class="notification-counter" aria-hidden="true">{{
        unreadCount > 99 ? '99+' : unreadCount
      }}</span>
      <span v-else-if="countUnavailable" class="notification-offline" aria-hidden="true">!</span>
    </button>
    <section
      v-if="open"
      :id="panelId"
      class="notification-panel"
      role="dialog"
      aria-label="Notificações"
      :aria-busy="loading"
    >
      <header class="notification-header">
        <div>
          <h2>Suas notificações</h2>
          <p>
            {{
              isAdmin
                ? 'Pedidos da equipe e atualizações das suas viagens.'
                : 'Acompanhe a aprovação e o cancelamento dos seus pedidos.'
            }}
          </p>
        </div>
        <button
          ref="closeButton"
          class="icon-button"
          aria-label="Fechar notificações"
          @click="close(true)"
        >
          <X :size="18" />
        </button>
      </header>
      <div class="notification-toolbar">
        <div class="notification-tabs" aria-label="Filtrar notificações">
          <button
            :aria-pressed="!unreadOnly"
            :class="{ active: !unreadOnly }"
            :disabled="marking"
            @click="filter(false)"
          >
            Todas</button
          ><button
            :aria-pressed="unreadOnly"
            :class="{ active: unreadOnly }"
            :disabled="marking"
            @click="filter(true)"
          >
            Não lidas <span v-if="unreadCount">{{ unreadCount }}</span>
          </button>
        </div>
        <button
          class="icon-button"
          aria-label="Atualizar notificações"
          :disabled="loading || marking"
          @click="load()"
        >
          <RefreshCw :size="15" />
        </button>
      </div>
      <button v-if="hasNew && !loading" class="notification-new" @click="load()">
        Há novos avisos. Atualizar a lista <RefreshCw :size="13" />
      </button>
      <div v-if="error" class="notification-error" role="alert">
        <p>{{ error }}</p>
        <button class="text-button" :disabled="loading" @click="load(meta?.current_page || 1)">
          Tentar novamente
        </button>
      </div>
      <div v-if="loading" class="notification-empty" role="status">
        <LoadingSpinner label="Carregando notificações" />
        <p>Buscando seus avisos…</p>
      </div>
      <div v-else-if="!error && !items.length" class="notification-empty">
        <Inbox :size="30" /><strong>{{
          unreadOnly ? 'Tudo em dia!' : 'Nenhuma notificação por enquanto'
        }}</strong>
        <p>
          {{
            unreadOnly
              ? 'Você não tem avisos não lidos.'
              : isAdmin
                ? 'Novos pedidos da equipe e decisões sobre suas viagens vão aparecer aqui.'
                : 'Quando seu pedido for aprovado ou cancelado, o aviso ficará salvo aqui, mesmo se você estiver offline.'
          }}
        </p>
      </div>
      <ul v-else-if="!loading" class="notification-list" aria-label="Avisos de pedidos de viagem">
        <li v-for="item in items" :key="item.id" :class="{ 'notification-unread': !item.read_at }">
          <button class="notification-item" :disabled="marking" @click="select(item)">
            <span
              class="notification-item-icon"
              :class="{
                'notification-approved': item.status === 'aprovado',
                'notification-cancelled': item.status === 'cancelado',
              }"
              ><component
                :is="
                  item.status === 'aprovado'
                    ? CircleCheck
                    : item.status === 'cancelado'
                      ? CircleX
                      : Bell
                "
                :size="16" /></span
            ><span class="notification-content"
              ><span class="notification-item-title"
                >{{ item.title
                }}<span v-if="!item.read_at" class="unread-dot" aria-label="Não lida"></span></span
              ><span class="notification-description"
                ><template v-if="item.kind === 'travel_order_status_changed'"
                  >Seu pedido de viagem para <strong>{{ item.destination }}</strong> foi
                  <strong>{{ item.status }}</strong
                  >.</template
                ><template v-else
                  ><strong>{{ item.requester_name }}</strong> solicitou uma viagem para
                  <strong>{{ item.destination }}</strong
                  >.</template
                ></span
              ><span class="notification-date"
                >Pedido #{{ item.travel_order_id }} · {{ timestamp(item.created_at) }}</span
              ><span class="notification-link">Ver pedido <ArrowUpRight :size="12" /></span
            ></span>
          </button>
        </li>
      </ul>
      <nav
        v-if="meta && meta.last_page > 1"
        class="notification-pagination"
        aria-label="Paginação de notificações"
      >
        <button
          class="icon-button"
          aria-label="Notificações anteriores"
          :disabled="loading || marking || meta.current_page <= 1"
          @click="load(meta.current_page - 1)"
        >
          <ChevronLeft :size="17" /></button
        ><span>Página {{ meta.current_page }} de {{ meta.last_page }}</span
        ><button
          class="icon-button"
          aria-label="Próxima página de notificações"
          :disabled="loading || marking || meta.current_page >= meta.last_page"
          @click="load(meta.current_page + 1)"
        >
          <ChevronRight :size="17" />
        </button>
      </nav>
      <footer class="notification-footer">
        <span>Atualização a cada 30s com a aba ativa</span
        ><button
          class="text-button"
          :disabled="!unreadCount || marking || loading"
          @click="markAllRead"
        >
          <LoadingSpinner v-if="marking" /><CheckCheck v-else :size="15" />Marcar todas como lidas
        </button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.notification-center {
  position: relative;
}
.notification-trigger {
  position: relative;
  width: 38px;
  height: 38px;
  color: #6b829e;
}
.notification-counter {
  position: absolute;
  right: -4px;
  top: -3px;
  background: #0875ef;
  color: #fff;
  border: 2px solid #fff;
  border-radius: 12px;
  padding: 2px 4px;
  min-width: 19px;
  font-size: 9px;
  line-height: 1.2;
  font-weight: 700;
}
.notification-offline {
  position: absolute;
  top: 0;
  right: 0;
  background: #fbefcb;
  color: #846315;
  border-radius: 50%;
  padding: 1px 4px;
  font-size: 9px;
}
.notification-panel {
  position: absolute;
  z-index: 45;
  top: 49px;
  right: -8px;
  width: 390px;
  max-height: min(640px, calc(100dvh - 100px));
  display: flex;
  flex-direction: column;
  background: white;
  border: 1px solid #e1e9f3;
  border-radius: 13px;
  box-shadow: 0 16px 50px #132d4a26;
  overflow: hidden;
}
.notification-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 19px 18px 16px;
}
.notification-header h2 {
  color: #2c4665;
  font-size: 16px;
  letter-spacing: -0.4px;
}
.notification-header p {
  color: #637b97;
  font-size: 11px;
  margin-top: 7px;
}
.notification-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 13px 10px 18px;
  border-bottom: 1px solid #edf1f6;
}
.notification-tabs {
  display: flex;
  gap: 5px;
}
.notification-tabs button {
  font-size: 11px;
  padding: 8px 10px;
  border-radius: 6px;
  color: #69809c;
}
.notification-tabs .active {
  background: #edf5ff;
  color: #146ed0;
  font-weight: 600;
}
.notification-tabs span {
  font-size: 10px;
  margin-left: 4px;
}
.notification-new {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 11px;
  background: #eef6ff;
  color: #276ebc;
  padding: 12px;
  flex-shrink: 0;
}
.notification-list {
  list-style: none;
  padding: 0;
  margin: 0;
  overflow-y: auto;
  min-height: 0;
}
.notification-list li {
  border-bottom: 1px solid #edf2f8;
}
.notification-list li:last-child {
  border-bottom: 0;
}
.notification-unread {
  background: #f5f9ff;
}
.notification-item {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  text-align: left;
  padding: 16px 18px;
}
.notification-item:hover {
  background: #edf5ff;
}
.notification-item-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 32px;
  background: #e9f2ff;
  border-radius: 8px;
  color: #6295ce;
  flex-shrink: 0;
}
.notification-item-icon.notification-approved {
  background: #e8f7ef;
  color: #28805b;
}
.notification-item-icon.notification-cancelled {
  background: #fff0f3;
  color: #b74a61;
}
.notification-content {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
}
.notification-item-title {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  color: #365575;
  font-weight: 650;
}
.unread-dot {
  display: inline-block;
  height: 6px;
  width: 6px;
  border-radius: 50%;
  background: #0875ef;
  flex-shrink: 0;
}
.notification-description {
  font-size: 12px;
  color: #637b96;
  line-height: 1.7;
  overflow-wrap: anywhere;
}
.notification-description strong {
  font-weight: 550;
}
.notification-date {
  font-size: 10px;
  color: #6a809a;
}
.notification-link {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #2476d5;
  font-size: 10px;
  font-weight: 600;
  margin-top: 2px;
}
.notification-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 13px;
  padding: 35px 25px;
  min-height: 190px;
  text-align: center;
  color: #7497bf;
}
.notification-empty strong {
  font-size: 12px;
  color: #506d8d;
}
.notification-empty p {
  font-size: 12px;
  color: #657e9b;
  line-height: 1.7;
}
.notification-error {
  padding: 13px 18px;
  background: #fff5f6;
  color: #ad4155;
  font-size: 11px;
  line-height: 1.7;
  flex-shrink: 0;
}
.notification-error .text-button {
  margin-top: 5px;
}
.notification-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 8px 12px;
  border-top: 1px solid #edf1f6;
  flex-shrink: 0;
  font-size: 11px;
  color: #647c98;
}
.notification-footer {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 9px;
  padding: 13px;
  border-top: 1px solid #edf1f6;
  flex-shrink: 0;
}
.notification-footer > span {
  font-size: 10px;
  color: #6a809b;
}
.notification-footer .text-button {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
}
@media (max-width: 760px) {
  .notification-panel {
    position: fixed;
    top: 72px;
    left: 12px;
    right: 12px;
    width: auto;
    max-height: calc(100dvh - 88px);
  }
}
</style>
