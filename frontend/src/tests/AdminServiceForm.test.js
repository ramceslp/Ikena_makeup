import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import AdminServiceForm from '../components/admin/AdminServiceForm.vue'

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

// ---------------------------------------------------------------------------
// Router (AdminServiceForm uses router.push on success)
// ---------------------------------------------------------------------------
const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const fakeCategories = [
  { id: 1, name: 'Social', slug: 'social' },
  { id: 2, name: 'Novias', slug: 'novias' },
]

function mountForm(props = {}) {
  return mount(AdminServiceForm, {
    props: {
      categories: fakeCategories,
      ...props,
    },
    global: { plugins: [router, createPinia()] },
  })
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------

describe('AdminServiceForm.vue — render', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders title input', () => {
    const wrapper = mountForm()
    expect(wrapper.find('input[name="title"]').exists()).toBe(true)
  })

  it('renders description textarea', () => {
    const wrapper = mountForm()
    expect(wrapper.find('textarea[name="description"]').exists()).toBe(true)
  })

  it('renders price input', () => {
    const wrapper = mountForm()
    expect(wrapper.find('input[name="price"]').exists()).toBe(true)
  })

  it('renders duration_hours input', () => {
    const wrapper = mountForm()
    expect(wrapper.find('input[name="duration_hours"]').exists()).toBe(true)
  })

  it('renders availability_type select', () => {
    const wrapper = mountForm()
    expect(wrapper.find('select[name="availability_type"]').exists()).toBe(true)
  })

  it('renders category select', () => {
    const wrapper = mountForm()
    expect(wrapper.find('select[name="category_id"]').exists()).toBe(true)
  })

  it('renders is_published toggle/checkbox', () => {
    const wrapper = mountForm()
    const publishedField =
      wrapper.find('input[name="is_published"]').exists() ||
      wrapper.find('[data-published-toggle]').exists()
    expect(publishedField).toBe(true)
  })

  it('renders file input for image upload', () => {
    const wrapper = mountForm()
    const fileInput = wrapper.find('input[type="file"]')
    expect(fileInput.exists()).toBe(true)
  })

  it('renders all category options', () => {
    const wrapper = mountForm()
    const select = wrapper.find('select[name="category_id"]')
    expect(select.text()).toContain('Social')
    expect(select.text()).toContain('Novias')
  })
})

// ---------------------------------------------------------------------------
// Submit — create mode
// ---------------------------------------------------------------------------

describe('AdminServiceForm.vue — submit (create mode)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('emits submit event with FormData when form is submitted', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[name="title"]').setValue('Nuevo Servicio')
    await wrapper.find('input[name="price"]').setValue('150')
    await wrapper.find('input[name="duration_hours"]').setValue('2')

    await wrapper.find('form').trigger('submit.prevent')

    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toBeInstanceOf(FormData)
  })

  it('FormData contains title field on submit', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[name="title"]').setValue('Test Service')
    await wrapper.find('input[name="price"]').setValue('100')
    await wrapper.find('input[name="duration_hours"]').setValue('1')
    await wrapper.find('form').trigger('submit.prevent')

    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeTruthy()
    const fd = emitted[0][0]
    expect(fd.get('title')).toBe('Test Service')
  })

  it('emitted FormData does NOT contain images[] — files travel as second emit arg', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[name="title"]').setValue('Test Service')
    await wrapper.find('input[name="price"]').setValue('100')
    await wrapper.find('input[name="duration_hours"]').setValue('1')
    await wrapper.find('form').trigger('submit.prevent')

    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeTruthy()
    const fd = emitted[0][0]
    // FormData must NOT contain images[] — images go as the second emit argument
    expect(fd.has('images[]')).toBe(false)
  })

  it('emits selected files as second argument when files are chosen', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[name="title"]').setValue('Test Service')
    await wrapper.find('input[name="price"]').setValue('100')
    await wrapper.find('input[name="duration_hours"]').setValue('1')

    // Simulate file selection
    const fakeFile = new File(['content'], 'photo.jpg', { type: 'image/jpeg' })
    const fileInput = wrapper.find('input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', {
      value: [fakeFile],
      configurable: true,
    })
    await fileInput.trigger('change')

    await wrapper.find('form').trigger('submit.prevent')

    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeTruthy()
    // Second argument should be the array of selected files
    const files = emitted[0][1]
    expect(Array.isArray(files)).toBe(true)
    expect(files).toHaveLength(1)
    expect(files[0].name).toBe('photo.jpg')
  })

  it('emits empty array as second arg when no files are chosen', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[name="title"]').setValue('Test Service')
    await wrapper.find('input[name="price"]').setValue('100')
    await wrapper.find('input[name="duration_hours"]').setValue('1')
    await wrapper.find('form').trigger('submit.prevent')

    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeTruthy()
    const files = emitted[0][1]
    expect(Array.isArray(files)).toBe(true)
    expect(files).toHaveLength(0)
  })
})

// ---------------------------------------------------------------------------
// Edit mode — pre-populated fields
// ---------------------------------------------------------------------------

describe('AdminServiceForm.vue — edit mode', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  const existingService = {
    id: 3,
    title: 'Servicio Existente',
    description: 'Descripción del servicio',
    price: '200.00',
    duration_hours: 3,
    availability_type: 'by_appointment',
    category_id: 1,
    is_published: true,
    images: [
      { id: 10, url: 'https://example.com/img.jpg', sort_order: 0 },
    ],
  }

  it('pre-populates title from service prop', () => {
    const wrapper = mountForm({ service: existingService })
    const input = wrapper.find('input[name="title"]')
    expect(input.element.value).toBe('Servicio Existente')
  })

  it('pre-populates price from service prop', () => {
    const wrapper = mountForm({ service: existingService })
    const input = wrapper.find('input[name="price"]')
    expect(input.element.value).toBe('200.00')
  })

  it('pre-populates availability_type from service prop', () => {
    const wrapper = mountForm({ service: existingService })
    const select = wrapper.find('select[name="availability_type"]')
    expect(select.element.value).toBe('by_appointment')
  })

  it('renders existing images when service has images', () => {
    const wrapper = mountForm({ service: existingService })
    const existingImgs = wrapper.findAll('[data-existing-image]')
    expect(existingImgs).toHaveLength(1)
  })

  it('emits delete-image event when delete image button is clicked', async () => {
    const wrapper = mountForm({ service: existingService })
    const deleteBtn = wrapper.find('[data-delete-image]')
    expect(deleteBtn.exists()).toBe(true)

    await deleteBtn.trigger('click')

    const emitted = wrapper.emitted('delete-image')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toBe(10) // image id
  })
})
