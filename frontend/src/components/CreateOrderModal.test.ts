import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CreateOrderModal from './CreateOrderModal.vue'
import { ordersService } from '../services/orders'

vi.mock('../services/orders', () => ({ ordersService: { create: vi.fn() } }))
vi.mock('../services/destinations', () => ({
  destinationsService: { search: vi.fn().mockResolvedValue({ data: [], available: true }) },
}))

describe('CreateOrderModal', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })
  const render = () => mount(CreateOrderModal, { global: { stubs: { Teleport: true } } })

  it('shows field errors without submitting an empty form', async () => {
    const wrapper = render()
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Informe o destino da sua viagem.')
    expect(wrapper.text()).toContain('Escolha a data de ida.')
    expect(wrapper.text()).toContain('Escolha a data de volta.')
    expect(ordersService.create).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('preserves the form when the API fails and prevents double submission', async () => {
    let reject: (reason: Error) => void = () => {}
    vi.mocked(ordersService.create).mockImplementation(
      () =>
        new Promise((_, rejectPromise) => {
          reject = rejectPromise
        }),
    )
    const wrapper = render()
    await wrapper.get('#destination').setValue('Recife, PE')
    await wrapper.get('#departure').setValue('2099-01-10')
    await wrapper.get('#return').setValue('2099-01-15')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    expect(ordersService.create).toHaveBeenCalledTimes(1)
    reject(new Error('network'))
    await flushPromises()
    expect((wrapper.get('#destination').element as HTMLInputElement).value).toBe('Recife, PE')
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.emitted('created')).toBeUndefined()
    wrapper.unmount()
  })

  it('asks before discarding a partially completed form', async () => {
    const wrapper = render()
    await wrapper.get('#destination').setValue('Recife')
    const back = wrapper.findAll('button').find((button) => button.text() === 'Voltar')!
    await back.trigger('click')
    expect(wrapper.text()).toContain('Descartar este pedido?')
    expect(wrapper.emitted('close')).toBeUndefined()
    wrapper.unmount()
  })
})
