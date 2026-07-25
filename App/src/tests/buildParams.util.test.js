import { describe, it, expect } from 'vitest'
import { buildParams } from '../stores/shared/buildParams.js'

// Shared filter-stripping helper used by courses.js, services.js and
// products.js (see stores/shared/buildParams.js). Each store's own tests
// already exercise this transitively via fetchCourses/fetchServices/
// fetchProducts; this covers the helper directly/in isolation.
describe('buildParams', () => {
  it('returns an empty object for no filters', () => {
    expect(buildParams()).toEqual({})
    expect(buildParams({})).toEqual({})
  })

  it('keeps truthy and falsy-but-valid values (0, false)', () => {
    expect(buildParams({ page: 1, per_page: 0, active: false })).toEqual({
      page: 1,
      per_page: 0,
      active: false,
    })
  })

  it('strips empty-string, null and undefined values', () => {
    expect(buildParams({ page: 1, search: '', category: null, sort: undefined })).toEqual({
      page: 1,
    })
  })
})
