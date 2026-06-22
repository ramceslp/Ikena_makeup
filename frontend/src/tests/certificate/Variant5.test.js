import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Variant5 from '../../components/certificate/variants/Variant5.vue'

const certificate = {
  code: 'CERT-ELEG-555',
  issued_at: '2024-07-04T00:00:00Z',
  student_name: 'Valentina Cruz',
  course_title: 'Maquillaje de Novias',
  instructor_name: 'Laura Pérez',
}

const settings = {
  business_name: 'Maison Élégance',
  title: 'Certificado de Distinción',
  award_line: 'Se concede a',
  achievement_line: 'tras culminar con excelencia',
  signer_name: 'Camila Ortiz',
  signer_role: 'Fundadora',
  design_variant: 5,
  logo_url: 'https://cdn.example.com/elegance.png',
}

function mountVariant(overrides = {}) {
  return mount(Variant5, {
    props: {
      certificate: { ...certificate, ...(overrides.certificate ?? {}) },
      settings: { ...settings, ...(overrides.settings ?? {}) },
    },
  })
}

describe('Variant5.vue — Elegant Portrait', () => {
  it('renders the business name from settings', () => {
    expect(mountVariant().text()).toContain('Maison Élégance')
  })
  it('renders the title from settings', () => {
    expect(mountVariant().text()).toContain('Certificado de Distinción')
  })
  it('renders the award line from settings', () => {
    expect(mountVariant().text()).toContain('Se concede a')
  })
  it('renders the achievement line from settings', () => {
    expect(mountVariant().text()).toContain('tras culminar con excelencia')
  })
  it('renders the student name from the certificate', () => {
    expect(mountVariant().text()).toContain('Valentina Cruz')
  })
  it('renders the course title from the certificate', () => {
    expect(mountVariant().text()).toContain('Maquillaje de Novias')
  })
  it('renders the signer name, NOT the instructor', () => {
    const text = mountVariant().text()
    expect(text).toContain('Camila Ortiz')
    expect(text).not.toContain('Laura Pérez')
  })
  it('renders the signer role only when present', () => {
    expect(mountVariant().text()).toContain('Fundadora')
    expect(mountVariant({ settings: { signer_role: '' } }).text()).not.toContain('Fundadora')
  })
  it('renders the issued date in Spanish long format', () => {
    const text = mountVariant().text()
    expect(text).toContain('2024')
    expect(text).toMatch(/julio/i)
  })
  it('renders the verification code', () => {
    expect(mountVariant().text()).toContain('CERT-ELEG-555')
  })
  it('renders the logo when logo_url is present, fallback mark otherwise', () => {
    const img = mountVariant().find('[data-cert-logo]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example.com/elegance.png')

    const noLogo = mountVariant({ settings: { logo_url: null } })
    expect(noLogo.find('[data-cert-logo]').exists()).toBe(false)
    expect(noLogo.find('[data-cert-logo-fallback]').exists()).toBe(true)
  })
})
