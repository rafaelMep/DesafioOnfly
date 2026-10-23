import { api } from './api'

export interface Destination {
  id: number
  name: string
  state: string
  country: string
  country_code: string
  label: string
}

export interface DestinationResults {
  data: Destination[]
  available: boolean
  source?: 'open-meteo' | 'ibge' | null
}

export const destinationsService = {
  async search(query: string, signal?: AbortSignal): Promise<DestinationResults> {
    return (await api.get<DestinationResults>('/destinations', { params: { q: query }, signal }))
      .data
  },
}
