<script setup lang="ts">
import { Search, SlidersHorizontal, RotateCcw, ChevronDown } from '@lucide/vue'
import { computed, ref } from 'vue'
import type { OrderFilters, TravelStatus } from '../types'
const props = defineProps<{ modelValue: OrderFilters; active: boolean; busy: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: OrderFilters]; apply: []; reset: [] }>()
const expanded = ref(false)
const rangeError = computed(
  () =>
    props.modelValue.date_from &&
    props.modelValue.date_to &&
    props.modelValue.date_to < props.modelValue.date_from,
)
function update<K extends keyof OrderFilters>(key: K, value: OrderFilters[K]) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
function submit() {
  if (!rangeError.value) emit('apply')
}
</script>
<template>
  <form class="filters" aria-label="Filtrar pedidos de viagem" @submit.prevent="submit">
    <div class="filter-main">
      <div class="search-field">
        <Search :size="18" aria-hidden="true" /><input
          :value="modelValue.destination"
          aria-label="Buscar por destino"
          placeholder="Qual destino você procura?"
          maxlength="150"
          @input="update('destination', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <div class="select-wrap">
        <label class="sr-only" for="status-filter">Status do pedido</label
        ><select
          id="status-filter"
          :value="modelValue.status"
          @change="
            update('status', ($event.target as HTMLSelectElement).value as TravelStatus | '')
          "
        >
          <option value="">Todos os status</option>
          <option value="solicitado">Solicitado</option>
          <option value="aprovado">Aprovado</option>
          <option value="cancelado">Cancelado</option></select
        ><ChevronDown :size="15" />
      </div>
      <button
        type="button"
        class="button button-secondary filter-toggle"
        :class="{ 'filter-expanded': expanded }"
        :aria-expanded="expanded"
        aria-controls="period-filters"
        @click="expanded = !expanded"
      >
        <SlidersHorizontal :size="16" />Período<span
          v-if="modelValue.date_from || modelValue.date_to"
          class="filter-dot"
        ></span>
      </button>
      <button class="button button-primary" type="submit" :disabled="busy || Boolean(rangeError)">
        Buscar
      </button>
      <button
        v-if="active"
        type="button"
        class="icon-button reset-filters"
        aria-label="Limpar filtros"
        title="Limpar filtros"
        @click="$emit('reset')"
      >
        <RotateCcw :size="17" />
      </button>
    </div>
    <div v-show="expanded" id="period-filters" class="filter-period">
      <div class="field">
        <label for="date-type">Filtrar por</label
        ><select
          id="date-type"
          :value="modelValue.date_type"
          @change="
            update('date_type', ($event.target as HTMLSelectElement).value as 'created' | 'travel')
          "
        >
          <option value="created">Data do pedido</option>
          <option value="travel">Período da viagem</option>
        </select>
      </div>
      <div class="field">
        <label for="date-from">De</label
        ><input
          id="date-from"
          type="date"
          :value="modelValue.date_from"
          @input="update('date_from', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <div class="field">
        <label for="date-to">Até</label
        ><input
          id="date-to"
          type="date"
          :value="modelValue.date_to"
          :min="modelValue.date_from || undefined"
          :aria-invalid="Boolean(rangeError)"
          :aria-describedby="rangeError ? 'range-error' : undefined"
          @input="update('date_to', ($event.target as HTMLInputElement).value)"
        />
      </div>
      <p v-if="rangeError" id="range-error" class="field-error" role="alert">
        A data final deve ser igual ou posterior à inicial.
      </p>
      <p v-else class="filter-help">
        {{
          modelValue.date_type === 'travel'
            ? 'Inclui viagens que cruzam o período selecionado.'
            : 'Considere o dia em que o pedido foi criado.'
        }}
      </p>
    </div>
  </form>
</template>
