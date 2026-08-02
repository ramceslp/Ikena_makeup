/**
 * Router test for the /admin/reportes route (PR2b).
 * Mirrors the pattern in cursosRouter.test.js.
 */
import { describe, it, expect } from 'vitest'
import router from '../router/index.js'

function findRoute(name) {
  return router.getRoutes().find((r) => r.name === name)
}

describe('admin reports route', () => {
  it('/admin/reportes route exists with name AdminReports', () => {
    const route = findRoute('AdminReports')
    expect(route).toBeDefined()
    expect(route.path).toBe('/admin/reportes')
  })

  it('/admin/reportes route requires admin', () => {
    const route = findRoute('AdminReports')
    expect(route.meta?.requiresAdmin).toBe(true)
  })
})
