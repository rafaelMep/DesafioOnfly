import { api } from './api'
import type { FinalStatus, NewOrder, OrderFilters, Paginated, Summary, TravelOrder } from '../types'

export const ordersService = {
  async list(filters: OrderFilters, page: number, signal?: AbortSignal) {
    const params = Object.fromEntries(
      Object.entries({ ...filters, page }).filter(([, value]) => value !== ''),
    )
    return (await api.get<Paginated<TravelOrder>>('/travel-orders', { params, signal })).data
  },
  async summary() {
    return (await api.get<{ data: Summary }>('/travel-orders/summary')).data.data
  },
  async create(payload: NewOrder) {
    return (await api.post<{ data: TravelOrder }>('/travel-orders', payload)).data.data
  },
  async show(id: number) {
    return (await api.get<{ data: TravelOrder }>(`/travel-orders/${id}`)).data.data
  },
  async updateStatus(id: number, status: FinalStatus) {
    return (await api.patch<{ data: TravelOrder }>(`/travel-orders/${id}/status`, { status })).data
      .data
  },
}
