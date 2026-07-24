// Shared filter-stripping helper used by courses.js, services.js,
// products.js and posts.js: strips empty-string/null/undefined values out of
// a filters object before it is sent as query params, so callers can pass a
// sparse/partial filters object (e.g. from a form with unfilled fields)
// without polluting the request with meaningless empty params.
export function buildParams(filters = {}) {
  const params = {}
  for (const [key, value] of Object.entries(filters)) {
    if (value !== '' && value !== null && value !== undefined) {
      params[key] = value
    }
  }
  return params
}
