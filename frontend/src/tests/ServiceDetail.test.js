import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import ServiceGallery from '../components/service/ServiceGallery.vue'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import ServiceDetail from '../views/ServiceDetail.vue'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const fakeImages = [
  { id: 1, url: 'https://example.com/img0.jpg', sort_order: 0 },
  { id: 2, url: 'https://example.com/img1.jpg', sort_order: 1 },
  { id: 3, url: 'https://example.com/img2.jpg', sort_order: 2 },
]

function mountGallery(images = fakeImages) {
  return mount(ServiceGallery, {
    props: { images },
  })
}

// ---------------------------------------------------------------------------
// Gallery renders images in order
// ---------------------------------------------------------------------------

describe('ServiceGallery.vue — renders images in sort_order', () => {
  it('renders main image as the first image by default', () => {
    const wrapper = mountGallery()
    const mainImg = wrapper.find('[data-main-image]')
    expect(mainImg.exists()).toBe(true)
    expect(mainImg.attributes('src')).toBe('https://example.com/img0.jpg')
  })

  it('renders thumbnails for all images', () => {
    const wrapper = mountGallery()
    const thumbs = wrapper.findAll('[data-thumbnail]')
    expect(thumbs).toHaveLength(3)
  })

  it('thumbnail srcs match image URLs in sort_order', () => {
    const wrapper = mountGallery()
    const thumbs = wrapper.findAll('[data-thumbnail]')
    expect(thumbs[0].attributes('src')).toBe('https://example.com/img0.jpg')
    expect(thumbs[1].attributes('src')).toBe('https://example.com/img1.jpg')
    expect(thumbs[2].attributes('src')).toBe('https://example.com/img2.jpg')
  })

  it('clicking a thumbnail changes the main image', async () => {
    const wrapper = mountGallery()
    const thumbs = wrapper.findAll('[data-thumbnail]')
    await thumbs[1].trigger('click')

    const mainImg = wrapper.find('[data-main-image]')
    expect(mainImg.attributes('src')).toBe('https://example.com/img1.jpg')
  })

  it('clicking next chevron advances to second image', async () => {
    const wrapper = mountGallery()
    const nextBtn = wrapper.find('[data-gallery-next]')
    expect(nextBtn.exists()).toBe(true)

    await nextBtn.trigger('click')

    const mainImg = wrapper.find('[data-main-image]')
    expect(mainImg.attributes('src')).toBe('https://example.com/img1.jpg')
  })

  it('clicking prev chevron wraps to last image from first', async () => {
    const wrapper = mountGallery()
    const prevBtn = wrapper.find('[data-gallery-prev]')
    expect(prevBtn.exists()).toBe(true)

    await prevBtn.trigger('click')

    const mainImg = wrapper.find('[data-main-image]')
    // Should wrap around to the last image
    expect(mainImg.attributes('src')).toBe('https://example.com/img2.jpg')
  })

  it('renders empty state gracefully when no images are provided', () => {
    const wrapper = mountGallery([])
    // Should not crash — might render a placeholder
    expect(wrapper.exists()).toBe(true)
  })

  // W-5: single-image case — main renders, no thumbnail strip, no prev/next chevrons
  it('with exactly ONE image: renders main image, no thumbnails, no prev/next chevrons', () => {
    const singleImage = [{ id: 1, url: 'https://example.com/only.jpg', sort_order: 0 }]
    const wrapper = mountGallery(singleImage)

    const mainImg = wrapper.find('[data-main-image]')
    expect(mainImg.exists()).toBe(true)
    expect(mainImg.attributes('src')).toBe('https://example.com/only.jpg')

    // No thumbnail strip when only one image
    const thumbs = wrapper.findAll('[data-thumbnail]')
    expect(thumbs).toHaveLength(0)

    // No navigation chevrons when only one image
    expect(wrapper.find('[data-gallery-next]').exists()).toBe(false)
    expect(wrapper.find('[data-gallery-prev]').exists()).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// ServiceDetail.vue — booking integration (Phase 13)
// ---------------------------------------------------------------------------

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/services/:slug', name: 'ServiceDetail', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

const byAppointmentService = {
  id: 1,
  title: 'Maquillaje Social',
  slug: 'maquillaje-social',
  price: '150.00',
  deposit_percentage: 30,
  availability_type: 'by_appointment',
  description: 'Maquillaje profesional',
  duration_hours: 2,
  images: [],
  category: null,
  is_published: true,
}

const immediateService = {
  id: 2,
  title: 'Masterclass',
  slug: 'masterclass',
  price: '200.00',
  deposit_percentage: 50,
  availability_type: 'immediate',
  description: 'Masterclass intensiva',
  duration_hours: 4,
  images: [],
  category: null,
  is_published: true,
}

function mountServiceDetail() {
  return mount(ServiceDetail, {
    global: {
      plugins: [router, createPinia()],
    },
  })
}

describe('ServiceDetail.vue — booking section integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders SlotPicker and BookingForm for by_appointment service', async () => {
    api.get.mockResolvedValueOnce({ data: { data: byAppointmentService } })
    // fetchAvailableSlots
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mountServiceDetail()
    await flushPromises()

    expect(wrapper.find('[data-booking-section]').exists()).toBe(true)
    expect(wrapper.find('[data-slot-picker]').exists()).toBe(true)
  })

  it('does NOT render booking section for immediate service', async () => {
    api.get.mockResolvedValueOnce({ data: { data: immediateService } })

    const wrapper = mountServiceDetail()
    await flushPromises()

    expect(wrapper.find('[data-booking-section]').exists()).toBe(false)
  })

  it('no longer shows "próximamente" disabled button for by_appointment service', async () => {
    api.get.mockResolvedValueOnce({ data: { data: byAppointmentService } })
    api.get.mockResolvedValueOnce({ data: { data: [] } })

    const wrapper = mountServiceDetail()
    await flushPromises()

    expect(wrapper.text()).not.toContain('próximamente')
  })
})
