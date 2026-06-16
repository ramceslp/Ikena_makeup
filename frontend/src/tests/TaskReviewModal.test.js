import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TaskReviewModal from '../components/instructor/TaskReviewModal.vue'

const stubs = {
  BaseModal: {
    template: '<div v-if="modelValue"><slot /><div data-footer><slot name="footer" /></div></div>',
    props: ['modelValue', 'title'],
    emits: ['update:modelValue'],
  },
  BaseButton: {
    template: '<button :disabled="$attrs.disabled || grading" @click="$emit(\'click\')"><slot /></button>',
    props: ['variant', 'size', 'grading'],
  },
}

const makeSubmission = (overrides = {}) => ({
  id: 1,
  lesson_id: 5,
  status: 'pending',
  feedback: null,
  before_url: 'http://ex.com/before.jpg',
  after_url: 'http://ex.com/after.jpg',
  created_at: '2026-06-16T00:00:00Z',
  graded_at: null,
  user: { id: 2, name: 'Carla López', avatar: null },
  lesson: { id: 5, title: 'Contorno básico' },
  ...overrides,
})

function mountModal(props = {}) {
  return mount(TaskReviewModal, {
    props: { modelValue: true, submission: makeSubmission(), grading: false, error: '', ...props },
    global: { stubs },
    attachTo: document.body,
  })
}

describe('TaskReviewModal.vue', () => {
  it('renders student name and lesson title when open', () => {
    const wrapper = mountModal()
    expect(wrapper.text()).toContain('Carla López')
    expect(wrapper.text()).toContain('Contorno básico')
  })

  it('renders before and after images', () => {
    const wrapper = mountModal()
    const imgs = wrapper.findAll('img')
    const srcs = imgs.map((i) => i.attributes('src'))
    expect(srcs).toContain('http://ex.com/before.jpg')
    expect(srcs).toContain('http://ex.com/after.jpg')
  })

  it('emits grade with status approved when "Aprobar" is clicked', async () => {
    const wrapper = mountModal()
    // Find the Aprobar button
    const buttons = wrapper.findAll('button')
    const aprobarBtn = buttons.find((b) => b.text().includes('Aprobar'))
    expect(aprobarBtn).toBeDefined()
    await aprobarBtn.trigger('click')

    const emitted = wrapper.emitted('grade')
    expect(emitted).toBeDefined()
    expect(emitted[0][0]).toMatchObject({ status: 'approved' })
  })

  it('emits grade with status needs_work when "Necesita correcciones" is clicked', async () => {
    const wrapper = mountModal()
    const buttons = wrapper.findAll('button')
    const nwBtn = buttons.find((b) => b.text().includes('Necesita correcciones'))
    expect(nwBtn).toBeDefined()
    await nwBtn.trigger('click')

    const emitted = wrapper.emitted('grade')
    expect(emitted).toBeDefined()
    expect(emitted[0][0]).toMatchObject({ status: 'needs_work' })
  })

  it('does not render content when modelValue is false', () => {
    const wrapper = mountModal({ modelValue: false })
    expect(wrapper.text()).not.toContain('Carla López')
  })

  it('seeds feedback textarea with existing submission feedback', () => {
    const sub = makeSubmission({ feedback: 'Mejora el contorno del ojo' })
    const wrapper = mountModal({ submission: sub })
    const textarea = wrapper.find('textarea')
    expect(textarea.element.value).toBe('Mejora el contorno del ojo')
  })
})
