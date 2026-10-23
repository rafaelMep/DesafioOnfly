import axios from 'axios'
import type { FieldErrors } from '../types'

export const TOKEN_KEY = 'onfly.access_token'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  timeout: 15000,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (
      axios.isAxiosError(error) &&
      error.response?.status === 401 &&
      error.config?.url !== '/auth/login'
    ) {
      sessionStorage.removeItem(TOKEN_KEY)
      window.dispatchEvent(new Event('auth:expired'))
    }
    return Promise.reject(error)
  },
)

export function errorMessage(error: unknown): string {
  if (!axios.isAxiosError(error)) return 'Não foi possível concluir a operação. Tente novamente.'
  if (!error.response)
    return 'Não conseguimos conectar ao servidor. Verifique sua conexão e tente novamente.'
  if (error.response.status === 429)
    return 'Muitas tentativas em pouco tempo. Aguarde um minuto e tente novamente.'
  if (error.response.status >= 500)
    return 'O servidor está indisponível no momento. Tente novamente em instantes.'
  if (error.response.status === 422) return 'Revise os campos destacados e tente novamente.'
  if (error.response.status === 401 && error.config?.url !== '/auth/login')
    return 'Sua sessão expirou. Entre novamente para continuar.'
  return error.response.data?.message || 'Não foi possível concluir a operação.'
}

export function fieldErrors(error: unknown): FieldErrors {
  return axios.isAxiosError(error) && error.response?.status === 422
    ? error.response.data.errors || {}
    : {}
}
