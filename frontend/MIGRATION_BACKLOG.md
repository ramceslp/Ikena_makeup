# Stitch → Vue Migration Backlog

Inventory of components/elements the Stitch designs offer that are **not yet built**
in the Vue frontend. Use this to plan the remaining view migrations.

**Migration molde** (reference pattern, already done on `Home.vue`): atomic UI
(`components/ui/`) + container/presentational. See `views/Home.vue` and
`components/home/*`.

**Hard rules for every migration:**
1. Preserve existing working logic — do not downgrade functionality.
2. Do **not** invent data — if the backend lacks a field, omit the element or
   flag it as backend work. Items needing backend support are marked ⚠️ BACKEND.
3. Reuse real assets/components instead of hotlinking Stitch images.

Legend: ✅ built · 🔨 to build · ⚠️ BACKEND needed

---

## Shared / cross-view atoms & molecules

| Component | Status | Notes |
|---|---|---|
| `ui/BaseButton` | ✅ | primary / outline / solid · md / sm |
| `ui/BaseBadge` | ✅ | blush / accent / secondary / primary · pill |
| `CourseCard` | ✅ | MD3 redesign, data contract preserved |
| `NavBar` | ✅ | restyled MD3 |
| `layout/SiteFooter` | ✅ | footer links are placeholders (no routes yet) |
| `ui/BaseInput` (text/email/password + label + error) | ✅ | `defineModel()` + label/type/placeholder/error/id + `#leading-icon` slot. |
| `ui/BaseModal` (overlay + Escape/backdrop close) | ✅ | `defineModel()` open + `title` + default/`#footer` slots, Teleport. |
| `ui/StarRating` (display + input) | ✅ | Dual-mode atom: display (fractional → full/half/empty via Material Symbols FILL) + editable (v-model, radiogroup buttons, hover). Filled `apricot-glow`, empty `outline-variant`. |
| `ui/ProgressBar` | ✅ | `value` 0–100 + `showLabel`, `role="progressbar"`. |
| `ui/TabGroup` | ✅ | `defineModel()` active key + `tabs[{key,label}]` + named slots `#tab-<key>`. |

---

## 1. Landing + Catálogo → `Home.vue` ✅ DONE

| Component | Status |
|---|---|
| `home/HeroSection` | ✅ |
| `home/CourseFilters` | ✅ |
| `home/CourseCatalog` | ✅ |
| `home/NewsletterCta` | ✅ (local-only; ⚠️ BACKEND for real subscription) |
| Category filter pills | 🔨 ⚠️ BACKEND | Stitch shows "Editorial / Novias / Noche". No `category` field on courses. |
| "Bestseller" / "Certificado" card ribbons | 🔨 ⚠️ BACKEND | No flags in data — omitted for now. |

---

## 2. Detalle del curso y temario → `CourseDetail.vue` ✅ DONE

Migrated to container/presentational. Container keeps all store wiring; sections are presentational.

| Component | Status | Description |
|---|---|---|
| `course/CourseDetailHero` | ✅ | Title + price + description + CTA. Uses `thumbnail` as poster (no trailer field in API). |
| `course/CurriculumAccordion` | ✅ | Collapsible sections → lessons ("Temario"), free-lesson badges. Data: `sections[].lessons[]`. |
| `course/InstructorProfile` | ❌ skipped | API only returns `instructor.{id,name}` — name shown inline. ⚠️ BACKEND for bio/photo. |
| `course/ReviewList` + `ReviewCard` + `ReviewForm` | ✅ | Wired in CourseDetail. ReviewList = avg summary + list; ReviewForm gated on eligibility (auth + enrolled + not instructor + ≥1 completed lesson, mirrors backend 403) OR existing `my_review` (edit/delete). Store refetches reviews+course after submit/delete so aggregates stay truthful. Star summary also in CourseDetailHero + CourseCard (only when `reviews_count > 0`). |
| `course/StickyCtaSidebar` | ✅ | Price + enroll/buy CTA, sticky. Wired to store `enroll`/`checkout`. |

---

## 3. Reproductor de video interactivo → `Player.vue` ✅ DONE

Migrated to container/presentational (two-column: video+tabs / playlist). Reuses `TabGroup`, `ProgressBar`, `utils/video.js`. Lesson locking mirrored in UI (free vs enrolled), backend enforces 403.

| Component | Status | Description |
|---|---|---|
| `player/VideoStage` | ✅ | Embed via `resolveVideo()` (YouTube/Vimeo iframe, native video, fallback). No fake controls overlay (iframes don't expose JS controls without SDK). |
| `player/PlaylistSidebar` | ✅ | Sections → lessons, active highlight, completion toggle, free badge, **locked state** (lock icon + non-clickable for paid lessons when not enrolled). |
| `player/LessonTabs` | ✅ | `TabGroup` with a single "Contenido" tab (lesson description). Materiales/Entrega tabs omitted — no backend data. |
| `player/PracticeSubmission` (Before/After drag & drop) | ✅ | Full feature: `POST /api/lessons/{lesson}/submissions` (multipart, upsert, resets to pending on resubmit). Lessons gain `is_practice` (toggle in LessonEditor); lesson detail exposes `is_practice` + `my_submission`. Frontend: drag&drop upload (FormData + per-request multipart header), status badge + feedback, conditional "Práctica" tab in LessonTabs (only when `is_practice`). |
| Instructor CTA / "Preguntar al Tutor" | ❌ skipped | Instructor name shown in sidebar footer; no messaging endpoint. ⚠️ BACKEND. |
| Lesson completion toggle | ✅ | Store action `toggleComplete` wired through sidebar. |

---

## 4. Mi perfil e historial de compras → `MyCourses.vue` (+ future Profile)

The enrolled-courses-with-progress list AND the full account/profile page
(info / security / purchase history) are DONE. `Profile.vue` at `/profile`.

| Component | Status | Description |
|---|---|---|
| `mycourses/EnrolledCourseRow` (with progress) | ✅ | Thumbnail + title + instructor + `ProgressBar` + "Continuar" → `/learn/:slug`. Real fields: progress_percentage, completed_lessons, total_lessons. |
| `MyCourses.vue` container | ✅ | Loading/error/empty (empty → CTA to catalog). |
| `profile/ProfileSidebarNav` | ✅ | Section nav (Perfil / Seguridad / Historial), `defineModel()` active key, responsive. |
| `profile/PersonalInfoForm` | ✅ | Edit name/email/avatar. `POST /profile` (multipart upload, real file → public disk). Avatar preview. Field-level + server errors. |
| `profile/SecurityForm` (change password) | ✅ | `PUT /profile/password` (current+new+confirm). Hidden for Google-only accounts via `user.has_password`. |
| `profile/PurchaseHistory` + `PurchaseRow` | ✅ | `GET /profile/orders` (all statuses). Course thumbnail/title + `formatCurrency(amount_cents)` + status badge + date. |

---

## 5. Panel del instructor → `InstructorDashboard.vue` (analytics) + `InstructorCourses.vue` (CRUD)

Analytics **slice DONE**. Key finding: an `Order` model (Payphone) already exists,
so revenue/sales/students/courses were **derivable from existing tables** — built
a read-only aggregation endpoint `GET /api/instructor/dashboard` (no new schema).
Ratings + student submissions remain genuinely backend-less (new features).

| Component | Status | Description |
|---|---|---|
| `GET /api/instructor/dashboard` (backend) | ✅ | Aggregates KPIs (revenue, sales, distinct students, courses, published) + 6-month zero-filled `sales_over_time`. Paid-only, scoped to instructor's courses. 9 Feature tests. |
| `instructor/MetricCard` (KPI cards) | ✅ | label/value/icon/hint. |
| `instructor/SalesChart` | ✅ | Hand-rolled dependency-free SVG bar chart (decision: no chart lib for a 6-point series). Divide-by-zero guarded, Spanish month labels, accessible. |
| `InstructorDashboard.vue` container + route | ✅ | `/instructor/dashboard` (requiresInstructor). Loading/error/zero states; cross-links to `/instructor`. `utils/money.js` `formatCurrency`. |
| `instructor/ReviewTasksList` + `TaskReviewModal` | ✅ | Full feature. Backend: `GET /api/instructor/submissions` (paginated, `?status=` filter, scoped to own courses) + `PATCH /api/instructor/submissions/{id}` (approved/needs_work + feedback, ownership-guarded). Frontend: `InstructorSubmissions.vue` (`/instructor/submissions`) with status filter + ReviewTasksList + TaskReviewModal (before/after side-by-side, feedback, aprobar/necesita correcciones). Cross-linked from dashboard. |
| Course ratings / `StarRating` | ✅ | `course_reviews` backend (model + CRUD + aggregates) + full frontend (StarRating atom, ReviewList/Card/Form, store actions, star summaries). Dashboard 5th MetricCard "Valoración media" consumes `average_rating` KPI. |
| Course management (CRUD) | ✅ | Existing: `InstructorCourseForm`, `InstructorCourseEdit`, `LessonEditor`, `SectionEditor`. |

---

## 6. Acceso y registro → `Login.vue` / `Register.vue` ✅ DONE

Restyled to MD3. Decision: kept the **two separate routes** (deep-linkable, logic
untouched) and built a shared `AuthLayout` shell whose toggle tabs are `RouterLink`s
that highlight the active route. All existing logic preserved 1:1 (validate, submit,
Google GSI flow, redirect query, error mapping).

| Component | Status | Description |
|---|---|---|
| `auth/AuthLayout` (tonal background + glass card) | ✅ | Shared shell: `hero.png` bg @ opacity-10 + gradient, header, glass card, toggle tabs, divider, `#oauth` slot, static Terms/Privacy. |
| Toggle tabs (Login ↔ Register) | ✅ | Implemented as active-aware `RouterLink`s in `AuthLayout` (no JS toggle; two routes preserved). |
| `auth/GoogleButton` | ✅ | Presentational atom; wired to existing `loginWithGoogle` GSI flow. Functional (not backend-blocked) — disabled when `VITE_GOOGLE_CLIENT_ID` is unset. |
| `ui/BaseInput` reuse | ✅ | Both views now use it. Extended backward-compatibly: `autocomplete` prop + opt-in `revealable` password eye toggle. |
| "¿Olvidaste tu contraseña?" | ❌ omitted | No reset flow/route in backend. ⚠️ BACKEND if added later. |

---

## 7. Certificado de profesionalización → NEW view `Certificate.vue`

⚠️ Entirely new — no view, no route, no backend support.

| Component | Status | Description |
|---|---|---|
| `certificate/CertificateCanvas` | 🔨 ⚠️ BACKEND | Printable certificate (student name, course, instructor, date, seal, decorative border). |
| `certificate/CertificateControls` | 🔨 | Download / print / share actions. |
| Route + issuance logic | 🔨 ⚠️ BACKEND | When/how a certificate is granted (course 100% complete?). Needs backend rule + endpoint. |

---

## Suggested build order

1. **Shared atoms first**: `BaseInput`, `ProgressBar`, `TabGroup`, `BaseModal`
   (unblock multiple views).
2. **CourseDetail** (`CurriculumAccordion` + `StickyCtaSidebar`) — high value, data exists.
3. **Player** (`PlaylistSidebar` + `VideoStage`) — data exists.
4. **MyCourses / Profile** — split decision + the ⚠️ BACKEND endpoints.
5. **Instructor Dashboard** — largest ⚠️ BACKEND surface.
6. **Certificate** — new feature, define backend rule first.
