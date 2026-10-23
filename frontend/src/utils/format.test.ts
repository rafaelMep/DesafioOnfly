import { describe, expect, it } from 'vitest'
import { canManageOrder, formatDate, initials, tripDuration, todayISO } from './format'
import type { TravelOrder, User } from '../types'

describe('presentation helpers', () => {
  it('formats date-only values without a timezone offset', () => {
    expect(formatDate('2027-01-10')).toBe('10/01/2027')
    expect(formatDate('2027-01-10T23:59:59Z')).toBe('10/01/2027')
  })
  it('normalizes whitespace when creating initials', () => {
    expect(initials('  Rafael  Silva ')).toBe('RS')
    expect(initials('Ana')).toBe('A')
    expect(initials('')).toBe('')
  })
  it('counts trip days inclusively', () => {
    expect(tripDuration('2027-01-10', '2027-01-10')).toBe(1)
    expect(tripDuration('2027-01-10', '2027-01-15')).toBe(6)
  })
  it('produces a local ISO date suitable for date inputs', () => {
    expect(todayISO()).toMatch(/^\d{4}-\d{2}-\d{2}$/)
  })
})

describe('action visibility', () => {
  const admin: User = { id: 1, is_admin: true, name: 'Admin', email: 'admin@example.test' }
  const order = { user_id: 2, status: 'solicitado' } as TravelOrder
  it('allows admins to manage only other users requested orders', () => {
    expect(canManageOrder(admin, order)).toBe(true)
    expect(canManageOrder({ ...admin, is_admin: false }, order)).toBe(false)
    expect(canManageOrder({ ...admin, id: 2 }, order)).toBe(false)
    expect(canManageOrder(admin, { ...order, status: 'aprovado' })).toBe(false)
    expect(canManageOrder(admin, { ...order, status: 'cancelado' })).toBe(false)
    expect(canManageOrder(null, order)).toBe(false)
  })
})
