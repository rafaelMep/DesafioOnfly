export type TravelStatus = 'solicitado' | 'aprovado' | 'cancelado'
export type FinalStatus = Exclude<TravelStatus, 'solicitado'>

export interface User {
  id: number
  name: string
  email: string
  is_admin: boolean
}

export interface TravelOrder {
  id: number
  user_id: number
  requester_name: string
  destination: string
  departure_date: string
  return_date: string
  status: TravelStatus
  created_at: string
}

export interface NewOrder {
  destination: string
  departure_date: string
  return_date: string
}

export interface OrderFilters {
  status: TravelStatus | ''
  destination: string
  date_type: 'created' | 'travel'
  date_from: string
  date_to: string
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

export interface Summary {
  total: number
  solicitado: number
  aprovado: number
  cancelado: number
}

export type FieldErrors = Record<string, string[]>
