import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import { useCertificateSettingsStore } from '../stores/certificateSettings.js'

describe('certificateSettings store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchSettings populates settings from the public endpoint', async () => {
    api.get.mockResolvedValueOnce({ data: { data: { business_name: 'Studio', design_variant: 2 } } })

    const store = useCertificateSettingsStore()
    await store.fetchSettings()

    expect(api.get).toHaveBeenCalledWith('/certificate-settings')
    expect(store.settings.business_name).toBe('Studio')
  })

  it('fetchSettings caches — a second call issues no second HTTP request', async () => {
    api.get.mockResolvedValue({ data: { data: { business_name: 'Studio' } } })

    const store = useCertificateSettingsStore()
    await store.fetchSettings()
    await store.fetchSettings()

    expect(api.get).toHaveBeenCalledTimes(1)
  })

  it('fetchAdminSettings bypasses the cache and refreshes from the admin endpoint', async () => {
    api.get.mockResolvedValue({ data: { data: { business_name: 'Admin Value' } } })

    const store = useCertificateSettingsStore()
    store.settings = { business_name: 'stale cached' }
    await store.fetchAdminSettings()

    expect(api.get).toHaveBeenCalledWith('/admin/certificate-settings')
    expect(store.settings.business_name).toBe('Admin Value')
  })

  it('updateSettings posts multipart and refreshes settings', async () => {
    api.post.mockResolvedValueOnce({ data: { data: { business_name: 'New', design_variant: 3 } } })

    const store = useCertificateSettingsStore()
    const fd = new FormData()
    const result = await store.updateSettings(fd)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/certificate-settings',
      fd,
      expect.objectContaining({ headers: { 'Content-Type': 'multipart/form-data' } }),
    )
    expect(store.settings.business_name).toBe('New')
    expect(result.business_name).toBe('New')
  })

  it('fetchSettings leaves a safe state on error (settings stays null, no throw)', async () => {
    api.get.mockRejectedValueOnce({ response: { data: { message: 'boom' } } })

    const store = useCertificateSettingsStore()
    const result = await store.fetchSettings()

    expect(result).toBeNull()
    expect(store.settings).toBeNull()
    expect(store.error).toBe('boom')
  })
})
