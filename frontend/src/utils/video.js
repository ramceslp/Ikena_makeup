/**
 * Resolves a video URL into a typed descriptor with embed/src information.
 * @param {string|null|undefined} url
 * @returns {{ type: 'youtube'|'vimeo'|'mp4'|'unknown', embedUrl?: string, src?: string }}
 */
export function resolveVideo(url) {
  if (!url || typeof url !== 'string' || url.trim() === '') {
    return { type: 'unknown' }
  }

  const trimmed = url.trim()

  // YouTube: youtube.com/watch?v=<id>
  const ytWatch = trimmed.match(/(?:youtube\.com\/watch\?(?:.*&)?v=)([\w-]+)/)
  if (ytWatch) {
    return { type: 'youtube', embedUrl: `https://www.youtube.com/embed/${ytWatch[1]}` }
  }

  // YouTube: youtu.be/<id>
  const ytShort = trimmed.match(/youtu\.be\/([\w-]+)/)
  if (ytShort) {
    return { type: 'youtube', embedUrl: `https://www.youtube.com/embed/${ytShort[1]}` }
  }

  // YouTube: youtube.com/embed/<id>
  const ytEmbed = trimmed.match(/youtube\.com\/embed\/([\w-]+)/)
  if (ytEmbed) {
    return { type: 'youtube', embedUrl: `https://www.youtube.com/embed/${ytEmbed[1]}` }
  }

  // Vimeo: player.vimeo.com/video/<id>
  const vimeoPlayer = trimmed.match(/player\.vimeo\.com\/video\/(\d+)/)
  if (vimeoPlayer) {
    return { type: 'vimeo', embedUrl: `https://player.vimeo.com/video/${vimeoPlayer[1]}` }
  }

  // Vimeo: vimeo.com/<id>
  const vimeoDirect = trimmed.match(/(?:^|[^a-z])vimeo\.com\/(\d+)/)
  if (vimeoDirect) {
    return { type: 'vimeo', embedUrl: `https://player.vimeo.com/video/${vimeoDirect[1]}` }
  }

  // Direct MP4
  if (/^https?:\/\/.+\.mp4(\?.*)?$/i.test(trimmed)) {
    return { type: 'mp4', src: trimmed }
  }

  return { type: 'unknown' }
}
