/**
 * Router tests for the /cursos route (PR3).
 * Validates that the Cursos route resolves correctly.
 */
import { describe, it, expect } from 'vitest'
import router from '../router/index.js'

function findRoute(name) {
  return router.getRoutes().find((r) => r.name === name)
}

describe('cursos route', () => {
  it('/cursos route exists with name Cursos', () => {
    const route = findRoute('Cursos')
    expect(route).toBeDefined()
    expect(route.path).toBe('/cursos')
  })

  it('/cursos route has no requiresAdmin meta', () => {
    const route = findRoute('Cursos')
    expect(route.meta?.requiresAdmin).toBeFalsy()
  })

  it('/cursos route has no requiresAuth meta', () => {
    const route = findRoute('Cursos')
    expect(route.meta?.requiresAuth).toBeFalsy()
  })
})
