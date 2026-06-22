import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Variant4 from '../../components/certificate/variants/Variant4.vue'

const certificate = {
  code: 'CERT-BAND-444',
  issued_at: '2025-11-20T00:00:00Z',
  student_name: 'Diego Salas',
  course_title: 'Maquillaje Editorial',
  instructor_name: 'Laura Pérez',
}

const settings = {
  business_name: 'Modern Lab',
  title: 'Acreditación Profesional',
  award_line: 'Concedido a',
  achievement_line: 'por completar con éxito',
  signer_name: 'Iván Toro',
  signer_role: 'Coordinador',
  design_variant: 4,
  logo_url: 'https://cdn.example.com/modern.png',
}

function mountVariant(overrides = {}) {
  return mount(Variant4, {
    props: {
      certificate: { ...certificate, ...(overrides.certificate ?? {}) },
      settings: { ...settings, ...(overrides.settings ?? {}) },
    },
  })
}

describe('Variant4.vue — Modern Band', () => {
  it('renders the business name from settings', () => {
    expect(mountVariant().text()).toContain('Modern Lab')
  })
  it('renders the title from settings', () => {
    expect(mountVariant().text()).toContain('Acreditación Profesional')
  })
  it('renders the award line from settings', () => {
    expect(mountVariant().text()).toContain('Concedido a')
  })
  it('renders the achievement line from settings', () => {
    expect(mountVariant().text()).toContain('por completar con éxito')
  })
  it('renders the student name from the certificate', () => {
    expect(mountVariant().text()).toContain('Diego Salas')
  })
  it('renders the course title from the certificate', () => {
    expect(mountVariant().text()).toContain('Maquillaje Editorial')
  })
  it('renders the signer name, NOT the instructor', () => {
    const text = mountVariant().text()
    expect(text).toContain('Iván Toro')
    expect(text).not.toContain('Laura Pérez')
  })
  it('renders the signer role only when present', () => {
    expect(mountVariant().text()).toContain('Coordinador')
    expect(mountVariant({ settings: { signer_role: '' } }).text()).not.toContain('Coordinador')
  })
  it('renders the issued date in Spanish long format', () => {
    const text = mountVariant().text()
    expect(text).toContain('2025')
    expect(text).toMatch(/noviembre/i)
  })
  it('renders the verification code', () => {
    expect(mountVariant().text()).toContain('CERT-BAND-444')
  })
  it('renders the logo when logo_url is present, fallback mark otherwise', () => {
    const img = mountVariant().find('[data-cert-logo]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example.com/modern.png')

    const noLogo = mountVariant({ settings: { logo_url: null } })
    expect(noLogo.find('[data-cert-logo]').exists()).toBe(false)
    expect(noLogo.find('[data-cert-logo-fallback]').exists()).toBe(true)
  })
})
