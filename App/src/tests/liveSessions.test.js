import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@capacitor/browser', () => ({
  Browser: { open: vi.fn().mockResolvedValue(undefined) },
}))

vi.mock('../services/api.js', () => ({
  default: { get: vi.fn(), post: vi.fn() },
}))

import { Browser } from '@capacitor/browser'
import api from '../services/api.js'
import { isAllowedMeetingUrl, openMeeting } from '../services/meetingLauncher.js'
import { useCoursesStore } from '../stores/courses.js'
import CourseCurriculum from '../components/course/CourseCurriculum.vue'

const MEET = 'https://meet.google.com/abc-defg-hij'

describe('meetingLauncher', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('accepts an https room', () => {
    expect(isAllowedMeetingUrl(MEET)).toBe(true)
  })

  /**
   * The API returns meeting_url: null for every session outside its window,
   * which is the common case. Left unguarded it becomes
   * window.open(null, '_blank') — a blank tab instead of an explanation.
   */
  it('rejects the null the API sends outside the session window', async () => {
    expect(isAllowedMeetingUrl(null)).toBe(false)
    expect(await openMeeting(null)).toBe(false)
    expect(Browser.open).not.toHaveBeenCalled()
  })

  it('rejects a plaintext url', () => {
    expect(isAllowedMeetingUrl('http://meet.google.com/abc')).toBe(false)
  })

  it('hands an open room to the system browser', async () => {
    expect(await openMeeting(MEET)).toBe(true)
    expect(Browser.open).toHaveBeenCalledWith({ url: MEET })
  })
})

describe('coursesStore.joinSession', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('opens the room when the window is open', async () => {
    api.get.mockResolvedValue({ data: { data: { id: 9, meeting_url: MEET } } })
    const store = useCoursesStore()

    expect(await store.joinSession(9)).toBe('opened')
    expect(api.get).toHaveBeenCalledWith('/lessons/9')
    expect(Browser.open).toHaveBeenCalledWith({ url: MEET })
    expect(store.sessionError).toBeNull()
  })

  it('explains the closed window instead of opening a blank tab', async () => {
    api.get.mockResolvedValue({ data: { data: { id: 9, meeting_url: null } } })
    const store = useCoursesStore()

    expect(await store.joinSession(9)).toBe('closed')
    expect(Browser.open).not.toHaveBeenCalled()
    expect(store.sessionError).toContain('15 minutos antes')
  })

  it('asks the user to enroll on a 403', async () => {
    api.get.mockRejectedValue({ response: { status: 403 } })
    const store = useCoursesStore()

    expect(await store.joinSession(9)).toBeNull()
    expect(store.sessionError).toContain('inscrito')
  })

  /**
   * A closed room must not blank out the enrollment CTA, which reads a
   * different error field.
   */
  it('keeps its error separate from enrollment', async () => {
    api.get.mockResolvedValue({ data: { data: { meeting_url: null } } })
    const store = useCoursesStore()

    await store.joinSession(9)

    expect(store.enrollError).toBeNull()
  })

  it('clears the pending lesson once done', async () => {
    api.get.mockResolvedValue({ data: { data: { meeting_url: MEET } } })
    const store = useCoursesStore()

    await store.joinSession(9)

    expect(store.joiningLessonId).toBeNull()
  })
})

describe('CourseCurriculum — live sessions', () => {
  const sections = [
    {
      id: 1,
      title: 'Módulo 1',
      position: 0,
      lessons: [
        { id: 10, title: 'Sesión 1', position: 0, is_free: false, duration: 5400, starts_at: '2026-09-02T00:00:00.000000Z' },
        { id: 11, title: 'Grabada', position: 1, is_free: false, duration: 600 },
      ],
    },
  ]

  function mountCurriculum(props = {}) {
    return mount(CourseCurriculum, {
      props: { sections, ...props },
      global: { stubs: { BaseBadge: true } },
    })
  }

  it('shows the schedule for a session and the duration for a recording', () => {
    const wrapper = mountCurriculum()

    expect(wrapper.findAll('[data-session-date]')).toHaveLength(1)
    expect(wrapper.text()).toContain('10:00')
  })

  /**
   * A visitor who has not bought the course sees when the sessions are —
   * that is what they are deciding on — but has nothing to join.
   */
  it('hides the join button until the student is enrolled', () => {
    expect(mountCurriculum().find('[data-join-session]').exists()).toBe(false)
    expect(mountCurriculum({ canJoin: true }).find('[data-join-session]').exists()).toBe(true)
  })

  it('offers no join button on a recorded lesson', () => {
    const wrapper = mountCurriculum({ canJoin: true })

    expect(wrapper.findAll('[data-join-session]')).toHaveLength(1)
  })

  it('emits the lesson to join', async () => {
    const wrapper = mountCurriculum({ canJoin: true })

    await wrapper.find('[data-join-session]').trigger('click')

    expect(wrapper.emitted('join')[0]).toEqual([10])
  })

  it('marks only the row being opened as pending', () => {
    const wrapper = mountCurriculum({ canJoin: true, joiningLessonId: 10 })

    const button = wrapper.find('[data-join-session]')
    expect(button.text()).toContain('Abriendo')
    expect(button.attributes('disabled')).toBeDefined()
  })
})
