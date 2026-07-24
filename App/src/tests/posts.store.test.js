import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } },
  },
}))

import api from '../services/api.js'
import { usePostsStore } from '../stores/posts.js'

describe('posts store (trimmed: fetchFeatured/fetchLatest only, see Home.vue)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchFeatured GETs /posts/featured and returns the featured post', async () => {
    const fakePost = { id: 3, title: 'Nueva colección', slug: 'nueva-coleccion' }
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    const result = await store.fetchFeatured()

    expect(api.get).toHaveBeenCalledWith('/posts/featured')
    expect(result).toEqual(fakePost)
  })

  it('fetchFeatured resolves to null (not a throw) when the request fails', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const store = usePostsStore()
    const result = await store.fetchFeatured()

    expect(result).toBeNull()
  })

  it('fetchLatest GETs /posts/latest and returns the latest posts array', async () => {
    const fakePosts = [{ id: 1, title: 'A' }, { id: 2, title: 'B' }]
    api.get.mockResolvedValueOnce({ data: { data: fakePosts } })

    const store = usePostsStore()
    const result = await store.fetchLatest()

    expect(api.get).toHaveBeenCalledWith('/posts/latest')
    expect(result).toEqual(fakePosts)
  })

  it('fetchLatest resolves to an empty array (not a throw) when the request fails', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))

    const store = usePostsStore()
    const result = await store.fetchLatest()

    expect(result).toEqual([])
  })
})
