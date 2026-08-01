import { Browser } from '@capacitor/browser'

// Launcher for live-session meeting links.
//
// A Meet or Zoom room cannot render inside the app's own WebView — it needs
// camera and microphone permissions the WebView does not grant, and on Android
// the system Custom Tab is what hands the URL off to the installed Meet or
// Zoom app. So this is the same Browser.open() handoff the checkout flow uses,
// for the same reason: the destination is not ours to render.
//
// It is deliberately NOT startCheckoutHandoff. That function POSTs a purchase
// snapshot and consumes a single-use token; a meeting link is neither, and
// routing it through there would mint handoff tokens for a page that takes no
// payment.

/**
 * isAllowedMeetingUrl — the guard that decides whether a meeting link may be
 * handed to Browser.open().
 *
 * The API returns `meeting_url: null` for every session outside its window,
 * which is the common case, not an error. Without this check that null reaches
 * Browser.open()'s web fallback as `window.open(null, '_blank')` and silently
 * opens a blank tab, so a student tapping "entrar" before the room opens gets
 * an empty browser instead of the schedule.
 *
 * https only, with no development exemption: unlike a checkout URL, this one
 * never points at a local dev server — it always comes from Meet, Zoom or
 * Teams, and App\Rules\MeetingUrl has already rejected anything else
 * server-side.
 *
 * @param {unknown} url
 * @returns {boolean}
 */
export function isAllowedMeetingUrl(url) {
  return typeof url === 'string' && url.startsWith('https://')
}

/**
 * Open a live session's room in the system browser.
 *
 * @param {unknown} url the lesson's meeting_url, which may be null
 * @returns {Promise<boolean>} whether a room was actually opened
 */
export async function openMeeting(url) {
  if (!isAllowedMeetingUrl(url)) return false

  await Browser.open({ url })

  return true
}
