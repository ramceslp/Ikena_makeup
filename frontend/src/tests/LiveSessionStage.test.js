import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import LiveSessionStage from '../components/player/LiveSessionStage.vue'
import VideoStage from '../components/player/VideoStage.vue'

const scheduled = {
  id: 1,
  title: 'Sesión 1',
  is_live_session: true,
  meeting_url: null,
  // 19:00 in the academy's timezone, serialized as the UTC instant it is.
  starts_at: '2026-09-02T00:00:00.000000Z',
  meeting_available_at: '2026-09-01T23:45:00.000000Z',
}

describe('LiveSessionStage', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('offers the join link when the room is open', () => {
    const wrapper = mount(LiveSessionStage, {
      props: {
        lesson: { ...scheduled, meeting_url: 'https://meet.google.com/abc-defg-hij' },
      },
    })

    const link = wrapper.find('[data-live-open] a')
    expect(link.attributes('href')).toBe('https://meet.google.com/abc-defg-hij')
    // The room must not open inside the player's own frame.
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toContain('noopener')
  })

  it('shows when to come back while the room is closed', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-08-30T12:00:00Z'))

    const wrapper = mount(LiveSessionStage, { props: { lesson: scheduled } })

    expect(wrapper.find('[data-live-scheduled]').exists()).toBe(true)
    expect(wrapper.find('[data-live-open]').exists()).toBe(false)
    expect(wrapper.text()).toContain('El enlace se habilita')
  })

  /**
   * "Come back later" is wrong advice once the session is over — the student
   * needs to know they missed it, not that they are early.
   */
  it('says the session ended once its moment has passed', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-09-05T12:00:00Z'))

    const wrapper = mount(LiveSessionStage, { props: { lesson: scheduled } })

    expect(wrapper.find('[data-live-ended]').exists()).toBe(true)
    expect(wrapper.find('[data-live-scheduled]').exists()).toBe(false)
  })
})

describe('VideoStage — live sessions', () => {
  it('replaces the video frame for a live session with no recording', () => {
    const wrapper = mount(VideoStage, {
      props: { lesson: scheduled, resolvedVideo: { type: 'unknown' } },
    })

    expect(wrapper.findComponent(LiveSessionStage).exists()).toBe(true)
  })

  it('keeps the live stage while the room is open, even with a recording', () => {
    const wrapper = mount(VideoStage, {
      props: {
        lesson: { ...scheduled, meeting_url: 'https://meet.google.com/abc-defg-hij' },
        resolvedVideo: { type: 'youtube', embedUrl: 'https://youtube.com/embed/x' },
      },
    })

    expect(wrapper.findComponent(LiveSessionStage).exists()).toBe(true)
    expect(wrapper.find('iframe').exists()).toBe(false)
  })

  /**
   * The session's afterlife: once the room has closed and a recording exists,
   * the video is what the student came for.
   */
  it('plays the recording once the room has closed', () => {
    const wrapper = mount(VideoStage, {
      props: {
        lesson: scheduled,
        resolvedVideo: { type: 'youtube', embedUrl: 'https://youtube.com/embed/x' },
      },
    })

    expect(wrapper.findComponent(LiveSessionStage).exists()).toBe(false)
    expect(wrapper.find('iframe').exists()).toBe(true)
  })

  it('leaves on-demand lessons untouched', () => {
    const wrapper = mount(VideoStage, {
      props: {
        lesson: { id: 2, title: 'Grabada', is_live_session: false },
        resolvedVideo: { type: 'mp4', src: 'https://example.com/a.mp4' },
      },
    })

    expect(wrapper.findComponent(LiveSessionStage).exists()).toBe(false)
    expect(wrapper.find('video').exists()).toBe(true)
  })
})
