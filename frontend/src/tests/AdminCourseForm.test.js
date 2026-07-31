import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminCourseForm from '../components/admin/AdminCourseForm.vue'

const instructors = [
  { id: 3, name: 'Ana Ruiz' },
  { id: 5, name: 'Luis Paz' },
]
const categories = [{ id: 1, name: 'Novias' }]

const existingCourse = {
  id: 7,
  title: 'Maquillaje de Novias',
  description: 'Curso completo',
  price: '99.00',
  thumbnail: 'https://example.com/a.jpg',
  instructor_id: 3,
  category_id: 1,
  offers_certificate: true,
}

function mountForm(props = {}) {
  return mount(AdminCourseForm, {
    props: { instructors, categories, ...props },
  })
}

describe('AdminCourseForm', () => {
  it('offers every instructor in the picker', () => {
    const wrapper = mountForm()

    const options = wrapper.find('#instructor_id').findAll('option')
    expect(options.map((o) => o.text())).toEqual(['Seleccionar instructor', 'Ana Ruiz', 'Luis Paz'])
  })

  it('starts blank in create mode', () => {
    const wrapper = mountForm()

    expect(wrapper.find('#title').element.value).toBe('')
    expect(wrapper.find('#instructor_id').element.value).toBe('')
  })

  it('prefills every field from an existing course', () => {
    const wrapper = mountForm({ course: existingCourse })

    expect(wrapper.find('#title').element.value).toBe('Maquillaje de Novias')
    expect(wrapper.find('#description').element.value).toBe('Curso completo')
    expect(wrapper.find('#price').element.value).toBe('99.00')
    expect(wrapper.find('#thumbnail').element.value).toBe('https://example.com/a.jpg')
    expect(wrapper.find('#instructor_id').element.value).toBe('3')
    expect(wrapper.find('#offers_certificate').element.checked).toBe(true)
  })

  /**
   * Edit views fetch after mount, so the course prop arrives null and fills in
   * later. Without the watcher the form would stay permanently blank.
   */
  it('fills in when the course prop arrives after mount', async () => {
    const wrapper = mountForm({ course: null })

    await wrapper.setProps({ course: existingCourse })

    expect(wrapper.find('#title').element.value).toBe('Maquillaje de Novias')
  })

  it('emits the typed payload on submit', async () => {
    const wrapper = mountForm()

    await wrapper.find('#title').setValue('Cejas Perfectas')
    await wrapper.find('#description').setValue('De cero a pro')
    await wrapper.find('#price').setValue('49.5')
    await wrapper.find('#instructor_id').setValue('5')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0]).toMatchObject({
      title: 'Cejas Perfectas',
      description: 'De cero a pro',
      price: 49.5,
      instructor_id: 5,
    })
  })

  /**
   * The API validates `thumbnail` as a URL. Sending "" from an untouched
   * optional field would fail validation, so it has to go out as null.
   */
  it('sends an empty thumbnail as null', async () => {
    const wrapper = mountForm()

    await wrapper.find('#title').setValue('X')
    await wrapper.find('#description').setValue('Y')
    await wrapper.find('#instructor_id').setValue('3')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0].thumbnail).toBeNull()
  })

  it('sends an unselected category as null', async () => {
    const wrapper = mountForm()

    await wrapper.find('#title').setValue('X')
    await wrapper.find('#description').setValue('Y')
    await wrapper.find('#instructor_id').setValue('3')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0].category_id).toBeNull()
  })

  it('sends an empty price as zero', async () => {
    const wrapper = mountForm()

    await wrapper.find('#title').setValue('X')
    await wrapper.find('#description').setValue('Y')
    await wrapper.find('#instructor_id').setValue('3')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0].price).toBe(0)
  })

  it('shows server-side field errors next to their input', () => {
    const wrapper = mountForm({
      validationErrors: { instructor_id: ['The selected user is not an instructor.'] },
    })

    expect(wrapper.find('[data-error-instructor]').text()).toBe(
      'The selected user is not an instructor.',
    )
  })

  it('emits cancel without submitting', async () => {
    const wrapper = mountForm()

    await wrapper.findAll('button').find((b) => b.text() === 'Cancelar').trigger('click')

    expect(wrapper.emitted('cancel')).toBeTruthy()
    expect(wrapper.emitted('submit')).toBeFalsy()
  })
})
