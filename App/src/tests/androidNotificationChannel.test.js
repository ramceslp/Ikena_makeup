import { describe, it, expect } from 'vitest'
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { DEFAULT_NOTIFICATION_CHANNEL_ID } from '../services/pushNotifications.js'

// ---------------------------------------------------------------------------
// The default notification channel id is necessarily written down TWICE: once
// in the Android string resource that AndroidManifest.xml points FCM at (which
// decides where an incoming push lands), and once in JS (which decides which
// channel the app actually creates at runtime). Nothing in either toolchain
// links them -- Android resources are invisible to the WebView bundle -- so a
// rename on one side leaves the manifest naming a channel the app never
// creates. FCM then quietly swaps in its own `fcm_fallback_notification_channel`
// ("Miscellaneous"), which is exactly the generic, unmanageable entry this whole
// change exists to remove. Nothing crashes and nothing is logged; the feature
// just silently reverts to the behavior it replaced.
//
// These tests are that missing link. They read the real Android sources rather
// than a fixture, so the duplication cannot drift unnoticed.
// ---------------------------------------------------------------------------

// Resolved from process.cwd() rather than import.meta.url: under Vitest's jsdom
// environment the global URL is jsdom's whatwg-url implementation, and the
// object it produces does not interoperate with node:url's fileURLToPath --
// which rejects it with "The URL must be of scheme file" even though
// import.meta.url is a perfectly good file:// URL. cwd is unambiguous here
// anyway: Vitest's root is App/ (vite.config.js lives there) and CI runs this
// job with working-directory: App.
const androidRes = (file) => {
  const path = resolve(process.cwd(), 'android/app/src/main', file)

  // A wrong cwd would otherwise surface as a bare ENOENT that reads like the
  // Android file was deleted.
  expect(existsSync(path), `expected Android source at ${path} (cwd: ${process.cwd()})`).toBe(true)

  return readFileSync(path, 'utf8')
}

describe('Android default notification channel wiring', () => {
  it('res/values/strings.xml declares the same channel id the app creates at runtime', () => {
    const strings = androidRes('res/values/strings.xml')

    const match = strings.match(
      /<string\s+name="default_notification_channel_id"[^>]*>([^<]+)<\/string>/
    )

    expect(match, 'strings.xml is missing <string name="default_notification_channel_id">').not.toBeNull()
    expect(match[1].trim()).toBe(DEFAULT_NOTIFICATION_CHANNEL_ID)
  })

  /**
   * Without this meta-data Android logs "Missing Default Notification Channel
   * metadata in AndroidManifest" and routes every push to FCM's
   * `fcm_fallback_notification_channel`, which shows up in the user's system
   * settings under a generic name they cannot meaningfully manage.
   */
  it('AndroidManifest.xml points FCM at that string resource', () => {
    const manifest = androidRes('AndroidManifest.xml')

    expect(manifest).toMatch(
      /android:name="com\.google\.firebase\.messaging\.default_notification_channel_id"/
    )
    expect(manifest).toMatch(/android:value="@string\/default_notification_channel_id"/)
  })

  /**
   * A channel id is a stable identifier, not copy: it is compared verbatim
   * against the FCM payload and against the channel already created on every
   * installed device. Marking it translatable would let a localization pass
   * "translate" it and break delivery for those users only.
   */
  it('marks the channel id string as non-translatable', () => {
    const strings = androidRes('res/values/strings.xml')

    expect(strings).toMatch(
      /<string\s+name="default_notification_channel_id"\s+translatable="false"\s*>/
    )
  })
})
