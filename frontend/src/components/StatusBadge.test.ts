import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import StatusBadge from './StatusBadge.vue'

describe('StatusBadge', () => {
  it.each([
    ['solicitado', 'Solicitado'],
    ['aprovado', 'Aprovado'],
    ['cancelado', 'Cancelado'],
  ] as const)('renders %s with text, not color alone', (status, label) => {
    const wrapper = mount(StatusBadge, { props: { status } })
    expect(wrapper.text()).toBe(label)
    expect(wrapper.classes()).toContain(`status-${status}`)
  })
})
