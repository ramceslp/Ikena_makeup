import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SecurityForm from '../components/profile/SecurityForm.vue'

const baseProps = {
  saving: false,
  error: '',
  fieldErrors: {},
  success: false,
}

describe('SecurityForm.vue', () => {
  it('shows info panel and hides form when user has no password (Google account)', () => {
    const wrapper = mount(SecurityForm, {
      props: { ...baseProps, user: { has_password: false, name: 'Google User' } },
    })

    expect(wrapper.find('input[type="password"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Google')
  })

  it('renders password inputs when user has_password is true', () => {
    const wrapper = mount(SecurityForm, {
      props: { ...baseProps, user: { has_password: true, name: 'Regular User' } },
    })

    expect(wrapper.find('input').exists()).toBe(true)
  })

  it('emits submit with current_password, password, password_confirmation when form submitted', async () => {
    const wrapper = mount(SecurityForm, {
      props: { ...baseProps, user: { has_password: true } },
    })

    const inputs = wrapper.findAll('input')
    // inputs[0] = current_password, inputs[1] = new password, inputs[2] = confirm password
    await inputs[0].setValue('oldpass')
    await inputs[1].setValue('newpass8')
    await inputs[2].setValue('newpass8')

    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const emittedPayload = wrapper.emitted('submit')[0][0]
    expect(emittedPayload).toHaveProperty('current_password')
    expect(emittedPayload).toHaveProperty('password')
    expect(emittedPayload).toHaveProperty('password_confirmation')
  })
})
