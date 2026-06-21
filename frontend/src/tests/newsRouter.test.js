/**
 * Router tests for news routes (PR2).
 * Validates that all news + admin post routes resolve correctly and carry proper meta.
 */
import { describe, it, expect } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'

// We import the real router to extract route definitions, but to avoid
// component loading complexity, we validate route shape via the route config.
import router from '../router/index.js'

function findRoute(name) {
  return router.getRoutes().find((r) => r.name === name)
}

describe('news routes — public', () => {
  it('/noticias route exists with name News', () => {
    const route = findRoute('News')
    expect(route).toBeDefined()
    expect(route.path).toBe('/noticias')
  })

  it('/noticias route has no requiresAdmin meta', () => {
    const route = findRoute('News')
    expect(route.meta?.requiresAdmin).toBeFalsy()
  })

  it('/noticias/:slug route exists with name NewsDetail', () => {
    const route = findRoute('NewsDetail')
    expect(route).toBeDefined()
    expect(route.path).toBe('/noticias/:slug')
  })

  it('/noticias/:slug route has no requiresAdmin meta', () => {
    const route = findRoute('NewsDetail')
    expect(route.meta?.requiresAdmin).toBeFalsy()
  })
})

describe('news routes — admin', () => {
  it('/admin/posts route exists with name AdminPosts', () => {
    const route = findRoute('AdminPosts')
    expect(route).toBeDefined()
    expect(route.path).toBe('/admin/posts')
  })

  it('/admin/posts route has requiresAdmin meta', () => {
    const route = findRoute('AdminPosts')
    expect(route.meta?.requiresAdmin).toBe(true)
  })

  it('/admin/posts/new route exists with name AdminPostCreate', () => {
    const route = findRoute('AdminPostCreate')
    expect(route).toBeDefined()
    expect(route.path).toBe('/admin/posts/new')
  })

  it('/admin/posts/new route has requiresAdmin meta', () => {
    const route = findRoute('AdminPostCreate')
    expect(route.meta?.requiresAdmin).toBe(true)
  })

  it('/admin/posts/:id/edit route exists with name AdminPostEdit', () => {
    const route = findRoute('AdminPostEdit')
    expect(route).toBeDefined()
    expect(route.path).toBe('/admin/posts/:id/edit')
  })

  it('/admin/posts/:id/edit route has requiresAdmin meta', () => {
    const route = findRoute('AdminPostEdit')
    expect(route.meta?.requiresAdmin).toBe(true)
  })
})
