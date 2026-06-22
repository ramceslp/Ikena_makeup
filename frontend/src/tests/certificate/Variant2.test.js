import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Variant2 from '../../components/certificate/variants/Variant2.vue'

// Same `{ certificate, settings }` contract as every other variant.
const certificate = {
  code: 'CERT-NEW-789',
  issued_at: '2025-01-15T10:00:00Z',
  student_name: 'Carlos Ruiz',
  course_title: 'Fundamentos de Maquillaje',
  instructor_name: 'Laura Pérez',
}

const settings = {
  business_name: 'Atelier Glow',
  title: 'Certificado Editorial',
  award_line: 'Otorgado con orgullo a',
  achievement_line: 'tras completar con éxito el programa',
  signer_name: 'Marta Solís',
  signer_role: 'Coordinadora',
  design_variant: 2,
  logo_url: 'https://cdn.example.com/glow.png',
}

function mountVariant(overrides = {}) {
  return mount(Variant2, {
    props: {
      certificate: { ...certificate, ...(overrides.certificate ?? {}) },
      settings: { ...settings, ...(overrides.settings ?? {}) },
    },
  })
}

describe('Variant2.vue — Minimal Line', () => {
  it('renders the configurable business name from settings', () => {
    expect(mountVariant().text()).toContain('Atelier Glow')
  })

  it('renders the configurable title from settings', () => {
    expect(mountVariant().text()).toContain('Certificado Editorial')
  })

  it('renders the configurable award line from settings', () => {
    expect(mountVariant().text()).toContain('Otorgado con orgullo a')
  })

  it('renders the configurable achievement line from settings', () => {
    expect(mountVariant().text()).toContain('tras completar con éxito el programa')
  })

  it('renders the student name from the certificate', () => {
    expect(mountVariant().text()).toContain('Carlos Ruiz')
  })

  it('renders the course title from the certificate', () => {
    expect(mountVariant().text()).toContain('Fundamentos de Maquillaje')
  })

  it('renders the signer name from settings, NOT the instructor', () => {
    const text = mountVariant().text()
    expect(text).toContain('Marta Solís')
    expect(text).not.toContain('Laura Pérez')
  })

  it('renders the signer role only when present', () => {
    expect(mountVariant().text()).toContain('Coordinadora')
  })

  it('omits the signer role line when empty', () => {
    const text = mountVariant({ settings: { signer_role: '' } }).text()
    expect(text).not.toContain('Coordinadora')
  })

  it('renders the issued date in Spanish long format', () => {
    const text = mountVariant().text()
    expect(text).toContain('2025')
    expect(text).toMatch(/enero/i)
  })

  it('renders the verification code', () => {
    expect(mountVariant().text()).toContain('CERT-NEW-789')
  })

  it('renders the logo image when logo_url is present', () => {
    const img = mountVariant().find('[data-cert-logo]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example.com/glow.png')
  })

  it('renders a fallback mark instead of an image when logo_url is null', () => {
    const wrapper = mountVariant({ settings: { logo_url: null } })
    expect(wrapper.find('[data-cert-logo]').exists()).toBe(false)
    expect(wrapper.find('[data-cert-logo-fallback]').exists()).toBe(true)
  })
})
