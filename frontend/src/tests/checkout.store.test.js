import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// ---------------------------------------------------------------------------
// Mock the axios api module BEFORE importing the store.
// Mirrors the pattern used in courses.store.test.js and auth.store.test.js.
// ---------------------------------------------------------------------------
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
import { useCoursesStore } from '../stores/courses.js'

describe('courses store — checkout & confirmPayment', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  // ── checkout ────────────────────────────────────────────────────────────────

  describe('checkout(slug)', () => {
    it('returns order_id, provider and config on 201', async () => {
      const fakeData = {
        order_id: 42,
        provider: 'payphone',
        config: {
          token: 'tok_test',
          clientTransactionId: 'order-42-abc',
          amount: 4999,
          amountWithoutTax: 4999,
          amountWithTax: 0,
          tax: 0,
          service: 0,
          tip: 0,
          currency: 'USD',
          storeId: 'store_123',
          reference: 'Curso: PHP Mastery',
          lang: 'es',
        },
      }

      api.post.mockResolvedValueOnce({ data: { data: fakeData } })

      const store = useCoursesStore()
      const result = await store.checkout('php-mastery')

      expect(api.post).toHaveBeenCalledWith('/courses/php-mastery/checkout')
      expect(result).toEqual(fakeData)
      expect(result.order_id).toBe(42)
      expect(result.config.amount).toBe(4999)
      expect(store.loading).toBe(false)
      expect(store.error).toBeNull()
    })

    it('sets error state and re-throws on API failure', async () => {
      api.post.mockRejectedValueOnce({
        response: { status: 500, data: { message: 'Internal server error' } },
      })

      const store = useCoursesStore()
      await expect(store.checkout('some-course')).rejects.toBeTruthy()

      expect(store.error).toBe('Internal server error')
      expect(store.loading).toBe(false)
    })

    it('re-throws 409 (already enrolled) without masking', async () => {
      const err = { response: { status: 409, data: { message: 'Ya estás inscrito' } } }
      api.post.mockRejectedValueOnce(err)

      const store = useCoursesStore()
      await expect(store.checkout('some-course')).rejects.toMatchObject({
        response: { status: 409 },
      })

      expect(store.error).toBe('Ya estás inscrito')
    })

    it('re-throws 422 (free course) without masking', async () => {
      const err = {
        response: { status: 422, data: { message: 'Este curso es gratuito' } },
      }
      api.post.mockRejectedValueOnce(err)

      const store = useCoursesStore()
      await expect(store.checkout('free-course')).rejects.toMatchObject({
        response: { status: 422 },
      })

      expect(store.error).toBe('Este curso es gratuito')
    })

    it('uses fallback error message when response has no message', async () => {
      api.post.mockRejectedValueOnce(new Error('Network Error'))

      const store = useCoursesStore()
      await expect(store.checkout('some-course')).rejects.toThrow()

      expect(store.error).toBe('Error al iniciar el pago')
    })

    it('resets loading to false even on error', async () => {
      api.post.mockRejectedValueOnce({ response: { data: {} } })

      const store = useCoursesStore()
      try { await store.checkout('x') } catch { /* expected */ }

      expect(store.loading).toBe(false)
    })
  })

  // ── confirmPayment ──────────────────────────────────────────────────────────

  describe('confirmPayment({ id, clientTransactionId })', () => {
    it('returns status, enrolled:true, and course_slug on approval', async () => {
      const fakeData = {
        status: 'paid',
        enrolled: true,
        course_slug: 'php-mastery',
      }

      api.post.mockResolvedValueOnce({ data: { data: fakeData } })

      const store = useCoursesStore()
      const result = await store.confirmPayment({
        id: 7,
        clientTransactionId: 'order-42-abc',
      })

      expect(api.post).toHaveBeenCalledWith('/payments/confirm', {
        id: 7,
        clientTransactionId: 'order-42-abc',
      })
      expect(result.enrolled).toBe(true)
      expect(result.course_slug).toBe('php-mastery')
      expect(store.loading).toBe(false)
      expect(store.error).toBeNull()
    })

    it('returns enrolled:false when payment is declined', async () => {
      const fakeData = {
        status: 'failed',
        enrolled: false,
        course_slug: 'php-mastery',
      }

      api.post.mockResolvedValueOnce({ data: { data: fakeData } })

      const store = useCoursesStore()
      const result = await store.confirmPayment({
        id: 8,
        clientTransactionId: 'order-declined',
      })

      expect(result.enrolled).toBe(false)
      expect(result.status).toBe('failed')
      expect(store.error).toBeNull()
    })

    it('sets error state and re-throws on API failure', async () => {
      api.post.mockRejectedValueOnce({
        response: { data: { message: 'Order not found' } },
      })

      const store = useCoursesStore()
      await expect(
        store.confirmPayment({ id: 99, clientTransactionId: 'x' })
      ).rejects.toBeTruthy()

      expect(store.error).toBe('Order not found')
      expect(store.loading).toBe(false)
    })

    it('uses fallback error message when response has no message', async () => {
      api.post.mockRejectedValueOnce(new Error('timeout'))

      const store = useCoursesStore()
      try {
        await store.confirmPayment({ id: 1, clientTransactionId: 'y' })
      } catch { /* expected */ }

      expect(store.error).toBe('Error al confirmar el pago')
    })

    it('resets loading to false even on error', async () => {
      api.post.mockRejectedValueOnce({ response: { data: {} } })

      const store = useCoursesStore()
      try {
        await store.confirmPayment({ id: 1, clientTransactionId: 'z' })
      } catch { /* expected */ }

      expect(store.loading).toBe(false)
    })
  })
})
