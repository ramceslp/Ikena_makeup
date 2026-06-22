import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Variant1 from '../../components/certificate/variants/Variant1.vue'

// ---------------------------------------------------------------------------
// Fixtures — deliberately DIFFERENT from the model defaults so the assertions
// prove the variant renders the `settings` payload, not hardcoded copy.
// ---------------------------------------------------------------------------

const certificate = {
  code: 'CERT-XYZ-456',
  issued_at: '2026-06-16T00:00:00Z',
  student_name: 'María López',
  course_title: 'Makeup Profesional Avanzado',
  instructor_name: 'Ana García',
}

const settings = {
  business_name: 'Estudio Bella',
  title: 'Diploma de Excelencia',
  award_line: 'Este diploma se concede a',
  achievement_line: 'por dominar con distinción el curso',
  signer_name: 'Lucía Fernández',
  signer_role: 'Directora Académica',
  design_variant: 1,
  logo_url: 'https://cdn.example.com/logo.png',
}

function mountVariant(overrides = {}) {
  return mount(Variant1, {
    props: {
      certificate: { ...certificate, ...(overrides.certificate ?? {}) },
      settings: { ...settings, ...(overrides.settings ?? {}) },
    },
  })
}

describe('Variant1.vue — Classic Frame', () => {
  it('renders the configurable business name from settings', () => {
    expect(mountVariant().text()).toContain('Estudio Bella')
  })

  it('renders the configurable title from settings', () => {
    expect(mountVariant().text()).toContain('Diploma de Excelencia')
  })

  it('renders the configurable award line from settings', () => {
    expect(mountVariant().text()).toContain('Este diploma se concede a')
  })

  it('renders the configurable achievement line from settings', () => {
    expect(mountVariant().text()).toContain('por dominar con distinción el curso')
  })

  it('renders the student name from the certificate', () => {
    expect(mountVariant().text()).toContain('María López')
  })

  it('renders the course title from the certificate', () => {
    expect(mountVariant().text()).toContain('Makeup Profesional Avanzado')
  })

  it('renders the signer name from settings, NOT the instructor', () => {
    const text = mountVariant().text()
    expect(text).toContain('Lucía Fernández')
    expect(text).not.toContain('Ana García')
  })

  it('renders the signer role only when present', () => {
    expect(mountVariant().text()).toContain('Directora Académica')
  })

  it('omits the signer role line when empty', () => {
    const text = mountVariant({ settings: { signer_role: '' } }).text()
    expect(text).not.toContain('Directora Académica')
  })

  it('renders the issued date in Spanish long format', () => {
    const text = mountVariant().text()
    expect(text).toContain('2026')
    expect(text).toMatch(/junio/i)
  })

  it('renders the verification code', () => {
    expect(mountVariant().text()).toContain('CERT-XYZ-456')
  })

  it('renders the logo image when logo_url is present', () => {
    const wrapper = mountVariant()
    const img = wrapper.find('[data-cert-logo]')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://cdn.example.com/logo.png')
  })

  it('renders a fallback mark instead of an image when logo_url is null', () => {
    const wrapper = mountVariant({ settings: { logo_url: null } })
    expect(wrapper.find('[data-cert-logo]').exists()).toBe(false)
    expect(wrapper.find('[data-cert-logo-fallback]').exists()).toBe(true)
  })
})
