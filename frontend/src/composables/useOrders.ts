import axios from 'axios'
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { ordersService } from '../services/orders'
import { errorMessage } from '../services/api'
import type { OrderFilters, Paginated, Summary, TravelOrder } from '../types'

export function useOrders() {
  const filters = reactive<OrderFilters>({
    status: '',
    destination: '',
    date_type: 'created',
    date_from: '',
    date_to: '',
  })
  const orders = ref<TravelOrder[]>([])
  const summary = ref<Summary | null>(null)
  const summaryError = ref(false)
  const meta = ref<Paginated<TravelOrder>['meta']>({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: null,
    to: null,
  })
  const loading = ref(true)
  const error = ref('')
  const activeFilters = computed(() =>
    Boolean(filters.status || filters.destination || filters.date_from || filters.date_to),
  )
  let controller: AbortController | undefined
  let requestId = 0

  async function load(page = 1) {
    controller?.abort()
    controller = new AbortController()
    const id = ++requestId
    loading.value = true
    error.value = ''
    try {
      const result = await ordersService.list(filters, page, controller.signal)
      if (id !== requestId) return
      orders.value = result.data
      meta.value = result.meta
    } catch (err) {
      if (axios.isCancel(err) || id !== requestId) return
      error.value = errorMessage(err)
    } finally {
      if (id === requestId) loading.value = false
    }
  }

  async function loadSummary() {
    try {
      summary.value = await ordersService.summary()
      summaryError.value = false
    } catch {
      summaryError.value = true
    }
  }

  function reset() {
    Object.assign(filters, {
      status: '',
      destination: '',
      date_type: 'created',
      date_from: '',
      date_to: '',
    })
    void load()
  }

  async function refresh(page = meta.value.current_page) {
    await Promise.all([load(page), loadSummary()])
  }

  onBeforeUnmount(() => controller?.abort())
  return {
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
  }
}
