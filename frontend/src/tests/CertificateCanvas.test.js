import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CertificateCanvas from '../components/certificate/CertificateCanvas.vue'
import Variant1 from '../components/certificate/variants/Variant1.vue'
import Variant2 from '../components/certificate/variants/Variant2.vue'

// ---------------------------------------------------------------------------
// CertificateCanvas is now a THIN SWITCHER: it picks the active variant from
// settings.design_variant and forwards the { certificate, settings } contract.
// Content assertions live at the variant level — here we only test routing.
// ---------------------------------------------------------------------------

const certificate = {
  code: 'CERT-XYZ-456',
  issued_at: '2026-06-16T00:00:00Z',
  student_name: 'María López',
  course_title: 'Makeup Profesional Avanzado',
  instructor_name: 'Ana García',
}

function baseSettings(design_variant) {
  return {
    business_name: 'Estudio Bella',
    title: 'Diploma de Excelencia',
    award_line: 'Este diploma se concede a',
    achievement_line: 'por dominar con distinción el curso',
    signer_name: 'Lucía Fernández',
    signer_role: 'Directora Académica',
    design_variant,
    logo_url: null,
  }
}

function mountCanvas(design_variant) {
  return mount(CertificateCanvas, {
    props: { certificate, settings: baseSettings(design_variant) },
  })
}

describe('CertificateCanvas.vue — variant switcher', () => {
  it('renders Variant1 when design_variant is 1', () => {
    const wrapper = mountCanvas(1)
    expect(wrapper.findComponent(Variant1).exists()).toBe(true)
    expect(wrapper.findComponent(Variant2).exists()).toBe(false)
  })

  it('renders Variant2 when design_variant is 2', () => {
    const wrapper = mountCanvas(2)
    expect(wrapper.findComponent(Variant2).exists()).toBe(true)
    expect(wrapper.findComponent(Variant1).exists()).toBe(false)
  })

  it('falls back to Variant1 for a not-yet-implemented variant (3-5)', () => {
    const wrapper = mountCanvas(4)
    expect(wrapper.findComponent(Variant1).exists()).toBe(true)
  })

  it('falls back to Variant1 for an out-of-range design_variant', () => {
    const wrapper = mountCanvas(99)
    expect(wrapper.findComponent(Variant1).exists()).toBe(true)
  })

  it('falls back to Variant1 when design_variant is missing', () => {
    const wrapper = mount(CertificateCanvas, {
      props: { certificate, settings: { ...baseSettings(1), design_variant: undefined } },
    })
    expect(wrapper.findComponent(Variant1).exists()).toBe(true)
  })

  it('forwards the certificate and settings props to the active variant', () => {
    const wrapper = mountCanvas(2)
    const variant = wrapper.findComponent(Variant2)
    expect(variant.props('certificate')).toMatchObject({ student_name: 'María López' })
    expect(variant.props('settings')).toMatchObject({ signer_name: 'Lucía Fernández' })
  })
})
