import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import LessonEditor from '../components/LessonEditor.vue'
import { useInstructorStore } from '../stores/instructor.js'

const baseLesson = {
  id: 11,
  title: 'Sesión 1',
  description: '',
  video_url: null,
  meeting_url: null,
  starts_at: null,
  duration: 0,
  is_free: false,
  is_practice: false,
}

/**
 * The editor reads the delivery mode off the course currently open in the
 * store, so each test declares the course it is editing inside.
 */
function mountEditor(course, lesson = baseLesson) {
  const store = useInstructorStore()
  store.currentCourse = course

  const wrapper = mount(LessonEditor, {
    props: { lesson, sectionId: 4, lessonIndex: 0 },
  })

  return { wrapper, store }
}

const liveCourse = {
  slug: 'curso-vivo',
  delivery_mode: 'live',
  sections: [{ id: 4, lessons: [baseLesson] }],
}

const onDemandCourse = {
  slug: 'curso-video',
  delivery_mode: 'on_demand',
  sections: [{ id: 4, lessons: [baseLesson] }],
}

async function expand(wrapper) {
  await wrapper.findAll('button').find((b) => b.text().includes('Sesión 1')).trigger('click')
}

describe('LessonEditor — live sessions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('offers the meeting link and schedule on a live course', async () => {
    const { wrapper } = mountEditor(liveCourse)
    await expand(wrapper)

    expect(wrapper.find('#lesson-meeting-11').exists()).toBe(true)
    expect(wrapper.find('#lesson-starts-at-11').exists()).toBe(true)
  })

  /**
   * The API rejects meeting fields outright on an on-demand course, so they
   * must not exist to be filled in the first place.
   */
  it('hides the meeting fields on an on-demand course', async () => {
    const { wrapper } = mountEditor(onDemandCourse)
    await expand(wrapper)

    expect(wrapper.find('#lesson-meeting-11').exists()).toBe(false)
    expect(wrapper.find('#lesson-starts-at-11').exists()).toBe(false)
  })

  it('sends the meeting link and schedule on save', async () => {
    const { wrapper, store } = mountEditor(liveCourse)
    store.updateLesson = vi.fn().mockResolvedValue({})
    await expand(wrapper)

    await wrapper.find('#lesson-meeting-11').setValue('https://meet.google.com/abc-defg-hij')
    await wrapper.find('#lesson-starts-at-11').setValue('2026-09-01T19:00')
    await wrapper.findAll('button').find((b) => b.text().includes('Guardar')).trigger('click')

    expect(store.updateLesson).toHaveBeenCalledWith(
      11,
      expect.objectContaining({
        meeting_url: 'https://meet.google.com/abc-defg-hij',
        starts_at: '2026-09-01T19:00',
      }),
    )
  })

  /**
   * The value is already academy wall-clock; passing it through a Date would
   * re-interpret it in the browser's timezone and shift the session.
   */
  it('passes the schedule through untouched, without timezone conversion', async () => {
    const { wrapper, store } = mountEditor(liveCourse, {
      ...baseLesson,
      starts_at: '2026-09-01T19:00',
    })
    store.updateLesson = vi.fn().mockResolvedValue({})
    await expand(wrapper)

    expect(wrapper.find('#lesson-starts-at-11').element.value).toBe('2026-09-01T19:00')

    await wrapper.findAll('button').find((b) => b.text().includes('Guardar')).trigger('click')

    expect(store.updateLesson).toHaveBeenCalledWith(
      11,
      expect.objectContaining({ starts_at: '2026-09-01T19:00' }),
    )
  })

  it('omits the meeting fields entirely from an on-demand payload', async () => {
    const { wrapper, store } = mountEditor(onDemandCourse)
    store.updateLesson = vi.fn().mockResolvedValue({})
    await expand(wrapper)

    await wrapper.findAll('button').find((b) => b.text().includes('Guardar')).trigger('click')

    const [, payload] = store.updateLesson.mock.calls[0]
    expect(payload).not.toHaveProperty('meeting_url')
    expect(payload).not.toHaveProperty('starts_at')
  })
})
