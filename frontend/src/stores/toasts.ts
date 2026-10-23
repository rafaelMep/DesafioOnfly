import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toasts', () => {
  const items = ref<{ id: number; message: string; kind: 'success' | 'error' }[]>([])
  let sequence = 0
  function remove(id: number) {
    items.value = items.value.filter((item) => item.id !== id)
  }
  function show(message: string, kind: 'success' | 'error' = 'success') {
    const id = ++sequence
    items.value.push({ id, message, kind })
    window.setTimeout(() => remove(id), kind === 'error' ? 9000 : 6000)
  }
  return { items, show, remove }
})
