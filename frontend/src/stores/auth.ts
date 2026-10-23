import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, TOKEN_KEY } from '../services/api'
import type { User } from '../types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)
  const isAdmin = computed(() => user.value?.is_admin === true)

  function clear() {
    sessionStorage.removeItem(TOKEN_KEY)
    user.value = null
    initialized.value = true
  }

  async function initialize() {
    if (initialized.value) return
    if (sessionStorage.getItem(TOKEN_KEY)) {
      try {
        user.value = (await api.get<{ user: User }>('/auth/me')).data.user
      } catch {
        clear()
      }
    }
    initialized.value = true
  }

  async function login(email: string, password: string) {
    const { data } = await api.post<{ user: User; access_token: string }>('/auth/login', {
      email,
      password,
    })
    sessionStorage.setItem(TOKEN_KEY, data.access_token)
    user.value = data.user
    initialized.value = true
  }

  async function logout() {
    // On network failure, keep the session so logout can be retried and the JWT revoked.
    await api.post('/auth/logout')
    clear()
  }

  return { user, initialized, isAdmin, initialize, login, logout, clear }
})
