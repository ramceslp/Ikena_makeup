import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminCertificateSettingsForm from '../components/admin/AdminCertificateSettingsForm.vue'
import CertificateCanvas from '../components/certificate/CertificateCanvas.vue'
import Variant5 from '../components/certificate/variants/Variant5.vue'

const settings = {
  business_name: 'Studio Bella',
  title: 'Diploma de Honor',
  award_line: 'Otorgado con distinción a',
  achievement_line: 'por finalizar el programa',
  signer_name: 'María Pérez',
  signer_role: 'Directora Académica',
  design_variant: 3,
  logo_url: 'http://localhost/storage/certificate/logo.png',
}

describe('AdminCertificateSettingsForm.vue', () => {
  it('hydrates the fields from the settings prop', () => {
    const wrapper = mount(AdminCertificateSettingsForm, { props: { settings } })

    expect(wrapper.find('#business_name').element.value).toBe('Studio Bella')
    expect(wrapper.find('#signer_role').element.value).toBe('Directora Académica')
    expect(wrapper.find('#design_variant').element.value).toBe('3')
  })

  it('renders the logo preview using the stored logo_url (not a blob)', () => {
    const wrapper = mount(AdminCertificateSettingsForm, { props: { settings } })

    const img = wrapper.find('[data-logo-preview]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe(settings.logo_url)
  })

  it('emits a FormData with the current field values on submit', async () => {
    const wrapper = mount(AdminCertificateSettingsForm, { props: { settings } })

    await wrapper.find('form').trigger('submit.prevent')

    const emitted = wrapper.emitted('submit')
    expect(emitted).toBeTruthy()

    const fd = emitted[0][0]
    expect(fd.get('business_name')).toBe('Studio Bella')
    expect(fd.get('award_line')).toBe('Otorgado con distinción a')
    expect(fd.get('signer_name')).toBe('María Pérez')
    expect(fd.get('signer_role')).toBe('Directora Académica')
    expect(fd.get('design_variant')).toBe('3')
  })

  it('updates the design_variant on selection change', async () => {
    const wrapper = mount(AdminCertificateSettingsForm, { props: { settings } })

    await wrapper.find('#design_variant').setValue('5')
    await wrapper.find('form').trigger('submit.prevent')

    const fd = wrapper.emitted('submit')[0][0]
    expect(fd.get('design_variant')).toBe('5')
  })

  it('renders a live certificate preview reflecting the current form values', () => {
    const wrapper = mount(AdminCertificateSettingsForm, { props: { settings } })

    const preview = wrapper.findComponent(CertificateCanvas)
    expect(preview.exists()).toBe(true)
    expect(preview.props('settings')).toMatchObject({
      business_name: 'Studio Bella',
      design_variant: 3,
    })
  })

  it('live preview switches variant when design_variant changes', async () => {
    const wrapper = mount(AdminCertificateSettingsForm, { props: { settings } })

    await wrapper.find('#design_variant').setValue('5')

    expect(wrapper.findComponent(Variant5).exists()).toBe(true)
  })

  it('live preview reflects edits to a text field', async () => {
    const wrapper = mount(AdminCertificateSettingsForm, { props: { settings } })

    await wrapper.find('#business_name').setValue('Nuevo Estudio')

    expect(wrapper.findComponent(CertificateCanvas).props('settings').business_name).toBe(
      'Nuevo Estudio',
    )
  })
})
