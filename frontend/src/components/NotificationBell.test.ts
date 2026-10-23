import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import NotificationBell from './NotificationBell.vue'
import { notificationsService, type InboxNotification } from '../services/notifications'

vi.mock('../services/notifications', () => ({
  notificationsService: { count: vi.fn(), list: vi.fn(), markRead: vi.fn(), markAllRead: vi.fn() },
}))

const item: InboxNotification = {
  id: 'notification-1',
  kind: 'travel_order_requested',
  status: null,
  travel_order_id: 42,
  title: 'Novo pedido de viagem',
  requester_name: 'Rafael Silva',
  destination: 'Ouro Preto, MG, Brasil',
  read_at: null,
  created_at: '2026-10-23T12:00:00Z',
}
const inbox = {
  data: [item],
  unread_count: 2,
  meta: { current_page: 1, last_page: 1, total: 1, per_page: 20, from: 1, to: 1 },
}
const render = () => mount(NotificationBell)

describe('NotificationBell', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.resetAllMocks()
    setActivePinia(createPinia())
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true)
    vi.mocked(notificationsService.count).mockResolvedValue({ unread_count: 2 })
    vi.mocked(notificationsService.list).mockResolvedValue(inbox)
    vi.mocked(notificationsService.markRead).mockResolvedValue({
      data: { ...item, read_at: '2026-10-23T13:00:00Z' },
      unread_count: 1,
    })
    vi.mocked(notificationsService.markAllRead).mockResolvedValue({ unread_count: 0 })
  })
  afterEach(() => {
    vi.restoreAllMocks()
    vi.useRealTimers()
  })

  it('loads the unread count and opens a persistent inbox', async () => {
    const wrapper = render()
    await flushPromises()
    expect(wrapper.get('.notification-trigger').attributes('aria-label')).toContain('2 não lidas')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    await wrapper.get('.notification-trigger').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="dialog"]').text()).toContain('Ouro Preto, MG, Brasil')
    expect(notificationsService.list).toHaveBeenCalledWith(1, false, expect.any(AbortSignal))
    wrapper.unmount()
  })

  it.each(['aprovado', 'cancelado'] as const)(
    'shows the traveler their %s decision instead of a new-request message',
    async (status) => {
      const decision: InboxNotification = {
        ...item,
        kind: 'travel_order_status_changed',
        status,
        title: status === 'aprovado' ? 'Pedido aprovado' : 'Pedido cancelado',
      }
      vi.mocked(notificationsService.list).mockResolvedValue({ ...inbox, data: [decision] })
      const wrapper = render()
      await wrapper.get('.notification-trigger').trigger('click')
      await flushPromises()
      expect(wrapper.get('.notification-description').text()).toContain(`foi ${status}`)
      expect(wrapper.get('.notification-item-title').text()).toContain(decision.title)
      expect(wrapper.get('.notification-description').text()).not.toContain('solicitou uma viagem')
      expect(wrapper.find('.notification-header').text()).toContain('seus pedidos')
      wrapper.unmount()
    },
  )

  it('opens the associated order and marks only that notification as read', async () => {
    const wrapper = render()
    await wrapper.get('.notification-trigger').trigger('click')
    await flushPromises()
    vi.mocked(notificationsService.count).mockResolvedValue({ unread_count: 1 })
    await wrapper.get('.notification-item').trigger('click')
    await flushPromises()
    expect(wrapper.emitted('openOrder')).toEqual([[42]])
    expect(notificationsService.markRead).toHaveBeenCalledWith('notification-1')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.get('.notification-trigger').attributes('aria-label')).toContain('1 não lidas')
    wrapper.unmount()
  })

  it('pauses polling while hidden and resumes on return, cleaning timers on unmount', async () => {
    const wrapper = render()
    await flushPromises()
    expect(notificationsService.count).toHaveBeenCalledTimes(1)
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden')
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(90000)
    expect(notificationsService.count).toHaveBeenCalledTimes(1)
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(notificationsService.count).toHaveBeenCalledTimes(2)
    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(90000)
    expect(notificationsService.count).toHaveBeenCalledTimes(2)
  })

  it('shows a recoverable list error without affecting the dashboard', async () => {
    vi.mocked(notificationsService.list).mockRejectedValueOnce(new Error('network'))
    const wrapper = render()
    await wrapper.get('.notification-trigger').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Tentar novamente')
    const retry = wrapper.findAll('button').find((button) => button.text() === 'Tentar novamente')!
    await retry.trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.get('.notification-list').text()).toContain('Novo pedido de viagem')
    wrapper.unmount()
  })

  it('marks all read and keeps the inbox history visible', async () => {
    const wrapper = render()
    await wrapper.get('.notification-trigger').trigger('click')
    await flushPromises()
    vi.mocked(notificationsService.list).mockResolvedValue({
      ...inbox,
      data: [{ ...item, read_at: '2026-10-23T13:00:00Z' }],
      unread_count: 0,
    })
    vi.mocked(notificationsService.count).mockResolvedValue({ unread_count: 0 })
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Marcar todas como lidas'))!
      .trigger('click')
    await flushPromises()
    expect(notificationsService.markAllRead).toHaveBeenCalledTimes(1)
    expect(wrapper.find('.notification-unread').exists()).toBe(false)
    expect(wrapper.get('.notification-list').text()).toContain('Ouro Preto')
    wrapper.unmount()
  })

  it('still opens the order if marking the notification fails', async () => {
    vi.mocked(notificationsService.markRead).mockRejectedValue(new Error('network'))
    const wrapper = render()
    await wrapper.get('.notification-trigger').trigger('click')
    await flushPromises()
    await wrapper.get('.notification-item').trigger('click')
    await flushPromises()
    expect(wrapper.emitted('openOrder')).toEqual([[42]])
    expect(wrapper.get('.notification-trigger').attributes('aria-label')).toContain('2 não lidas')
    wrapper.unmount()
  })

  it('polls every 30 seconds after completion without overlapping requests', async () => {
    let complete: (value: { unread_count: number }) => void = () => {}
    vi.mocked(notificationsService.count).mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          complete = resolve
        }),
    )
    const wrapper = render()
    await vi.advanceTimersByTimeAsync(90000)
    expect(notificationsService.count).toHaveBeenCalledTimes(1)
    complete({ unread_count: 2 })
    await flushPromises()
    await vi.advanceTimersByTimeAsync(29999)
    expect(notificationsService.count).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(1)
    expect(notificationsService.count).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('backs off after a polling failure and recovers without losing the session', async () => {
    vi.mocked(notificationsService.count).mockRejectedValueOnce(new Error('network'))
    const wrapper = render()
    await flushPromises()
    expect(wrapper.get('.notification-trigger').attributes('aria-label')).toContain(
      'falha ao atualizar',
    )
    await vi.advanceTimersByTimeAsync(30000)
    expect(notificationsService.count).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(30000)
    expect(notificationsService.count).toHaveBeenCalledTimes(2)
    expect(wrapper.get('.notification-trigger').attributes('aria-label')).toContain('2 não lidas')
    wrapper.unmount()
  })
})
