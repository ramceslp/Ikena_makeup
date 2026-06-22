import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Variant3 from '../../components/certificate/variants/Variant3.vue'

const certificate = {
  code: 'CERT-ORN-333',
  issued_at: '2026-03-08T00:00:00Z',
  student_name: 'Sofía Méndez',
  course_title: 'Maquillaje de Pasarela',
  instructor_name: 'Ana García',
}

const settings = {
  business_name: 'Casa Ornato',
  title: 'Certificado de Mérito',
  award_line: 'Se distingue a',
  achievement_line: 'por su excelencia en el curso',
  signer_name: 'Renata Vidal',
  signer_role: 'Directora',
  design_variant: 3,
  logo_url: 'https://cdn.example.com/ornato.png',
}

function mountVariant(overrides = {}) {
  return mount(Variant3, {
    props: {
      certificate: { ...certificate, ...(overrides.certificate ?? {}) },
      settings: { ...settings, ...(overrides.settings ?? {}) },
    },
  })
}

describe('Variant3.vue — Ornate Seal', () => {
  it('renders the business name from settings', () => {
    expect(mountVariant().text()).toContain('Casa Ornato')
  })
  it('renders the title from settings', () => {
    expect(mountVariant().text()).toContain('Certificado de Mérito')
  })
  it('renders the award line from settings', () => {
    expect(mountVariant().text()).toContain('Se distingue a')
  })
  it('renders the achievement line from settings', () => {
    expect(mountVariant().text()).toContain('por su excelencia en el curso')
  })
  it('renders the student name from the certificate', () => {
    expect(mountVariant().text()).toContain('Sofía Méndez')
  })
  it('renders the course title from the certificate', () => {
    expect(mountVariant().text()).toContain('Maquillaje de Pasarela')
  })
  it('renders the signer name, NOT the instructor', () => {
    const text = mountVariant().text()
    expect(text).toContain('Renata Vidal')
    expect(text).not.toContain('Ana García')
  })
  it('renders the signer role only when present', () => {
    expect(mountVariant().text()).toContain('Directora')
    expect(mountVariant({ settings: { signer_role: '' } }).text()).not.toContain('Directora')
  })
  it('renders the issued date in Spanish long format', () => {
    const text = mountVariant().text()
    expect(text).toContain('2026')
    expect(text).toMatch(/marzo/i)
  })
  it('renders the verification code', () => {
    expect(mountVariant().text()).toContain('CERT-ORN-333')
  })
  it('renders the logo when logo_url is present, fallback mark otherwise', () => {
    const img = mountVariant().find('[data-cert-logo]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example.com/ornato.png')

    const noLogo = mountVariant({ settings: { logo_url: null } })
    expect(noLogo.find('[data-cert-logo]').exists()).toBe(false)
    expect(noLogo.find('[data-cert-logo-fallback]').exists()).toBe(true)
  })
})
