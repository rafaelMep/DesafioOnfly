import { onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import {
  notificationsService,
  type InboxNotification,
  type NotificationInbox,
} from '../services/notifications'
import { errorMessage } from '../services/api'

export function useNotifications() {
  const unreadCount = ref<number | null>(null)
  const countUnavailable = ref(false)
  const items = ref<InboxNotification[]>([])
  const meta = ref<NotificationInbox['meta'] | null>(null)
  const loading = ref(false)
  const error = ref('')
  const unreadOnly = ref(false)
  const marking = ref(false)
  const hasNew = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  let countController: AbortController | undefined
  let listController: AbortController | undefined
  let listSequence = 0
  let disposed = false
  let loadedUnread: number | null = null

  function canPoll() {
    return !disposed && document.visibilityState !== 'hidden' && navigator.onLine
  }

  async function poll() {
    if (!canPoll()) return
    countController?.abort()
    const controller = new AbortController()
    countController = controller
    try {
      const result = await notificationsService.count(controller.signal)
      if (disposed || controller.signal.aborted) return
      unreadCount.value = result.unread_count
      countUnavailable.value = false
      hasNew.value = loadedUnread !== null && result.unread_count > loadedUnread
    } catch (err) {
      if (!disposed && !controller.signal.aborted && !axios.isCancel(err))
        countUnavailable.value = true
    } finally {
      if (!disposed && !controller.signal.aborted && countController === controller && canPoll()) {
        clearTimeout(timer)
        timer = setTimeout(poll, countUnavailable.value ? 60000 : 30000)
      }
    }
  }

  function pause() {
    clearTimeout(timer)
    countController?.abort()
  }
  function visibilityChanged() {
    pause()
    if (document.visibilityState !== 'hidden' && navigator.onLine) void poll()
  }

  async function load(page = 1) {
    pause()
    const sequence = ++listSequence
    listController?.abort()
    listController = new AbortController()
    loading.value = true
    error.value = ''
    try {
      const result = await notificationsService.list(page, unreadOnly.value, listController.signal)
      if (disposed || sequence !== listSequence) return
      items.value = result.data
      meta.value = result.meta
      unreadCount.value = result.unread_count
      loadedUnread = result.unread_count
      hasNew.value = false
      countUnavailable.value = false
    } catch (err) {
      if (!disposed && sequence === listSequence && !axios.isCancel(err))
        error.value = errorMessage(err)
    } finally {
      if (!disposed && sequence === listSequence) {
        loading.value = false
        if (document.visibilityState !== 'hidden' && navigator.onLine) {
          clearTimeout(timer)
          timer = setTimeout(poll, 30000)
        }
      }
    }
  }

  async function markRead(item: InboxNotification) {
    if (item.read_at || marking.value) return
    marking.value = true
    pause()
    try {
      const result = await notificationsService.markRead(item.id)
      if (disposed) return
      items.value = items.value.map((current) => (current.id === item.id ? result.data : current))
      unreadCount.value = result.unread_count
      loadedUnread = result.unread_count
    } finally {
      marking.value = false
      if (!disposed) void poll()
    }
  }

  async function markAllRead() {
    if (marking.value) return
    marking.value = true
    error.value = ''
    pause()
    try {
      await notificationsService.markAllRead()
      if (!disposed) await load(1)
    } catch (err) {
      if (!disposed) error.value = errorMessage(err)
    } finally {
      marking.value = false
      if (!disposed) void poll()
    }
  }

  function filter(unread: boolean) {
    unreadOnly.value = unread
    void load(1)
  }

  onMounted(() => {
    void poll()
    document.addEventListener('visibilitychange', visibilityChanged)
    window.addEventListener('online', visibilityChanged)
    window.addEventListener('offline', pause)
  })
  onBeforeUnmount(() => {
    disposed = true
    pause()
    listController?.abort()
    document.removeEventListener('visibilitychange', visibilityChanged)
    window.removeEventListener('online', visibilityChanged)
    window.removeEventListener('offline', pause)
  })

  return {
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
  }
}
