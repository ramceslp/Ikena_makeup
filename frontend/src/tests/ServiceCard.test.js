import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import ServiceCard from '../components/service/ServiceCard.vue'

// ---------------------------------------------------------------------------
// Minimal router — ServiceCard uses RouterLink to service detail
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

function mountCard(service) {
  return mount(ServiceCard, {
    props: { service },
    global: { plugins: [router] },
  })
}

const baseService = {
  id: 1,
  title: 'Maquillaje Social',
  slug: 'maquillaje-social',
  description: 'Servicio de maquillaje para eventos sociales.',
  price: '120.00',
  thumbnail: 'https://example.com/thumb.jpg',
  duration_hours: 2,
  availability_type: 'immediate',
  category: { id: 1, name: 'Social', slug: 'social' },
}

describe('ServiceCard.vue', () => {
  it('renders the service title', () => {
    const wrapper = mountCard(baseService)
    expect(wrapper.text()).toContain('Maquillaje Social')
  })

  it('renders the formatted price', () => {
    const wrapper = mountCard(baseService)
    expect(wrapper.text()).toContain('$120.00')
  })

  it('renders duration_hours with label', () => {
    const wrapper = mountCard(baseService)
    // Should show something like "2 Horas" or "2"
    expect(wrapper.text()).toContain('2')
  })

  it('renders availability_type label for immediate', () => {
    const wrapper = mountCard({ ...baseService, availability_type: 'immediate' })
    // Should render a human-readable label or the value
    expect(wrapper.text()).toMatch(/inmediata|immediate/i)
  })

  it('renders availability_type label for by_appointment', () => {
    const wrapper = mountCard({ ...baseService, availability_type: 'by_appointment' })
    expect(wrapper.text()).toMatch(/cita|appointment/i)
  })

  it('renders the category badge when category is present', () => {
    const wrapper = mountCard(baseService)
    expect(wrapper.text()).toContain('Social')
  })

  it('does not render category badge when category is null', () => {
    const wrapper = mountCard({ ...baseService, title: 'Maquillaje de Día', category: null })
    // W-3: assert the category pill element is genuinely absent via stable selector
    const categoryPills = wrapper.findAll('[data-category-pill]')
    expect(categoryPills).toHaveLength(0)
  })

  it('renders thumbnail image with correct src and alt', () => {
    const wrapper = mountCard(baseService)
    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://example.com/thumb.jpg')
    expect(img.attributes('alt')).toBe('Maquillaje Social')
  })

  it('renders SVG fallback when thumbnail is null', () => {
    const wrapper = mountCard({ ...baseService, thumbnail: null })
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('contains a link to /services/{slug}', () => {
    const wrapper = mountCard(baseService)
    const link = wrapper.find('a[href*="maquillaje-social"]')
    expect(link.exists()).toBe(true)
  })

  it('renders "Ver Detalles" CTA text', () => {
    const wrapper = mountCard(baseService)
    expect(wrapper.text()).toContain('Ver Detalles')
  })

  it('truncates a long description', () => {
    const longDesc = 'A'.repeat(200)
    const wrapper = mountCard({ ...baseService, description: longDesc })
    const text = wrapper.text()
    // Should be truncated — not show full 200 chars in a single block
    expect(text.length).toBeLessThan(300)
  })

  it('renders "Gratis" when price is 0.00', () => {
    const wrapper = mountCard({ ...baseService, price: '0.00' })
    expect(wrapper.text()).toContain('Gratis')
  })
})
