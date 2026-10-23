<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { MapPin, ArrowUpLeft } from '@lucide/vue'
import LoadingSpinner from './LoadingSpinner.vue'
import {
  destinationsService,
  type Destination,
  type DestinationResults,
} from '../services/destinations'

const props = defineProps<{
  id: string
  modelValue: string
  disabled?: boolean
  invalid?: boolean
  describedBy?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const results = ref<Destination[]>([])
const focused = ref(false)
const open = ref(false)
const loading = ref(false)
const available = ref(true)
const source = ref<DestinationResults['source']>(null)
const active = ref(-1)
const expanded = computed(() => focused.value && open.value && props.modelValue.trim().length >= 2)
const listId = computed(() => `${props.id}-suggestions`)
const cache = new Map<string, DestinationResults>()
let controller: AbortController | undefined
let timer: ReturnType<typeof setTimeout> | undefined
let generation = 0
let selectedLabel = ''

function cancel() {
  clearTimeout(timer)
  controller?.abort()
  generation++
  loading.value = false
}

function schedule(value: string) {
  cancel()
  active.value = -1
  results.value = []
  source.value = null
  open.value = false
  const query = value.trim()
  if (query.length < 2 || value === selectedLabel || props.disabled) return
  open.value = true
  loading.value = true
  const requestId = generation
  timer = setTimeout(async () => {
    controller = new AbortController()
    try {
      const response =
        cache.get(query) || (await destinationsService.search(query, controller.signal))
      if (requestId !== generation) return
      results.value = response.data
      available.value = response.available
      source.value = response.source
      if (response.available) {
        if (cache.size >= 30) cache.delete(cache.keys().next().value!)
        cache.set(query, response)
      }
    } catch (error) {
      if (axios.isCancel(error) || requestId !== generation) return
      available.value = false
    } finally {
      if (requestId === generation) loading.value = false
    }
  }, 300)
}

function select(city: Destination) {
  cancel()
  selectedLabel = city.label
  open.value = false
  emit('update:modelValue', city.label)
}

function input(event: Event) {
  selectedLabel = ''
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

function blur() {
  focused.value = false
  open.value = false
}

function keydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && expanded.value) {
    event.preventDefault()
    event.stopPropagation()
    cancel()
    open.value = false
    return
  }
  if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && results.value.length) {
    event.preventDefault()
    open.value = true
    active.value =
      (active.value + (event.key === 'ArrowDown' ? 1 : -1) + results.value.length) %
      results.value.length
    void nextTick(() =>
      document
        .getElementById(`${listId.value}-${active.value}`)
        ?.scrollIntoView?.({ block: 'nearest' }),
    )
  }
  if (event.key === 'Enter' && expanded.value && active.value >= 0) {
    event.preventDefault()
    select(results.value[active.value]!)
  }
  if (event.key === 'Tab') open.value = false
}

watch(() => props.modelValue, schedule)
onBeforeUnmount(cancel)
</script>

<template>
  <div class="destination-autocomplete">
    <div class="input-icon">
      <MapPin :size="18" />
      <input
        :id="id"
        :value="modelValue"
        data-autofocus
        role="combobox"
        aria-autocomplete="list"
        aria-haspopup="listbox"
        :aria-expanded="expanded"
        :aria-controls="listId"
        :aria-activedescendant="expanded && active >= 0 ? `${listId}-${active}` : undefined"
        :aria-invalid="invalid"
        :aria-describedby="describedBy"
        placeholder="Busque uma cidade ou digite o destino"
        maxlength="150"
        autocomplete="off"
        :disabled="disabled"
        @input="input"
        @keydown="keydown"
        @focus="focused = true"
        @blur="blur"
      />
      <LoadingSpinner
        v-if="loading && focused"
        class="destination-search-spinner"
        label="Buscando cidades"
      />
    </div>
    <div v-if="expanded" class="destination-dropdown">
      <ul :id="listId" role="listbox" aria-label="Cidades sugeridas" :aria-busy="loading">
        <li
          v-for="(city, index) in results"
          :id="`${listId}-${index}`"
          :key="city.id"
          role="option"
          :aria-selected="active === index"
          :class="{ 'destination-option-active': active === index }"
          @mousedown.prevent
          @click="select(city)"
          @mousemove="active = index"
        >
          <span class="destination-result-icon"><MapPin :size="16" /></span
          ><span
            ><strong>{{ city.name }}</strong
            ><small>{{ city.state ? `${city.state} · ` : '' }}{{ city.country }}</small></span
          ><ArrowUpLeft :size="15" />
        </li>
      </ul>
      <p class="destination-search-status" role="status">
        <template v-if="loading">Buscando cidades…</template>
        <template v-else-if="!available"
          >Sugestões indisponíveis. Você pode digitar o destino normalmente.</template
        >
        <template v-else-if="!results.length"
          >Nenhuma cidade encontrada. Você pode usar o destino digitado.</template
        >
        <template v-else
          >{{ results.length }} sugestões · Use ↑ ↓ e Enter para selecionar.</template
        >
      </p>
      <div class="destination-source">
        <span v-if="source === 'ibge'"
          >Dados:
          <a
            href="https://servicodados.ibge.gov.br/api/docs/localidades"
            target="_blank"
            rel="noopener noreferrer"
            >IBGE</a
          ></span
        >
        <span v-else
          >Dados:
          <a
            href="https://open-meteo.com/en/docs/geocoding-api"
            target="_blank"
            rel="noopener noreferrer"
            >Open-Meteo</a
          >
          /
          <a href="https://www.geonames.org/" target="_blank" rel="noopener noreferrer"
            >GeoNames</a
          ></span
        >
        <span>{{
          source === 'ibge'
            ? 'Sugestões brasileiras · Texto livre aceito'
            : 'Brasil e exterior · Texto livre aceito'
        }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.destination-autocomplete {
  position: relative;
}
.destination-autocomplete input {
  padding-right: 40px;
}
.destination-search-spinner {
  position: absolute;
  top: 14px;
  right: 13px;
  color: #548dcb;
  width: 16px;
  height: 16px;
}
.destination-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  z-index: 5;
  background: white;
  border: 1px solid #dce7f4;
  border-radius: 10px;
  box-shadow: 0 12px 30px #152e4b22;
  overflow: hidden;
}
.destination-dropdown ul {
  list-style: none;
  padding: 5px;
  margin: 0;
  max-height: 210px;
  overflow-y: auto;
}
.destination-dropdown li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  border-radius: 6px;
  cursor: pointer;
}
.destination-dropdown li:hover,
.destination-option-active {
  background: #eff6ff;
}
.destination-result-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 32px;
  color: #6395cd;
  background: #f2f7fd;
  border-radius: 7px;
  flex-shrink: 0;
}
.destination-dropdown strong {
  display: block;
  font-size: 12px;
  color: #3c5878;
  font-weight: 600;
}
.destination-dropdown small {
  display: block;
  font-size: 10px;
  color: #667e99;
  margin-top: 4px;
}
.destination-dropdown li > svg {
  margin-left: auto;
  color: #8aa5c5;
}
.destination-search-status {
  padding: 9px 14px;
  font-size: 11px;
  line-height: 1.6;
  color: #637c98;
}
.destination-source {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 5px;
  border-top: 1px solid #edf1f7;
  padding: 10px 14px;
  font-size: 10px;
  color: #6c829d;
  background: #fafcfe;
}
.destination-source span {
  color: #64758c;
}
.destination-source a {
  color: #366faa;
  text-decoration: underline;
  text-underline-offset: 2px;
}
</style>
