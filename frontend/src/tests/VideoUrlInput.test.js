import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import VideoUrlInput from '../components/VideoUrlInput.vue'

function mountComponent(modelValue = '') {
  const pinia = createPinia()
  return mount(VideoUrlInput, {
    props: { modelValue },
    global: { plugins: [pinia] },
  })
}

describe('VideoUrlInput', () => {
  it('renders a text input', () => {
    const wrapper = mountComponent()
    expect(wrapper.find('input[type="text"]').exists()).toBe(true)
  })

  it('emits update:modelValue when input changes', async () => {
    const wrapper = mountComponent()
    const input = wrapper.find('input')
    await input.setValue('https://youtu.be/abc123')
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
  })

  it('renders an iframe for a YouTube URL', () => {
    const wrapper = mountComponent('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
    const iframe = wrapper.find('iframe')
    expect(iframe.exists()).toBe(true)
    expect(iframe.attributes('src')).toContain('youtube.com/embed/dQw4w9WgXcQ')
  })

  it('renders an iframe for a Vimeo URL', () => {
    const wrapper = mountComponent('https://vimeo.com/123456789')
    const iframe = wrapper.find('iframe')
    expect(iframe.exists()).toBe(true)
    expect(iframe.attributes('src')).toContain('player.vimeo.com/video/123456789')
  })

  it('renders a video element for a direct .mp4 URL', () => {
    const wrapper = mountComponent('https://cdn.example.com/lesson.mp4')
    expect(wrapper.find('video').exists()).toBe(true)
    expect(wrapper.find('source').attributes('src')).toBe('https://cdn.example.com/lesson.mp4')
  })

  it('shows validity hint text for an invalid non-empty URL (never color-only)', () => {
    const wrapper = mountComponent('https://somerandsite.com/watch')
    const text = wrapper.text()
    expect(text).toContain('URL de video no válida')
    // No iframe or video should be rendered
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.find('video').exists()).toBe(false)
  })

  it('shows no preview for an empty string', () => {
    const wrapper = mountComponent('')
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.find('video').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('URL de video no válida')
  })

  it('shows no preview for undefined/null (treated as empty)', () => {
    const wrapper = mountComponent(undefined)
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.find('video').exists()).toBe(false)
  })
})
