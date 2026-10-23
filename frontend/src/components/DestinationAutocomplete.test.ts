import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DestinationAutocomplete from './DestinationAutocomplete.vue'
import { destinationsService } from '../services/destinations'

vi.mock('../services/destinations', () => ({ destinationsService: { search: vi.fn() } }))
const city = {
  id: 3470127,
  name: 'Belo Horizonte',
  state: 'Minas Gerais',
  country: 'Brasil',
  country_code: 'BR',
  label: 'Belo Horizonte, Minas Gerais, Brasil',
}
const render = () =>
  mount(DestinationAutocomplete, { props: { id: 'destination', modelValue: '' } })

describe('DestinationAutocomplete', () => {
  it('identifies IBGE fallback results and allows selecting them', async () => {
    const fallbackCity = {
      id: 3146107,
      name: 'Ouro Preto',
      state: 'MG',
      country: 'Brasil',
      country_code: 'BR',
      label: 'Ouro Preto, MG, Brasil',
    }
    vi.mocked(destinationsService.search).mockResolvedValue({
      data: [fallbackCity],
      available: true,
      source: 'ibge',
    })
    const wrapper = render()
    await wrapper.get('input').trigger('focus')
    await wrapper.setProps({ modelValue: 'Ouro Preto' })
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()
    expect(wrapper.get('.destination-source').text()).toContain('IBGE')
    expect(wrapper.get('[role="option"]').text()).toContain('MG · Brasil')
    await wrapper.get('[role="option"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Ouro Preto, MG, Brasil'])
    wrapper.unmount()
  })
  beforeEach(() => {
    vi.useFakeTimers()
    vi.resetAllMocks()
  })
  afterEach(() => {
    vi.useRealTimers()
  })

  it('debounces typing and selects a suggestion using the keyboard', async () => {
    vi.mocked(destinationsService.search).mockResolvedValue({ data: [city], available: true })
    const wrapper = render()
    await wrapper.get('input').trigger('focus')
    await wrapper.setProps({ modelValue: 'Be' })
    await vi.advanceTimersByTimeAsync(150)
    await wrapper.setProps({ modelValue: 'Belo' })
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()
    expect(destinationsService.search).toHaveBeenCalledTimes(1)
    expect(destinationsService.search).toHaveBeenCalledWith('Belo', expect.any(AbortSignal))
    await wrapper.get('input').trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.get('[role="option"]').attributes('aria-selected')).toBe('true')
    await wrapper.get('input').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
      'Belo Horizonte, Minas Gerais, Brasil',
    ])
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('keeps free text available when the provider is unavailable', async () => {
    vi.mocked(destinationsService.search).mockResolvedValue({ data: [], available: false })
    const wrapper = render()
    await wrapper.get('input').trigger('focus')
    await wrapper.setProps({ modelValue: 'Paris, França' })
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()
    expect(wrapper.text()).toContain('Sugestões indisponíveis')
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('Paris, França')
    expect(wrapper.get('input').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('ignores stale responses and Escape dismisses only suggestions', async () => {
    let resolveFirst: (value: { data: (typeof city)[]; available: boolean }) => void = () => {}
    vi.mocked(destinationsService.search)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveFirst = resolve
          }),
      )
      .mockResolvedValueOnce({ data: [], available: true })
    const wrapper = render()
    await wrapper.get('input').trigger('focus')
    await wrapper.setProps({ modelValue: 'Belo' })
    await vi.advanceTimersByTimeAsync(300)
    await wrapper.setProps({ modelValue: 'Paris' })
    await vi.advanceTimersByTimeAsync(300)
    resolveFirst({ data: [city], available: true })
    await flushPromises()
    expect(wrapper.find('[role="option"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Nenhuma cidade encontrada')
    await wrapper.get('input').trigger('keydown', { key: 'Escape' })
    expect(wrapper.get('input').attributes('aria-expanded')).toBe('false')
    wrapper.unmount()
  })

  it('does not search short queries and cancels work on unmount', async () => {
    const wrapper = render()
    await wrapper.setProps({ modelValue: 'B' })
    await vi.advanceTimersByTimeAsync(500)
    expect(destinationsService.search).not.toHaveBeenCalled()
    await wrapper.setProps({ modelValue: 'Belo' })
    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(500)
    expect(destinationsService.search).not.toHaveBeenCalled()
  })
})
