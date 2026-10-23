import { api } from './api'
import type { FinalStatus, Paginated } from '../types'

export interface InboxNotification {
  id: string
  kind: 'travel_order_requested' | 'travel_order_status_changed'
  status: FinalStatus | null
  travel_order_id: number
  title: string
  requester_name: string
  destination: string
  read_at: string | null
  created_at: string
}

export interface NotificationInbox extends Paginated<InboxNotification> {
  unread_count: number
}

export const notificationsService = {
  async count(signal?: AbortSignal) {
    return (await api.get<{ unread_count: number }>('/notifications/unread-count', { signal })).data
  },
  async list(page = 1, unreadOnly = false, signal?: AbortSignal) {
    return (
      await api.get<NotificationInbox>('/notifications', {
        params: { page, unread_only: unreadOnly ? 1 : 0 },
        signal,
      })
    ).data
  },
  async markRead(id: string) {
    return (
      await api.patch<{ data: InboxNotification; unread_count: number }>(
        `/notifications/${id}/read`,
      )
    ).data
  },
  async markAllRead() {
    return (await api.patch<{ unread_count: number }>('/notifications/read-all')).data
  },
}
