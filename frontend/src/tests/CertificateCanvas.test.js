import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CertificateCanvas from '../components/certificate/CertificateCanvas.vue'

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const baseCertificate = {
  code: 'CERT-XYZ-456',
  issued_at: '2026-06-16T00:00:00Z',
  student_name: 'María López',
  course_title: 'Makeup Profesional Avanzado',
  instructor_name: 'Ana García',
}

function mountCanvas(certificate = baseCertificate) {
  return mount(CertificateCanvas, {
    props: { certificate },
  })
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('CertificateCanvas.vue', () => {
  it('renders the student name prominently', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('María López')
  })

  it('renders the course title', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('Makeup Profesional Avanzado')
  })

  it('renders the instructor name', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('Ana García')
  })

  it('renders the verification code', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('CERT-XYZ-456')
  })

  it('renders the issued date formatted in Spanish long format', () => {
    const wrapper = mountCanvas()
    // Intl.DateTimeFormat('es', { dateStyle: 'long' }) → "16 de junio de 2026"
    expect(wrapper.text()).toContain('2026')
    expect(wrapper.text()).toMatch(/junio/i)
  })

  it('renders the certificate heading in Spanish', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('Certificado')
  })

  it('renders the introductory phrase', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('Se otorga')
  })

  it('renders the completion phrase', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('completar satisfactoriamente')
  })

  it('renders verification code label', () => {
    const wrapper = mountCanvas()
    expect(wrapper.text()).toContain('verificación')
  })

  it('renders with a different certificate payload correctly', () => {
    const cert = {
      code: 'CERT-NEW-789',
      issued_at: '2025-01-15T10:00:00Z',
      student_name: 'Carlos Ruiz',
      course_title: 'Fundamentos de Maquillaje',
      instructor_name: 'Laura Pérez',
    }
    const wrapper = mountCanvas(cert)
    expect(wrapper.text()).toContain('Carlos Ruiz')
    expect(wrapper.text()).toContain('Fundamentos de Maquillaje')
    expect(wrapper.text()).toContain('Laura Pérez')
    expect(wrapper.text()).toContain('CERT-NEW-789')
    expect(wrapper.text()).toContain('2025')
    expect(wrapper.text()).toMatch(/enero/i)
  })
})
