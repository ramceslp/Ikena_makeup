import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AttendanceRoster from '../components/AttendanceRoster.vue'
import { useInstructorStore } from '../stores/instructor.js'

const roster = [
  { id: 1, name: 'Ana Ruiz', email: 'ana@example.com', attended: true },
  { id: 2, name: 'Beto Paz', email: 'beto@example.com', attended: false },
]

function mountRoster() {
  const store = useInstructorStore()
  store.fetchAttendance = vi.fn().mockImplementation(async () => {
    store.attendance = { 7: roster }
    return roster
  })
  store.saveAttendance = vi.fn().mockResolvedValue(roster)

  const wrapper = mount(AttendanceRoster, { props: { lessonId: 7 } })
  return { wrapper, store }
}

async function open(wrapper) {
  await wrapper.find('button[aria-expanded]').trigger('click')
  await flushPromises()
}

describe('AttendanceRoster', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  /**
   * A course can have twenty sessions; fetching every roster on mount would be
   * twenty requests for a screen the instructor may only be editing.
   */
  it('does not fetch until opened', () => {
    const { store } = mountRoster()

    expect(store.fetchAttendance).not.toHaveBeenCalled()
  })

  it('loads the roster on first open only', async () => {
    const { wrapper, store } = mountRoster()

    await open(wrapper)
    await wrapper.find('button[aria-expanded]').trigger('click')
    await open(wrapper)

    expect(store.fetchAttendance).toHaveBeenCalledTimes(1)
    expect(store.fetchAttendance).toHaveBeenCalledWith(7)
  })

  it('prechecks the students already marked present', async () => {
    const { wrapper } = mountRoster()
    await open(wrapper)

    expect(wrapper.find('#attendance-7-1').element.checked).toBe(true)
    expect(wrapper.find('#attendance-7-2').element.checked).toBe(false)
    expect(wrapper.find('[data-present-count]').text()).toContain('1 de 2')
  })

  it('sends the checked students on save', async () => {
    const { wrapper, store } = mountRoster()
    await open(wrapper)

    await wrapper.find('#attendance-7-2').trigger('change')
    await wrapper.findAll('button').find((b) => b.text().includes('Guardar')).trigger('click')

    expect(store.saveAttendance).toHaveBeenCalledWith(7, [1, 2])
  })

  /**
   * Unchecking has to travel as an absent id, not be silently dropped — the
   * API replaces the roster, which is what takes the certificate back.
   */
  it('sends an unchecked student as removed', async () => {
    const { wrapper, store } = mountRoster()
    await open(wrapper)

    await wrapper.find('#attendance-7-1').trigger('change')
    await wrapper.findAll('button').find((b) => b.text().includes('Guardar')).trigger('click')

    expect(store.saveAttendance).toHaveBeenCalledWith(7, [])
  })

  it('explains an empty course instead of showing a bare list', async () => {
    const store = useInstructorStore()
    store.fetchAttendance = vi.fn().mockImplementation(async () => {
      store.attendance = { 7: [] }
      return []
    })

    const wrapper = mount(AttendanceRoster, { props: { lessonId: 7 } })
    await open(wrapper)

    expect(wrapper.text()).toContain('Todavía no hay alumnos inscritos')
  })
})
