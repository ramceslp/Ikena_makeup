# HANDOFF — Push Notifications (news, courses, admin broadcasts)

> **Purpose of this file**: anyone with zero context — a new contributor, or a future you —
> must be able to read this single document and pick the work up without re-exploring the
> codebase. Update the **Slice status** table and **Session log** when the feature changes.

- **Change name**: `push-notifications`
- **Started**: 2026-07-29
- **Delivered**: 2026-07-30 — complete and verified end-to-end on an Android emulator
- **Base branch**: `main` (at `dcb9383`)
- **Depends on**: the `mobile-capacitor-setup` change, which delivered the FCM transport this
  one builds on

**Read this first if you are configuring Firebase**: §8 opens with the distinction between
`google-services.json` and the service account key. Confusing the two is the single most
likely mistake in the whole setup, and it was hit in practice.

*Originally written under the gitignored `sdd/` working directory; moved here so it travels
with the repository. Code comments across the feature reference it by this path.*

---

## 1. Goal

Three user-facing capabilities:

1. **Automatic push when a news post is published** → opens that post in the mobile app.
2. **Automatic push when a course becomes available** → opens that course in the mobile app.
3. **Admin section** to compose and send custom push notifications, plus a **history**
   of everything that was ever sent (automatic *and* custom).

---

## 2. What ALREADY exists (do not rebuild any of this)

The `mobile-capacitor-setup` change already shipped a complete, working FCM transport.
This was verified by reading the source, not assumed.

| Piece | Location | State |
|---|---|---|
| FCM package | `laravel-notification-channels/fcm ^6.1` in `backend/composer.json` | installed |
| Firebase config | `backend/config/firebase.php` | present, reads `FIREBASE_CREDENTIALS` |
| Token storage | `backend/app/Models/DeviceToken.php`, migration `2026_07_21_100005_create_device_tokens_table.php` | `token` UNIQUE, FK `user_id`, `platform`, `last_used_at` |
| Register/unregister API | `backend/app/Http/Controllers/Api/DeviceTokenController.php` — `POST`/`DELETE /api/device-tokens` (auth:sanctum) | idempotent upsert on `token` |
| Token routing | `backend/app/Models/User.php::routeNotificationForFcm()` (line ~89) | returns **array** of that user's tokens; N+1-safe via `relationLoaded` |
| Dead-token cleanup | `backend/app/Listeners/InvalidateFcmDeviceToken.php` | auto-discovered; deletes rows on `NotRegistered`/`InvalidToken` |
| Example notification | `backend/app/Notifications/AppointmentReminder.php` | the shape to copy for new notifications |
| App-side registration | `App/src/services/pushNotifications.js`, `App/src/stores/push.js` (+ 3 test files) | complete, silent-by-design on every failure path |
| App kill switch | `App/src/config/env.js` → `PUSH_ENABLED` (line 148) | **fails safe**: unset ⇒ OFF |

### 2.1 Critical discovery — `FcmChannel` already multicasts

`backend/vendor/laravel-notification-channels/fcm/src/FcmChannel.php`:

```php
const int TOKENS_PER_REQUEST = 500;

return Collection::make($tokens)
    ->chunk(self::TOKENS_PER_REQUEST)
    ->map(fn ($tokens) => $client->sendMulticast($message, $tokens->all()))
    ->map(fn (MulticastSendReport $report) => $this->checkReportForFailures(...));
```

**Consequences — this shaped the whole design:**

- We do **NOT** write chunking or multicast code. Hand it the full token list.
- `send()` **returns** `Collection<MulticastSendReport>` → exact success/failure counts
  for the history table come for free.
- It dispatches `NotificationFailed` per failed token → the existing
  `InvalidateFcmDeviceToken` listener keeps working for broadcasts with **zero new code**.
- Therefore we call `FcmChannel::send()` **directly** from our broadcaster rather than
  `Notification::send()`, because the facade swallows the return value and we need the counts.

---

## 3. Gaps found during exploration (beyond what was asked)

1. **The app never handles a received notification.** `pushNotifications.js` only listens for
   `registration` / `registrationError`. There is no `pushNotificationReceived` or
   `pushNotificationActionPerformed` listener anywhere in `App/src` (verified by grep).
   Tapping a notification today does nothing useful. Covered by Slice 5.
2. **Firebase is not configured.** `App/android/app/google-services.json` does not exist and
   `FIREBASE_CREDENTIALS` is unset. Nothing can reach a real phone yet. Covered by Slice 6
   (manual, requires Firebase console access).
3. **🔴 The mobile app has no news section — and its existing news links are broken.**
   Found while wiring the deep links in Slice 2. `App/src/components/home/LatestNewsGrid.vue`
   (line 19) and `FeaturedNewsHero.vue` (line 21) both link to `/noticias/{slug}`, and the
   grid header links to `/noticias`. **Neither route exists** in `App/src/router/index.js`,
   whose only routes are `/`, `/login`, `/cursos`, `/cursos/:slug`, `/products`,
   `/products/:slug`, `/services`, `/services/:slug`, `/cart`, `/profile`.
   Tapping "Leer más" on a news card in the app today does nothing.

   This is a **pre-existing bug**, not one this change introduced — but it blocked the news
   deep link, which had nowhere to land. **Fixed in Slice 5b** (commit `92d944f`).

   The backend side is already there: `GET /api/posts` and `GET /api/posts/{slug}` are public
   (`backend/routes/api.php` lines 59–62). `App/src/stores/posts.js` only implements
   `fetchLatest()` and `fetchFeatured()`, so it needs `fetchAll()` and `fetchBySlug()`.

   The course deep link `/cursos/{slug}` **does** resolve — that half works today.

---

## 4. Decisions (confirmed with the user 2026-07-29)

| # | Decision | Chosen | Why |
|---|---|---|---|
| D1 | Delivery strategy | **Per-token fan-out** (not FCM topics) | Reuses `device_tokens` + `FcmChannel` + `InvalidateFcmDeviceToken` as-is. Enables later segmentation (only enrolled, only buyers) and exact per-send delivery counts, which the history requires. Topics cannot say *who* received it and break token invalidation. |
| D2 | Firebase setup | **Build behind the flag** | Implement and test against a mocked `Kreait\Firebase\Contract\Messaging`, mirroring the existing `FakeGateway` pattern in `app/Services/Payments/Gateways/`. `PUSH_ENABLED` stays `false`. Firebase becomes a documented final manual step — development is not blocked today. |
| D3 | History scope | **Everything sent** | One `push_notification_logs` timeline covering automatic *and* custom sends. Answers "was the course-published push actually sent?" from the panel. |
| D4 | On notification tap | **Deep link** | Payload carries `data.route`; the app routes to it. No in-app inbox in this change (explicitly out of scope). |

### Rejected

- **FCM topics** — no segmentation, imprecise history, breaks token invalidation.
- **Hybrid topics + tokens** — two send paths, two failure surfaces, two history models. Not justified at this scale.
- **In-app notification inbox** — separate endpoint, per-user read state, new store and view. Out of scope; revisit later.
- **Model observers for triggers** — see §5.3.

---

## 5. Architecture

### 5.1 Backend kill switch (MANDATORY — do this first)

`kreait` throws on first use when `FIREBASE_CREDENTIALS` is unset. Without a backend-side
flag, **publishing any post would throw in dev and CI**.

New `backend/config/push.php`:

```php
return [
    'enabled' => env('PUSH_ENABLED', false),   // fail safe, mirrors App's VITE_PUSH_ENABLED
];
```

When disabled: still write the log row with `status = 'skipped'` (the attempt is
informative for the admin), but never dispatch the job.

### 5.2 New backend pieces

```
config/push.php                                  kill switch
database/migrations/*_create_push_notification_logs_table.php
database/migrations/*_add_push_notified_at_to_posts_and_courses.php
app/Models/PushNotificationLog.php
app/Services/Push/PushBroadcaster.php            calls FcmChannel::send(), returns counts
app/Services/Push/PushDispatcher.php             builds log row + dispatches job
app/Jobs/BroadcastPushNotification.php           queued (QUEUE_CONNECTION=database)
app/Notifications/PushBroadcast.php               ONE generic FCM message — see below
app/Services/Push/DTOs/BroadcastResult.php
app/Http/Controllers/Api/Admin/PushNotificationController.php
app/Http/Requests/Admin/StorePushNotificationRequest.php
app/Http/Resources/PushNotificationLogResource.php
```

**`push_notification_logs` schema**

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `type` | string | `post.published` \| `course.published` \| `custom` |
| `title` | string | |
| `body` | text | |
| `data` | json nullable | `{ route: '/noticias/slug' }` |
| `audience` | string | `all` for now; the seam for future segments |
| `sent_by` | FK users nullable | admin who sent; `null` for automatic |
| `recipients_count` | int | tokens targeted |
| `success_count` | int | from `MulticastSendReport` |
| `failure_count` | int | from `MulticastSendReport` |
| `status` | string | `pending` \| `sent` \| `failed` \| `skipped` |
| `sent_at` | timestamp nullable | |
| timestamps | | |

**`push_notified_at`** (nullable timestamp) on **both** `posts` and `courses` — the
idempotency guard. A notification fires only when the column is `NULL`, then it is stamped.
This is what prevents a re-send when an admin edits an already-published post, or
unpublishes and republishes a course.

**Design change made during Slice 1** — the plan originally called for one Notification
subclass per trigger (`NewPostPublished`, `NewCoursePublished`, `CustomAdminPush`). Replaced
with a **single generic `PushBroadcast(title, body, data)`**, because:

- The queued job then carries only a log ID — no Eloquent model serialized into a job payload.
- The log row becomes the single source of truth for what was sent, so the history cannot
  drift from the actual message. The history *is* the message.
- Per-trigger wording moves to `PushDispatcher` (Slice 2), which is where it belongs anyway.

`PushBroadcast` also casts every `data` value to string: FCM rejects non-string data values
with an opaque API error rather than a useful validation message.

**Broadcaster sketch** (returns real counts, keeps invalidation alive):

```php
$notifiable = (new AnonymousNotifiable)->route('fcm', $tokens);
$reports = app(FcmChannel::class)->send($notifiable, $notification);   // Collection<MulticastSendReport>
$success = $reports->sum(fn ($r) => count($r->successes()->getItems()));
$failure = $reports->sum(fn ($r) => count($r->failures()->getItems()));
```

### 5.3 Trigger hook points

| Trigger | Hook | Note |
|---|---|---|
| News published | `Api/Admin/PostController::store()` and `::update()` | fire when `is_published` is true **and** `push_notified_at IS NULL` |
| Course available | `Api/Instructor/CourseController::publish()` (line ~141, `$course->update(['is_published' => true])`) | **the single publish path** |

Two facts that make this clean and were verified in source:

- `Instructor\CourseController::store()` hard-codes `'is_published' => false` (line 52), so a
  course can only ever become available through `publish()`. One hook point, not two —
  despite the `courses` migration defaulting `is_published` to `true`.
- Courses are **not** managed under `Api/Admin/` at all. There is no `Admin\CourseController`.
  Do not go looking for one.

**Why explicit controller hooks over model observers**: observers would also fire from
seeders, factories and tinker, and the project's existing precedent is explicit
(`BookingConfirmed` is dispatched from `CheckoutController::confirm()`). Predictable and
directly testable. Tradeoff accepted: a future publish path added elsewhere must remember
to call the dispatcher.

### 5.4 Admin API

```
GET  /api/admin/push-notifications         paginated history, newest first, ?type= filter
POST /api/admin/push-notifications         send custom  { title, body, route? }  -> 201
GET  /api/admin/push-notifications/stats   { device_count, push_enabled }
```

Registered inside the existing `Route::middleware('admin')->prefix('admin')` group in
`backend/routes/api.php`.

**Response shape** for a history row (`PushNotificationLogResource`):

```json
{
  "id": 12, "type": "custom", "title": "Promo 2x1", "body": "Solo por hoy",
  "route": "/cursos", "audience": "all", "status": "sent",
  "recipients_count": 415, "success_count": 412, "failure_count": 3,
  "sent_by": { "id": 1, "name": "Ikena" },
  "sent_at": "2026-07-29T12:04:00-05:00", "created_at": "..."
}
```

`sent_by` is `null` for automatic triggers — render those as "Sistema".
`status: "skipped"` means Firebase is not configured; the UI must show that distinctly, not
as a successful send.

### 5.5 Frontend (admin panel)

- `frontend/src/views/admin/AdminNotifications.vue` — compose card on top, history table below.
- Route `/admin/notificaciones` in `frontend/src/router/index.js`, admin guard (existing
  pattern checks `authStore.user?.role !== 'admin'`, line ~314).
- Nav link in `frontend/src/components/NavBar.vue` (admin links array, line ~20).
- Test `frontend/src/tests/AdminNotifications.test.js`.

### 5.6 App (mobile)

- `pushNotifications.js`: add `addNotificationListeners(onReceived, onAction)` wrapping
  `pushNotificationReceived` + `pushNotificationActionPerformed`, guarded by the same
  attach-once flag already used for registration listeners.
- `stores/push.js`: wire listeners inside `init()`, after the `PUSH_ENABLED` guard.
- Deep link: `router.push(payload.notification.data.route)`.
- **Security**: validate `route` is an internal path — must start with a single `/` and not
  `//` (which would be protocol-relative and navigate off-app). Never pass an unvalidated
  push payload to the router.

---

## 6. Slice plan

Chained PRs, each independently reviewable and under the ~400-line review budget.

| # | Slice | Scope | Est. lines | Status |
|---|---|---|---|---|
| 1 | Backend foundation | `config/push.php`, both migrations, `PushNotificationLog` + factory, `PushBroadcast`, `PushBroadcaster`, `BroadcastResult`, `BroadcastPushNotification` job, 11 tests | ~350 | ✅ **done** — branch `feat/push-notifications-foundation`, 780/780 suite green |
| 2 | Automatic triggers | `PushDispatcher` (owns rules + wording, incl. `custom()`), hooks in `Admin\PostController` + `Instructor\CourseController`, 20 tests | ~300 | ✅ **done** — 800/800 suite green |
| 3 | Admin API | `Admin\PushNotificationController` (index + store + stats), FormRequest, Resource, routes, 14 tests | ~250 | ✅ **done** — 814/814 suite green |
| 4 | Admin UI | `stores/notifications.js`, `AdminNotifications.vue`, route, NavBar link, 16 tests | ~350 | ✅ **done** — 910/910 frontend green |
| 5 | App receive + deep link | `addNotificationListeners`, store wiring, `extractRoute` guard, 20 tests | ~250 | ✅ **done** |
| 5b | App news section | `/noticias` + `/noticias/:slug`, `News.vue` + `NewsDetail.vue`, `NewsCard.vue`, `fetchPosts()`/`fetchPost()`, 18 tests | ~350 | ✅ **done** — 372/372 app green, build OK |
| 6 | Firebase enablement | Firebase project + credentials configured, verified on emulator | n/a | ✅ **done — verified end-to-end on device** |

`PushDispatcher::custom()` was built in Slice 2 alongside the other dispatch rules; Slice 3
only added the HTTP surface on top of it.

**The feature is complete and verified working on a real Android emulator** — see session 2d
in §10 for the end-to-end evidence.

---

## 7. How to verify

```bash
# Backend
cd backend && composer test              # artisan test
cd backend && ./vendor/bin/pint --test   # style

# Frontend admin panel
cd frontend && npm test

# Mobile app
cd App && npm test
```

Push must stay **off** in tests. `config('push.enabled')` defaults to `false`, so the
dispatcher short-circuits and no test ever touches Firebase. Tests that exercise the send
path must bind a mock of `Kreait\Firebase\Contract\Messaging` in the container and set
`config(['push.enabled' => true])` explicitly.

**Pint is NOT enforced.** `.github/workflows/tests.yml` runs only `php artisan test`.
`./vendor/bin/pint --test` currently fails on ~180 pre-existing files, mostly
`binary_operator_spaces` — the repo deliberately uses aligned `=>` in array literals, which
Pint's default preset disagrees with. **Do not run `pint --fix`**: it would produce a huge
diff unrelated to this change. Match the surrounding style instead.

---

## 8. Slice 6 — Firebase enablement (manual, requires user)

Deliberately deferred per decision **D2**. Nothing below can be done by an agent.

### ⚠️ Two different files — do not confuse them

This is the single most likely mistake in this whole section, and it was hit in practice.

| | `google-services.json` | Service account key |
|---|---|---|
| **What** | Android **client** config | Server **credential** |
| **Where from** | Console → Project settings → *Your apps* → Android | Console → Project settings → **Service accounts** → *Generate new private key* |
| **Identifying key** | `project_info`, `client`, `configuration_version` | `"type": "service_account"`, `private_key`, `client_email` |
| **Goes in** | `App/android/app/google-services.json` | `backend/storage/app/firebase/service-account.json` |
| **Referenced by** | nothing — Gradle picks it up by location | `FIREBASE_CREDENTIALS` in `backend/.env` |
| **Secret?** | Not per Google, but this repo is **public** → gitignored | **Yes, absolutely** |

Pointing `FIREBASE_CREDENTIALS` at `google-services.json` **cannot work**:
`vendor/google/auth/src/CredentialsLoader.php:169` requires `$jsonKey['type'] == 'service_account'`,
and `google-services.json` has no `type` field at all.

**Never place the service account key anywhere under `App/android/`.** That tree is the
Android project and can end up inside a distributable APK, which anyone can decompile — that
would hand full Firebase Admin access to every user who installs the app.

### How `FIREBASE_CREDENTIALS` paths resolve

`Kreait\Laravel\Firebase\FirebaseProjectManager::resolveJsonCredentials()` (line 57):

```php
return $isRelativePath ? $this->app->basePath($credentials) : $credentials;
```

A relative path resolves against **Laravel's basePath (`backend/`)** — *not* the shell's
working directory. So the value must be relative to `backend/`, or absolute. There is no
sane way to reach a sibling directory like `App/` from here, which is another reason the
credential belongs inside `backend/`.

### Steps

1. Create the Firebase project (or reuse the one behind the existing Google Sign-In client).
2. Add an Android app with the applicationId from `App/android/app/build.gradle`.
3. Download `google-services.json` → `App/android/app/google-services.json`.
   Already gitignored (`App/.gitignore`) because this repo is public.
4. Generate a **service account** private key (see the table above — different file) and save
   it to `backend/storage/app/firebase/service-account.json`.
   Already gitignored, twice over: Laravel's own `storage/app/.gitignore` contains `*`, plus
   an explicit `/storage/app/firebase/` rule in `backend/.gitignore` documenting the intent.
5. In `backend/.env`:
   ```
   FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
   PUSH_ENABLED=true
   ```
6. Set `VITE_PUSH_ENABLED=true` in `App/.env.development.local`.
7. `php artisan config:clear` — a cached config silently keeps the old values.
8. Rebuild: `cd App && npx vite build --mode development && npx cap sync android`
   — **must** be `--mode development`; a plain `npm run build` produces a blank screen
   (`VITE_API_URL is not set`). Known gotcha from the previous change.
9. **Gradle needs JBR 21.** The `java` on this machine's PATH is 1.8.0_481, which fails with
   "Dependency requires at least JVM runtime version 11". Android Studio ships JBR 21.0.10 at
   `C:\Program Files\Android\Android Studio\jbr` (verified present).

   Syntax differs per shell — the primary shell here is Windows, not Git Bash:

   | Shell | Command |
   |---|---|
   | `cmd.exe` | `set JAVA_HOME=C:\Program Files\Android\Android Studio\jbr` |
   | PowerShell | `$env:JAVA_HOME = "C:\Program Files\Android\Android Studio\jbr"` |
   | Git Bash | `export JAVA_HOME="/c/Program Files/Android/Android Studio/jbr"` |

   **In `cmd.exe` do NOT quote the value.** `set JAVA_HOME="C:\..."` makes the quotes part of
   the variable and Gradle then fails on a path error that does not explain itself. PowerShell
   is the opposite — quotes are required there because it parses them.

   The variable is per-terminal-session; a new window needs it again.

   Verify with `echo %JAVA_HOME%` then `"%JAVA_HOME%\bin\java" -version` → expect 21.0.10.
10. **Queue.** This project's `.env` has `QUEUE_CONNECTION=sync`, so the broadcast runs
    **inline** inside the admin's request and **no worker is needed**. The admin's "send"
    request blocks on the FCM fan-out, which is fine at this scale (a few hundred tokens is
    one HTTP call, since `FcmChannel` batches 500 per request).
    If you ever switch to `QUEUE_CONNECTION=database`, you must then run
    `cd backend && php artisan queue:work` or nothing will ever send.
11. Verify end-to-end: publish a post from the admin panel, confirm the notification arrives
    and that tapping it opens the post at `/noticias/{slug}`.

**Until steps 4–5 are done, every send records `status = 'skipped'`.** That is correct
behavior, not a bug.

### Quick credential smoke test (before touching the app)

```bash
cd backend && php artisan tinker
>>> app(\Kreait\Firebase\Contract\Messaging::class);
```

Resolving without an exception means the credential is valid and readable. An exception here
means step 4 or 5 is wrong — diagnose it there, not after a 10-minute Gradle build. The error
names the exact absolute path it tried, which is the fastest way to confirm the basePath
resolution above.

### The test suite must not inherit your `.env`

`backend/phpunit.xml` forces `PUSH_ENABLED=false` (`force="true"`), the same way it already
forces `PAYMENT_DRIVER=fake`.

Without it, the moment you set `PUSH_ENABLED=true` locally, **six tests that publish a post
or a course start dispatching real broadcasts** and fail on the credential. This was hit for
real during Slice 6 bring-up. CI never catches it, because CI has no `.env` to inherit from.

Tests that genuinely exercise the send path opt in explicitly with
`config(['push.enabled' => true])` plus a mocked `Kreait\Firebase\Contract\Messaging`.

---

## 9. Known gotchas carried over from the previous change

- `npx vite build --mode development` — never plain `npm run build` for the app (blank screen).
- `JAVA_HOME` must point at Android Studio's JBR 21 for Gradle.
- `PushNotifications.register()` crashes the **entire Android process** when
  `google-services.json` is missing. The throw happens on a native plugin thread and is
  unreachable from any JS `try/catch`. This is exactly why `PUSH_ENABLED` exists and why it
  is checked *before* the permission prompt, not just before `register()`.
- App timezone is `America/Guayaquil` (UTC-5), pinned by `ConfigTimezoneTest`. Any
  `sent_at` display must respect it.
- The project's git convention: conventional commits, **no** `Co-Authored-By` / AI attribution.

---

## 10. Session log

### 2026-07-29 — session 1 (planning)

- Explored the full existing push chain; confirmed the FCM transport is already complete.
- Discovered `FcmChannel` self-chunks at 500 and returns `MulticastSendReport` — removed the
  planned custom multicast layer from the design.
- Discovered the app has no receive/tap handling (gap 1) and no Firebase config (gap 2).
- Confirmed `Instructor\CourseController::publish()` is the single course-publish path.
- Took decisions D1–D4 with the user.
- Wrote this handoff.
- **Implemented Slice 1** on branch `feat/push-notifications-foundation`:
  `config/push.php`, the two migrations, `PushNotificationLog` + factory, `PushBroadcast`,
  `PushBroadcaster`, `BroadcastResult`, `BroadcastPushNotification`, and 11 tests across
  `tests/Feature/Push/`. Full suite green: **780 passed, 2287 assertions**.
- Changed the design mid-slice from per-trigger Notification subclasses to one generic
  `PushBroadcast` (rationale in §5.2).
- Confirmed Pint is not enforced in CI (see §7) — did not reformat.
- **Implemented Slice 2**: `PushDispatcher` (+ `custom()`), `push_notified_at` casts on
  `Post`/`Course`, hooks in `Admin\PostController::store()`/`::update()` and
  `Instructor\CourseController::publish()`, and 20 tests across `PushDispatcherTest` +
  `PushTriggerEndpointTest`. Full suite green: **800 passed, 2350 assertions**.
- **Found gap 3** (see §3): the app's news links are broken and there is no news route.
  Added Slice 5b to cover it.
- Gotcha hit while writing tests: `postJson(..., ['_method' => 'PATCH'])` returns 405 on
  these admin routes. They are registered as plain `Route::post`, and Laravel honours the
  `_method` spoof. Use plain `postJson` with no `_method`, as
  `tests/Feature/Admin/AdminPostControllerTest.php` does.
- **Implemented Slice 3**: `Admin\PushNotificationController` (index/store/stats),
  `StorePushNotificationRequest`, `PushNotificationLogResource`, routes, and 14 tests in
  `AdminPushNotificationControllerTest`. Full suite green: **814 passed, 2403 assertions**.
- **Backend complete.**

### 2026-07-30 — session 2 (slices 4, 5, 5b)

- **Slice 4**: `frontend/src/stores/notifications.js` + `views/admin/AdminNotifications.vue`,
  route `/admin/notificaciones`, NavBar entry, 16 tests. Had to update
  `NavBarAdmin.test.js`, which asserts the complete admin link set.
  Frontend suite: **910 passed**.
- **Slice 5**: `addNotificationListeners()` in `App/src/services/pushNotifications.js`,
  wired into `stores/push.js` with `_onNotificationReceived` / `_onNotificationTapped`, and
  the exported `extractRoute()` guard. Had to add `addNotificationListeners` and a router
  mock to the two existing push store test files, which mock the service wholesale.
- **Slice 5b**: `App/src/views/News.vue`, `NewsDetail.vue`, `components/news/NewsCard.vue`,
  `fetchPosts()`/`fetchPost()` on the app's posts store, routes `/noticias` and
  `/noticias/:slug`. App suite: **372 passed**; `npx vite build --mode development` clean.
- Design note recorded while building Slice 5: the delivery listeners MUST attach before
  every early return in `init()`. `init()` exits as soon as a device token is already
  persisted — the common case for a returning user — so attaching after that check would
  leave taps dead on the longest-installed devices. There is a regression test for exactly
  this.
- Note on Slice 5b scope: the app's public news API accepts only `search` and `page`, so the
  catalog has no type/sort filter. Adding one requires a backend change first.
- **Next**: Slice 6 only — the manual Firebase setup in §8.

### 2026-07-30 — session 2b (chained PRs)

Strategy chosen by the user: **stacked to `main`** + `size:exception` on the four PRs over 400
lines (the units are cohesive and ~48% of the volume is tests, which stay with the code they
verify).

Discovered while splitting: PRs 5 and 6 have **zero file overlap** with PRs 1–4 or with each
other (verified with `comm` over the touched-file lists), so they target `main` directly and
can be reviewed in parallel instead of being forced into a linear stack.

| PR | Branch | Base | Lines | Status |
|---|---|---|---|---|
| [#62](https://github.com/ramceslp/Ikena_makeup/pull/62) | `feat/push-foundation` | `main` | 906 | CI green |
| [#63](https://github.com/ramceslp/Ikena_makeup/pull/63) | `feat/push-triggers` | #62 | 590 | CI green |
| [#64](https://github.com/ramceslp/Ikena_makeup/pull/64) | `feat/push-admin-api` | #63 | 417 | CI green |
| [#65](https://github.com/ramceslp/Ikena_makeup/pull/65) | `feat/push-admin-ui` | #64 | 733 | CI green |
| [#66](https://github.com/ramceslp/Ikena_makeup/pull/66) | `feat/push-app-deeplink` | `main` | 396 | CI green |
| [#67](https://github.com/ramceslp/Ikena_makeup/pull/67) | `feat/app-news-section` | `main` | 760 | CI green |
| [#68](https://github.com/ramceslp/Ikena_makeup/pull/68) | `chore/gitignore-firebase-secrets` | `main` | 20 | CI green |

**Merge order**: #62 → #63 → #64 → #65 in sequence (GitHub auto-retargets each child to `main`
as its parent merges and its branch is deleted). #66 and #67 can merge any time.

`feat/push-notifications-foundation` is the original working branch holding all six commits.
It has no PR and can be deleted once the chain lands. Note it is now **stale** — the chain was
rebased after it was created (see below).

### 2026-07-30 — session 2c (Slice 6 bring-up, two real bugs found)

Triggered by a question while configuring Firebase. Both findings came from the user's live
environment, not from CI.

1. **`FIREBASE_CREDENTIALS` was pointed at `google-services.json`.** Wrong file entirely, not
   just a wrong path — §8 now leads with a comparison table so this cannot recur.
   Also confirmed empirically that relative paths resolve against Laravel's **basePath**
   (`backend/`), not the shell CWD.
2. **`QUEUE_CONNECTION=sync` made the POST response lie.** `dispatch()` runs the job inline;
   the job updates its own model instance, so the controller serialized a stale row reporting
   `pending` while the database already said `sent`. Proved with a throwaway probe test before
   fixing. Fixed with `$log->refresh()` — a no-op on an async connection — and pinned by
   `test_the_response_reports_the_real_status_when_the_queue_runs_synchronously`.
   Writing that test surfaced a second trap: `Queue::fake()` in `setUp()` intercepts dispatch
   entirely, so the real manager has to be captured and swapped back for the sync case.
3. **The suite inherited `PUSH_ENABLED` from `.env`** — six tests broke the moment push was
   enabled locally. Forced off in `phpunit.xml`.

Chain was rebased onto the updated foundation and force-pushed; all 7 PRs re-verified green
(21 checks). New commits: `6373e82` (phpunit), `21dcb14` (sync refresh).

### 2026-07-30 — session 2d (Slice 6 DONE — verified end-to-end on the emulator)

Ran the whole stack: `php artisan serve --host=0.0.0.0`, AVD `Medium_Phone_API_36.1`,
`gradlew assembleDebug`, `adb install`. Local branch `test/push-integration` merges all six
feature branches for testing (it is a test artifact — do not PR it).

**Evidence, in order:**

1. `FirebaseApp initialization successful` in logcat, `google_app_id` / `project_id` present
   in `app/build/generated/res/processDebugGoogleServices/values/values.xml`.
2. Both delivery listeners attached at boot — `pushNotificationReceived` and
   `pushNotificationActionPerformed` (Slice 5 working).
3. Permission granted → `register()` → `device_tokens` row written:
   `user=5, platform=android`.
4. Real broadcast sent through `PushDispatcher` → **`status=sent, recipients=1, successes=1,
   failures=0`**.
5. Notification landed in the Android tray with the exact title/body
   (`dumpsys notification` confirmed).
6. **Tapped it → app opened `/noticias`.** Tapping a card opened `/noticias/{slug}`.
   The deep link works end to end.

**Gradle needs `ANDROID_HOME` too**, not just `JAVA_HOME`. `local.properties` is gitignored
and absent, so the build fails with "SDK location not found" until
`ANDROID_HOME=%LOCALAPPDATA%\Android\Sdk` is exported (or `sdk.dir` is written to
`local.properties`).

**Bug found and fixed during the run** (`d9960e0`, folded into PR #67): a `cover_image_url`
that is set but fails to LOAD rendered the browser's broken-image glyph plus alt text,
overflowing the thumbnail and colliding with the "Destacada" badge. A seeded loremflickr
placeholder was returning 500 while its siblings returned 200. Both `NewsCard.vue` and
`NewsDetail.vue` now treat a failed load as no image. Re-verified on the emulator.

### 2026-07-30 — session 2e (custom deep links opened a blank screen)

Reported by the user from live use: a custom broadcast sent with a destination
arrived correctly, but tapping it opened a blank page.

**Root cause — three things lining up, none of which reported anything:**

1. **`vue-router` 4 does not reject `push()` to an unmatched path.** It resolves,
   with an empty `matched` array and a console warning nobody reads on a phone.
   The `try/catch` in `stores/push.js::_onNotificationTapped` — written for
   exactly this case, per its own comment — therefore never fired.
2. **The App router had no catch-all.** So `AppShell` rendered its top and bottom
   bars around an empty `<RouterView>`. That is the blank screen: not an error
   state, *nothing*.
3. **`route` was a free-text field validated only by `regex:/^\/(?!\/)/`.** That
   established the string was an internal path, never that it led anywhere.

**The trap that made it likely** — the web panel and the app do NOT share a route
vocabulary, and nothing said so:

| Screen | Web (`frontend/`) | App (`App/`) |
|---|---|---|
| Course detail | `/courses/{slug}` | **`/cursos/{slug}`** |
| News | `/noticias/{slug}` | `/noticias/{slug}` ✅ |
| Products | `/products/{slug}` | `/products/{slug}` ✅ |
| Anything `/admin/*` | exists | **does not exist** |

Copying a course URL out of the admin's own address bar produced a path that
passed validation, was stored, was delivered to every device, was recorded as
`sent` — and opened nothing.

**Fix, in three layers** (each one alone would have left a hole):

| Layer | Change |
|---|---|
| Source | `config/push_destinations.php` — the catalogue of screens the app can open. `Services/Push/AppDestinations` turns it into both picker options and a validator, from ONE reading. |
| Send | The admin composes with a **destination picker + slug**, not a path. `StorePushNotificationRequest` collapses them into the same `route` string the pipeline already stored — nothing downstream changed. `Rules\AppDestination` rejects a path the app has no route for, *and* a slug matching no published record. |
| Receive | App router gains a catch-all → `views/NotFound.vue`. `stores/push.js` gains `isReachableRoute()`, checked **before** navigating — for the notifications sent before this fix that are still sitting in people's trays. |

**Deliberate asymmetry in `AppDestinationsSyncTest`**: it reads the REAL
`App/src/router/index.js` and fails only when the catalogue offers something the
router lacks. The reverse (a route not offered in the picker) costs an option
nobody sees; this direction reaches a user. Same idea as
`androidNotificationChannel.test.js`, across the PHP↔JS boundary this time.

**Two subtleties worth keeping:**

- `isReachableRoute` must reject a path matching *only* the catch-all. Adding the
  404 route makes `matched.length > 0` true for every string on earth, which
  would have handed the guard straight back to the bug.
- On an unreachable link the app **stays put** rather than routing to the 404.
  The user tapped expecting content; the app opens on Home either way, and Home
  is a working app. The 404 view is for links a user followed deliberately.

Suites after the change: backend **828**, app **408**, frontend **923**, all
green; `npx vite build --mode development` clean.

### Follow-ups worth doing (none blocking)

- ~~**`Missing Default Notification Channel metadata in AndroidManifest`** (logcat warning).~~
  **Done.** The manifest now declares
  `com.google.firebase.messaging.default_notification_channel_id` pointing at a
  `translatable="false"` string resource, and the app creates the matching channel
  (`ikena_general` / "Novedades de Ikena", importance 4) at every enabled Android boot.

  Two things are less obvious than they look:

  - The channel is created **before** `init()`'s "not logged in" and "already registered"
    early returns, for the same reason the delivery listeners are. A device that has had the
    app installed for a while exits at `alreadyRegistered` — and that is exactly the device
    that receives pushes. Creating it after either return would leave existing installs with
    no channel forever.
  - The id is necessarily duplicated between the Android string resource and JS, and nothing
    in either toolchain links them. A rename on one side yields a push aimed at a channel that
    does not exist, which Android drops in silence.
    `App/src/tests/androidNotificationChannel.test.js` is that missing link: it reads the real
    Android sources and compares them.

  Verified on an API 36 emulator: warning gone, channel visible by name in system settings,
  and a real broadcast landed on `channel=ikena_general` (not on the fallback).
  Devices that received a push under an earlier build keep the old fallback channel, listed as
  "Miscellaneous" alongside the new one — cosmetic, and only on those installs.
- **Notification icon** is the default Capacitor launcher glyph. A dedicated monochrome
  `ic_stat_*` drawable is the Android convention.
- **The slug field in the compose form is still typed by hand.** The server rejects one that
  matches no published record, so it cannot produce a broken notification — but picking the
  post/course/product from a list would beat remembering its slug. Needs a lookup endpoint per
  content type, which is why it was not done here.
- **History rows written before session 2e may hold unreachable paths.** They are harmless
  (the app now declines to navigate, landing the user on Home) and are left as-is: the history
  is a record of what was actually sent, so rewriting it would be a lie.
- ~~The same broken-image weakness still exists in the home news components.~~
  **Done** — PR #71 (`5e2854d`). The fix was extended past the two App components originally
  noted: the same defect existed on all four `frontend/` news surfaces too, and fixing only
  one client would have left the identical bug on the other. Six surfaces in total; the
  App's `News.vue` / `NewsDetail.vue` were already covered by #67.

  The featured heroes were the worst case: their gradient-mesh fallback is a `v-else` on
  whether the URL *exists*, so a URL that failed to *load* left the section with neither an
  image nor its backdrop. Grids track failure per post id, not with one flag — the emulator
  showed one placeholder returning 500 while its siblings returned 200.

**Commits on `feat/push-notifications-foundation`:**
- `e4a18f9` feat(backend): add push broadcast foundation with send history log
- `2fe21ce` feat(backend): broadcast a push when a news post or a course is published
- `9b01b06` feat(backend): add admin endpoints for custom broadcasts and send history
- `8ad2a08` feat(frontend): add admin notification centre with compose form and history
- `33c4a13` feat(app): open the deep link when a push notification is tapped
- `92d944f` feat(app): add the news section the home screen has always linked to

---

## 11. How to resume

1. Read this file top to bottom.
2. Check the **Slice status** table in §6 for the first slice not marked done.
3. Re-read §5 for that slice's design before writing code.
4. `git status` and `git branch` — confirm whether a slice branch is already in flight.
5. Implement, test per §7, then **update §6 and §10 in this file** before the session ends.
