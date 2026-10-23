<script setup lang="ts">
import { BriefcaseBusiness, Clock3, CircleCheck, CircleX, ArrowUpRight } from '@lucide/vue'
import type { Summary, TravelStatus } from '../types'
defineProps<{ summary: Summary | null; failed: boolean; selected: TravelStatus | '' }>()
defineEmits<{ select: [status: TravelStatus | '']; retry: [] }>()
const cards = [
  {
    key: 'total',
    status: '' as const,
    label: 'Total de pedidos',
    caption: 'Todas as suas possibilidades',
    icon: BriefcaseBusiness,
    tone: 'blue',
  },
  {
    key: 'solicitado',
    status: 'solicitado' as const,
    label: 'Aguardando aprovação',
    caption: 'Próximos passos a caminho',
    icon: Clock3,
    tone: 'amber',
  },
  {
    key: 'aprovado',
    status: 'aprovado' as const,
    label: 'Viagens aprovadas',
    caption: 'Tudo pronto para ir além',
    icon: CircleCheck,
    tone: 'green',
  },
  {
    key: 'cancelado',
    status: 'cancelado' as const,
    label: 'Pedidos cancelados',
    caption: 'Novos planos pela frente',
    icon: CircleX,
    tone: 'slate',
  },
] as const
</script>
<template>
  <div v-if="failed" class="inline-notice">
    Não foi possível atualizar o resumo.
    <button class="text-button" @click="$emit('retry')">Tentar novamente</button>
  </div>
  <section class="stats-grid" aria-label="Resumo geral de pedidos, independente dos filtros">
    <button
      v-for="card in cards"
      :key="card.key"
      class="stat-card"
      :class="[`stat-${card.tone}`, { 'stat-selected': selected === card.status }]"
      :aria-pressed="selected === card.status"
      @click="$emit('select', card.status)"
    >
      <div class="stat-heading">
        <span>{{ card.label }}</span
        ><span class="stat-icon"><component :is="card.icon" :size="19" /></span>
      </div>
      <strong v-if="summary" class="stat-value"
        >{{ String(summary[card.key]).padStart(2, '0')
        }}<ArrowUpRight :size="19" class="stat-arrow"
      /></strong>
      <span v-else class="skeleton stat-skeleton" aria-label="Carregando resumo"></span>
      <span class="stat-caption">{{ card.caption }}</span>
    </button>
  </section>
</template>
