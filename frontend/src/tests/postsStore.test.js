import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../services/api.js', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
    interceptors: {
      request: { use: vi.fn() },
      response: { use: vi.fn() },
    },
  },
}))

import api from '../services/api.js'
import { usePostsStore } from '../stores/posts.js'

// ---------------------------------------------------------------------------
// fetchPosts
// ---------------------------------------------------------------------------

describe('posts store — fetchPosts', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchPosts populates posts and postMeta from API response', async () => {
    const fakePosts = [
      { id: 1, title: 'Noticia de prueba', slug: 'noticia-prueba', type: 'noticia', is_published: true },
      { id: 2, title: 'Nuevo Curso', slug: 'nuevo-curso', type: 'nuevo_curso', is_published: true },
    ]
    api.get.mockResolvedValueOnce({
      data: { data: fakePosts, meta: { current_page: 1, last_page: 2, total: 20 } },
    })

    const store = usePostsStore()
    await store.fetchPosts()

    expect(store.posts).toEqual(fakePosts)
    expect(store.postMeta.total).toBe(20)
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('fetchPosts calls GET /posts with merged filter params', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = usePostsStore()
    await store.fetchPosts({ search: 'maquillaje', page: 2 })

    expect(api.get).toHaveBeenCalledWith('/posts', {
      params: expect.objectContaining({ search: 'maquillaje', page: 2 }),
    })
  })

  it('fetchPosts strips empty string and null filter values', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = usePostsStore()
    await store.fetchPosts({ search: '', type: null })

    const callArgs = api.get.mock.calls[0]
    expect(callArgs[1].params).not.toHaveProperty('search')
    expect(callArgs[1].params).not.toHaveProperty('type')
  })

  it('fetchPosts sets error state on API failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'Error del servidor' } },
    })

    const store = usePostsStore()
    await store.fetchPosts()

    expect(store.posts).toEqual([])
    expect(store.error).toBe('Error del servidor')
    expect(store.loading).toBe(false)
  })

  it('fetchPosts loading toggles true then false', async () => {
    let loadingDuringCall = false
    api.get.mockImplementationOnce(async () => {
      loadingDuringCall = true
      return { data: { data: [], meta: {} } }
    })

    const store = usePostsStore()
    await store.fetchPosts()

    expect(loadingDuringCall).toBe(true)
    expect(store.loading).toBe(false)
  })

  // FIX 7 — fetchPosts must NOT mutate this.filters (cross-navigation contamination)
  it('fetchPosts does NOT persist params into this.filters across calls', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    const store = usePostsStore()
    const filtersBefore = JSON.stringify(store.filters)

    // First call with explicit type + page filters
    await store.fetchPosts({ type: 'oferta', page: 3 })

    // store.filters must be unchanged
    expect(JSON.stringify(store.filters)).toBe(filtersBefore)

    vi.clearAllMocks()
    api.get.mockResolvedValue({ data: { data: [], meta: {} } })

    // Second call with NO args — must NOT carry type/page from the first call
    await store.fetchPosts()

    const secondCallArgs = api.get.mock.calls[0]
    expect(secondCallArgs[1].params).not.toHaveProperty('type')
    expect(secondCallArgs[1].params).not.toHaveProperty('page')
  })
})

// ---------------------------------------------------------------------------
// fetchPost (public detail by slug)
// ---------------------------------------------------------------------------

describe('posts store — fetchPost', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchPost calls GET /posts/{slug} and populates currentPost', async () => {
    const fakeDetail = {
      id: 1,
      title: 'Noticia detallada',
      slug: 'noticia-detallada',
      body: '<p>Contenido</p>',
      type: 'noticia',
      is_published: true,
    }
    api.get.mockResolvedValueOnce({ data: { data: fakeDetail } })

    const store = usePostsStore()
    const result = await store.fetchPost('noticia-detallada')

    expect(api.get).toHaveBeenCalledWith('/posts/noticia-detallada')
    expect(store.currentPost).toEqual(fakeDetail)
    expect(result).toEqual(fakeDetail)
  })

  it('fetchPost sets error and rethrows on failure (404 for drafts)', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'No encontrado' } },
    })

    const store = usePostsStore()
    await expect(store.fetchPost('borrador-oculto')).rejects.toBeDefined()
    expect(store.error).toBe('No encontrado')
    expect(store.loading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// fetchLatest
// ---------------------------------------------------------------------------

describe('posts store — fetchLatest', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchLatest calls GET /posts/latest and returns list', async () => {
    const fakeLatest = [
      { id: 3, title: 'Post 3', slug: 'post-3', type: 'noticia' },
      { id: 2, title: 'Post 2', slug: 'post-2', type: 'oferta' },
    ]
    api.get.mockResolvedValueOnce({ data: { data: fakeLatest } })

    const store = usePostsStore()
    const result = await store.fetchLatest()

    expect(api.get).toHaveBeenCalledWith('/posts/latest')
    expect(result).toEqual(fakeLatest)
  })

  it('fetchLatest returns empty array on error without throwing', async () => {
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const store = usePostsStore()
    const result = await store.fetchLatest()

    expect(result).toEqual([])
  })
})

// ---------------------------------------------------------------------------
// fetchFeatured
// ---------------------------------------------------------------------------

describe('posts store — fetchFeatured', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchFeatured calls GET /posts/featured and returns the post', async () => {
    const fakePost = { id: 1, title: 'Destacado', slug: 'destacado', is_featured: true }
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    const result = await store.fetchFeatured()

    expect(api.get).toHaveBeenCalledWith('/posts/featured')
    expect(result).toEqual(fakePost)
  })

  it('fetchFeatured returns null on error without throwing', async () => {
    api.get.mockRejectedValueOnce(new Error('Network error'))

    const store = usePostsStore()
    const result = await store.fetchFeatured()

    expect(result).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// fetchAdminPosts (includes drafts)
// ---------------------------------------------------------------------------

describe('posts store — fetchAdminPosts', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAdminPosts calls GET /admin/posts and populates posts', async () => {
    const adminList = [
      { id: 1, title: 'Publicado', is_published: true },
      { id: 2, title: 'Borrador', is_published: false },
    ]
    api.get.mockResolvedValueOnce({
      data: { data: adminList, meta: { current_page: 1, last_page: 1, total: 2 } },
    })

    const store = usePostsStore()
    await store.fetchAdminPosts()

    expect(api.get).toHaveBeenCalledWith('/admin/posts', expect.anything())
    expect(store.posts).toEqual(adminList)
    expect(store.postMeta.total).toBe(2)
  })

  it('fetchAdminPosts does NOT mutate store.filters', async () => {
    api.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    const store = usePostsStore()
    const filtersBefore = JSON.stringify(store.filters)

    await store.fetchAdminPosts({ search: 'test' })

    expect(JSON.stringify(store.filters)).toBe(filtersBefore)
  })

  it('fetchAdminPosts sets error on failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'No autorizado' } },
    })

    const store = usePostsStore()
    await store.fetchAdminPosts()

    expect(store.error).toBe('No autorizado')
    expect(store.loading).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// fetchAdminPost (detail by id)
// ---------------------------------------------------------------------------

describe('posts store — fetchAdminPost', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetchAdminPost calls GET /admin/posts/{id} and populates currentPost', async () => {
    const fakePost = { id: 7, title: 'Borrador Admin', is_published: false, body: '<p>Draft</p>' }
    api.get.mockResolvedValueOnce({ data: { data: fakePost } })

    const store = usePostsStore()
    const result = await store.fetchAdminPost(7)

    expect(api.get).toHaveBeenCalledWith('/admin/posts/7')
    expect(store.currentPost).toEqual(fakePost)
    expect(result).toEqual(fakePost)
  })

  it('fetchAdminPost sets error and rethrows on failure', async () => {
    api.get.mockRejectedValueOnce({
      response: { data: { message: 'No encontrado' } },
    })

    const store = usePostsStore()
    await expect(store.fetchAdminPost(999)).rejects.toBeDefined()
    expect(store.error).toBe('No encontrado')
  })
})

// ---------------------------------------------------------------------------
// Admin write actions
// ---------------------------------------------------------------------------

describe('posts store — admin write actions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('createPost POSTs FormData to /admin/posts and returns created post', async () => {
    const created = { id: 5, title: 'Nuevo Post', slug: 'nuevo-post' }
    api.post.mockResolvedValueOnce({ data: { data: created } })

    const store = usePostsStore()
    const fd = new FormData()
    fd.append('title', 'Nuevo Post')

    const result = await store.createPost(fd)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/posts',
      fd,
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(created)
  })

  it('updatePost POSTs FormData with _method=PATCH to /admin/posts/{id}', async () => {
    const updated = { id: 3, title: 'Post Actualizado' }
    api.post.mockResolvedValueOnce({ data: { data: updated } })

    const store = usePostsStore()
    const fd = new FormData()
    fd.append('title', 'Post Actualizado')

    const result = await store.updatePost(3, fd)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/posts/3',
      fd,
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(updated)
  })

  it('deletePost DELETEs /admin/posts/{id}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = usePostsStore()
    await store.deletePost(7)

    expect(api.delete).toHaveBeenCalledWith('/admin/posts/7')
  })

  it('uploadCover POSTs file to /admin/posts/{id}/cover', async () => {
    const coverData = { cover_image_url: 'http://example.com/cover.jpg' }
    api.post.mockResolvedValueOnce({ data: { data: coverData } })

    const store = usePostsStore()
    const file = new File(['img'], 'cover.jpg', { type: 'image/jpeg' })

    const result = await store.uploadCover(1, file)

    expect(api.post).toHaveBeenCalledWith(
      '/admin/posts/1/cover',
      expect.any(FormData),
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(coverData)
  })

  it('deleteCover DELETEs /admin/posts/{id}/cover', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = usePostsStore()
    await store.deleteCover(1)

    expect(api.delete).toHaveBeenCalledWith('/admin/posts/1/cover')
  })

  it('uploadImages POSTs multipart to /admin/posts/{id}/images and returns image list', async () => {
    const fakeImages = [{ id: 10, url: 'http://example.com/img.jpg', sort_order: 0 }]
    api.post.mockResolvedValueOnce({ data: { data: fakeImages } })

    const store = usePostsStore()
    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' })

    const result = await store.uploadImages(2, [file])

    expect(api.post).toHaveBeenCalledWith(
      '/admin/posts/2/images',
      expect.any(FormData),
      expect.objectContaining({ headers: expect.objectContaining({ 'Content-Type': 'multipart/form-data' }) }),
    )
    expect(result).toEqual(fakeImages)
  })

  it('deleteImage DELETEs /admin/posts/{id}/images/{imageId}', async () => {
    api.delete.mockResolvedValueOnce({})

    const store = usePostsStore()
    await store.deleteImage(2, 10)

    expect(api.delete).toHaveBeenCalledWith('/admin/posts/2/images/10')
  })

  it('reorderImages POSTs to /admin/posts/{id}/images/reorder', async () => {
    const fakeImages = [{ id: 2, url: 'http://example.com/2.jpg', sort_order: 0 }]
    api.post.mockResolvedValueOnce({ data: { data: fakeImages } })

    const store = usePostsStore()
    const result = await store.reorderImages(2, [2, 1])

    expect(api.post).toHaveBeenCalledWith(
      '/admin/posts/2/images/reorder',
      { order: [2, 1] },
    )
    expect(result).toEqual(fakeImages)
  })
})
