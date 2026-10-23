import type { TravelOrder, User } from '../types'

export function formatDate(value: string): string {
  const [year, month, day] = value.slice(0, 10).split('-')
  return `${day}/${month}/${year}`
}

export function initials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
}

export function todayISO(): string {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function canManageOrder(user: User | null, order: TravelOrder): boolean {
  return Boolean(user?.is_admin && user.id !== order.user_id && order.status === 'solicitado')
}

export function tripDuration(departure: string, arrival: string): number {
  return Math.round((Date.parse(arrival) - Date.parse(departure)) / 86400000) + 1
}
