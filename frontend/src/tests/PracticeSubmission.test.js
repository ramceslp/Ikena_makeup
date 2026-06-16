import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PracticeSubmission from '../components/player/PracticeSubmission.vue'

// Stub child atoms to avoid import errors in test environment
const stubs = {
  BaseBadge: { template: '<span><slot /></span>', props: ['variant', 'pill'] },
  BaseButton: { template: '<button :disabled="$attrs.disabled" @click="$emit(\'click\')"><slot /></button>' },
}

function mountComponent(props = {}) {
  return mount(PracticeSubmission, {
    props,
    global: { stubs },
    attachTo: document.body,
  })
}

describe('PracticeSubmission.vue', () => {
  it('renders the upload form when submission is null', () => {
    const wrapper = mountComponent({ submission: null, submitting: false, error: '' })
    expect(wrapper.text()).toContain('Foto antes')
    expect(wrapper.text()).toContain('Foto después')
    expect(wrapper.text()).toContain('Arrastra o haz clic para subir')
  })

  it('shows "En revisión" badge when submission.status is pending', () => {
    const wrapper = mountComponent({
      submission: { id: 1, status: 'pending', feedback: null, before_url: 'http://b.jpg', after_url: 'http://a.jpg', created_at: '', graded_at: null, user: { id: 1, name: 'A', avatar: null }, lesson: { id: 1, title: 'L' } },
      submitting: false,
      error: '',
    })
    expect(wrapper.text()).toContain('En revisión')
  })

  it('shows "Necesita correcciones" and feedback when status is needs_work', () => {
    const wrapper = mountComponent({
      submission: { id: 1, status: 'needs_work', feedback: 'Mejora el contorno', before_url: 'http://b.jpg', after_url: 'http://a.jpg', created_at: '', graded_at: null, user: { id: 1, name: 'A', avatar: null }, lesson: { id: 1, title: 'L' } },
      submitting: false,
      error: '',
    })
    expect(wrapper.text()).toContain('Necesita correcciones')
    expect(wrapper.text()).toContain('Mejora el contorno')
  })

  it('shows "Aprobada" badge when submission.status is approved', () => {
    const wrapper = mountComponent({
      submission: { id: 1, status: 'approved', feedback: null, before_url: 'http://b.jpg', after_url: 'http://a.jpg', created_at: '', graded_at: null, user: { id: 1, name: 'A', avatar: null }, lesson: { id: 1, title: 'L' } },
      submitting: false,
      error: '',
    })
    expect(wrapper.text()).toContain('Aprobada')
  })

  it('shows error alert when error prop is set', () => {
    const wrapper = mountComponent({ submission: null, submitting: false, error: 'Error al enviar' })
    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Error al enviar')
  })

  it('emits submit with before and after files when both are picked', async () => {
    const wrapper = mountComponent({ submission: null, submitting: false, error: '' })
    const vm = wrapper.vm

    const before = new File(['b'], 'before.jpg', { type: 'image/jpeg' })
    const after = new File(['a'], 'after.jpg', { type: 'image/jpeg' })

    // jsdom does not support setting target.files via trigger().
    // Use defineExpose to set the file refs directly, then call handleSubmit.
    const inputs = wrapper.findAll('input[type="file"]')
    expect(inputs).toHaveLength(2)

    // Manually fire change events using Object.defineProperty to set files
    const beforeInput = inputs[0].element
    const afterInput = inputs[1].element

    Object.defineProperty(beforeInput, 'files', { value: [before], configurable: true })
    Object.defineProperty(afterInput, 'files', { value: [after], configurable: true })

    await inputs[0].trigger('change')
    await inputs[1].trigger('change')

    // Call handleSubmit via exposed method
    if (vm.handleSubmit) await vm.handleSubmit()

    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeDefined()
    expect(emitted[0][0]).toMatchObject({ before: expect.any(File), after: expect.any(File) })
  })
})
