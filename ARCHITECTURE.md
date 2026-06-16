# Ikena LMS — Architecture & API Contract (Single Source of Truth)

> Decoupled platform: **Laravel API backend** + **Vue 3 SPA frontend**, token-based
> auth (Sanctum personal access tokens) so the same API can later serve mobile apps.
> All backend responses are JSON. Frontend NEVER mocks data — it always hits the real API.

## 1. Tech Stack

| Layer    | Choice                                                                 |
|----------|-----------------------------------------------------------------------|
| Backend  | PHP 8.4 · Laravel 13 · Sanctum (Bearer tokens) · Socialite (Google)   |
| Database | MySQL 8.0 (MariaDB-compatible, driver `mysql`)                         |
| Frontend | Vue 3 (Composition API) · Vite · Pinia · Vue Router · Axios · Tailwind v4 |

## 2. Database Schema

### users
| column            | type                         | notes                                  |
|-------------------|------------------------------|----------------------------------------|
| id                | bigint PK                    |                                        |
| name              | string                       |                                        |
| email             | string unique                |                                        |
| email_verified_at | timestamp nullable           |                                        |
| password          | string **nullable**          | nullable → OAuth-only users have none  |
| google_id         | string nullable unique index | for Google OAuth linking               |
| avatar            | string nullable              | Google profile picture URL             |
| role              | string default 'student'     | 'student' \| 'instructor'              |
| remember_token, timestamps |                     |                                        |

### courses
| column        | type                  | notes                              |
|---------------|-----------------------|------------------------------------|
| id            | bigint PK             |                                    |
| instructor_id | FK users->id cascade  | belongsTo instructor               |
| title         | string                |                                    |
| slug          | string unique         | derived from title                 |
| description   | text                  |                                    |
| price         | decimal(8,2) default 0|                                    |
| thumbnail     | string nullable       | image URL                          |
| is_published  | boolean default true  | catalog only shows published       |
| timestamps    |                       |                                    |

### sections
| column     | type                    | notes              |
|------------|-------------------------|--------------------|
| id         | bigint PK               |                    |
| course_id  | FK courses->id cascade  | belongsTo course   |
| title      | string                  |                    |
| position   | unsignedInteger default 0 | ordering         |
| timestamps |                         |                    |

### lessons
| column      | type                     | notes                          |
|-------------|--------------------------|--------------------------------|
| id          | bigint PK                |                                |
| section_id  | FK sections->id cascade  | belongsTo section              |
| title       | string                   |                                |
| description | text nullable            |                                |
| video_url   | string nullable          | HTML5/Vimeo URL                |
| duration    | unsignedInteger nullable | seconds                        |
| position    | unsignedInteger default 0| ordering                       |
| is_free     | boolean default false    | preview lesson, open to all    |
| timestamps  |                          |                                |

### enrollments  (pivot users <-> courses, "purchase/access")
| column      | type                    | notes                               |
|-------------|-------------------------|-------------------------------------|
| id          | bigint PK               |                                     |
| user_id     | FK users->id cascade    |                                     |
| course_id   | FK courses->id cascade  |                                     |
| price_paid  | decimal(8,2) default 0  | snapshot of price at enroll time    |
| **unique(user_id, course_id)** | timestamps |                                  |

### lesson_progress  (pivot users <-> lessons, completion)
| column       | type                    | notes                              |
|--------------|-------------------------|------------------------------------|
| id           | bigint PK               |                                    |
| user_id      | FK users->id cascade    |                                    |
| lesson_id    | FK lessons->id cascade  |                                    |
| completed_at | timestamp               | presence of row = completed        |
| **unique(user_id, lesson_id)** | timestamps |                                 |

## 3. Eloquent Relationships

- **User**: `coursesTeaching` hasMany(Course, instructor_id) · `enrollments` hasMany ·
  `enrolledCourses` belongsToMany(Course, enrollments) withTimestamps ·
  `completedLessons` belongsToMany(Lesson, lesson_progress) withPivot('completed_at')
- **Course**: `instructor` belongsTo(User) · `sections` hasMany (orderBy position) ·
  `lessons` hasManyThrough(Lesson, Section) · `enrollments` hasMany ·
  `students` belongsToMany(User, enrollments)
- **Section**: `course` belongsTo · `lessons` hasMany (orderBy position)
- **Lesson**: `section` belongsTo · `course` hasOneThrough(Course, Section) accessor ok ·
  `completedByUsers` belongsToMany(User, lesson_progress)
- **Enrollment**: `user` belongsTo · `course` belongsTo

Helper on Course: `lessons_count` (withCount via sections) for catalog & progress math.

## 4. Auth Model

- Token-based with Sanctum **personal access tokens** (NOT cookie/SPA mode), so mobile
  clients work identically. User model uses `HasApiTokens`.
- On register/login/google → return `{ user, token }`. Client stores token, sends
  `Authorization: Bearer <token>` on every protected request.
- `password` is nullable; Google-only users authenticate exclusively via OAuth.

## 5. API Endpoints

Base path `/api`. Standard error shapes:
- 422 validation → `{ "message": "...", "errors": { "field": ["..."] } }`
- 401 → `{ "message": "Unauthenticated." }`
- 403 → `{ "message": "You do not have access to this course." }`
- 404 → `{ "message": "Resource not found." }`

### Public
| Method | Path                | Body / Query                              | 2xx Response |
|--------|---------------------|-------------------------------------------|--------------|
| POST   | /api/register       | name, email, password, password_confirmation | 201 `{user, token}` |
| POST   | /api/login          | email, password                           | 200 `{user, token}` |
| POST   | /api/auth/google    | `{ id_token }` (Google ID token from JS SDK) | 200 `{user, token}` |
| GET    | /api/courses        | `?search=&min_price=&max_price=&sort=newest\|price_asc\|price_desc&page=` | 200 paginated `{data:[CourseCard], links, meta}` |
| GET    | /api/courses/{slug} | —                                         | 200 `{data: CourseDetail}` |

### Protected (`auth:sanctum`)
| Method | Path                          | Notes | 2xx Response |
|--------|-------------------------------|-------|--------------|
| GET    | /api/me                       | current user | 200 `{data: User}` |
| POST   | /api/logout                   | revoke current token | 204 |
| GET    | /api/my-courses               | enrolled + progress | 200 `{data:[MyCourse]}` |
| POST   | /api/courses/{slug}/enroll    | MVP "purchase" (idempotent) | 201 `{data: MyCourse}` |
| GET    | /api/lessons/{id}             | video only if is_free OR enrolled, else 403 | 200 `{data: Lesson}` |
| POST   | /api/lessons/{id}/complete    | TOGGLE completion (must be enrolled) | 200 `{data:{lesson_id, completed:bool, progress:{completed_lessons,total_lessons,percentage}}}` |

## 6. JSON Resource Shapes

**User**: `{ id, name, email, avatar, role }`

**CourseCard** (catalog list):
```json
{ "id":1, "title":"...", "slug":"...", "description":"...", "price":"49.90",
  "thumbnail":"...", "instructor":{"id":2,"name":"..."},
  "lessons_count":12, "sections_count":3, "is_enrolled":false }
```
`is_enrolled` present only when request is authenticated; false otherwise.

**CourseDetail**:
```json
{ "id":1, "title":"...", "slug":"...", "description":"...", "price":"49.90",
  "thumbnail":"...", "instructor":{"id":2,"name":"..."},
  "total_lessons":12, "is_enrolled":false,
  "sections":[
    { "id":1,"title":"...","position":0,
      "lessons":[
        {"id":10,"title":"...","position":0,"is_free":true,"duration":300,"completed":false}
      ]}
  ]}
```
In CourseDetail, lesson objects DO NOT include `video_url` (catalog is public).
`completed` present only when authenticated & enrolled.

**Lesson** (GET /api/lessons/{id} — full):
```json
{ "id":10,"section_id":1,"title":"...","description":"...",
  "video_url":"https://...","duration":300,"position":0,"is_free":true,"completed":false }
```
`video_url` is null/withheld when the user lacks access (only happens for free-lesson
peek without enrollment — non-free lessons return 403 before reaching here).

**MyCourse**:
```json
{ "id":1,"title":"...","slug":"...","thumbnail":"...","instructor":{"id":2,"name":"..."},
  "total_lessons":12,"completed_lessons":5,"progress_percentage":42 }
```
`progress_percentage = round(completed_lessons / total_lessons * 100)`; 0 when no lessons.

## 7. Authorization Rules (critical)

- GET /api/lessons/{id}: allow if `lesson.is_free` OR user enrolled in the lesson's course.
  Otherwise 403. When allowed via enrollment, return full `video_url`.
- POST /api/lessons/{id}/complete: require enrollment in the lesson's course (403 if not).
- Catalog endpoints are fully public; never leak `video_url` of paid lessons there.

## 8. CORS & Config

- `config/cors.php`: allow frontend origin `http://localhost:5173`, methods *, headers *.
- `config/services.php`: add `google` with client_id/secret/redirect from env.
- Sanctum: token-based; `EnsureFrontendRequestsAreStateful` NOT required (pure token).

## 9. Frontend Contract

- `src/services/api.js`: single Axios instance, baseURL `import.meta.env.VITE_API_URL`
  (default `http://localhost:8000/api`). Request interceptor injects
  `Authorization: Bearer <token>` from auth store. Response interceptor: on 401 → clear
  auth + redirect to /login.
- Pinia `auth` store: `{ user, token }`, persisted to localStorage; actions
  `register, login, loginWithGoogle, fetchMe, logout`.
- Pinia `courses` store: catalog list + filters, course detail, my-courses.
- Router guards: routes requiring auth redirect to /login when no token.
- Views: Login, Register, Home (catalog + filters), CourseDetail (public, with enroll
  CTA), MyCourses (cards with progress %), Player (2-column: video + curriculum sidebar
  with live-toggle completion checkboxes).
- Google button: uses Google Identity Services, sends `id_token` to POST /api/auth/google.
- Components follow atomic-ish structure; handle loading & error states; validate forms.

## 10. Seed Data (for immediate testing)

DatabaseSeeder creates: 1 instructor, 1 student (student@ikena.test / password),
2–3 courses each with 2–3 sections and several lessons (some `is_free`), and enroll the
student into one course with a couple of lessons already completed — so MyCourses and the
progress bar show real numbers on first run.

The seeded instructor MUST have known credentials for testing the authoring UI:
`instructor@ikena.test` / `password`, `role = 'instructor'`, owning the seeded courses.

## 11. Instructor Authoring (course/section/lesson CRUD)

> Goal: an instructor manages ONLY their own courses. Videos are NOT stored on the
> server — only a hosted `video_url` (YouTube, Vimeo, or a direct `.mp4`) is saved.

### 11.1 Access control
- All instructor endpoints sit under `auth:sanctum` + a `role:instructor` gate
  (middleware alias `instructor` → `EnsureUserIsInstructor`; 403 `{ "message": "Instructor role required." }` otherwise).
- Ownership: every course/section/lesson action is authorized via `CoursePolicy`
  against the parent course's `instructor_id`. Acting on a course you don't own → 403
  `{ "message": "You do not own this course." }`. Sections/lessons authorize through their course.

### 11.2 `video_url` rule (shared contract)
A reusable validation rule `VideoUrl` accepts, case-insensitive, exactly these forms:
- YouTube: `youtube.com/watch?v=<id>`, `youtu.be/<id>`, `youtube.com/embed/<id>`
- Vimeo: `vimeo.com/<digits>`, `player.vimeo.com/video/<digits>`
- Direct MP4: any `http(s)` URL whose path ends in `.mp4`

Stored RAW (never rewritten). The frontend resolves provider at render time
(`src/utils/video.js` → `{ type: 'youtube'|'vimeo'|'mp4', embedUrl|src }`); the same
helper powers the lesson form's live preview and the Player. `video_url` is required only
when the lesson is meant to be playable; it MAY be null on a draft lesson.

### 11.3 Endpoints (base `/api/instructor`, all protected + role:instructor)
| Method | Path | Body | 2xx |
|--------|------|------|-----|
| GET    | `/instructor/courses` | — | 200 `{data:[InstructorCourseCard]}` (own courses, published+draft) |
| POST   | `/instructor/courses` | `title, description, price, thumbnail?` | 201 `{data: InstructorCourseDetail}` (created as draft, `is_published=false`) |
| GET    | `/instructor/courses/{slug}` | — | 200 `{data: InstructorCourseDetail}` (sections+lessons WITH `video_url`) |
| PATCH  | `/instructor/courses/{slug}` | any of `title, description, price, thumbnail` | 200 `{data: InstructorCourseDetail}` |
| DELETE | `/instructor/courses/{slug}` | — | 204 (cascade sections+lessons) |
| POST   | `/instructor/courses/{slug}/publish` | — | 200 `{data: InstructorCourseDetail}` — sets `is_published=true`; **422 if course has 0 lessons** |
| POST   | `/instructor/courses/{slug}/unpublish` | — | 200 `{data: InstructorCourseDetail}` |
| POST   | `/instructor/courses/{slug}/sections` | `title` | 201 `{data: Section}` (position auto = max+1) |
| PATCH  | `/instructor/sections/{id}` | `title?` | 200 `{data: Section}` |
| DELETE | `/instructor/sections/{id}` | — | 204 (cascade lessons) |
| PATCH  | `/instructor/courses/{slug}/sections/reorder` | `{ ordered_ids: [int] }` | 200 `{data:[Section]}` (must be exactly this course's section ids) |
| POST   | `/instructor/sections/{id}/lessons` | `title, video_url?, description?, duration?, is_free?` | 201 `{data: InstructorLesson}` (position auto = max+1) |
| PATCH  | `/instructor/lessons/{id}` | any of `title, video_url, description, duration, is_free` | 200 `{data: InstructorLesson}` |
| DELETE | `/instructor/lessons/{id}` | — | 204 |
| PATCH  | `/instructor/sections/{id}/lessons/reorder` | `{ ordered_ids: [int] }` | 200 `{data:[InstructorLesson]}` (must be exactly this section's lesson ids) |

Validation: `title` required string max 255; `price` numeric min 0; `thumbnail` nullable url;
`duration` nullable integer min 0; `is_free` boolean; `video_url` nullable + `VideoUrl` rule;
`reorder.ordered_ids` required array of ints that exactly match the existing child ids
(422 otherwise). Slugs auto-derive from title on create; on title change the slug is
regenerated and kept unique.

### 11.4 Resource shapes
**InstructorCourseCard** (list): `{ id, title, slug, price, thumbnail, is_published, sections_count, lessons_count, students_count, created_at }`

**InstructorCourseDetail**: like public CourseDetail BUT lessons include the full
`video_url` (owner sees everything) and the course includes `is_published`:
```json
{ "id":1,"title":"...","slug":"...","description":"...","price":"49.90","thumbnail":"...",
  "is_published":false,"total_lessons":3,
  "sections":[ { "id":1,"title":"...","position":0,
    "lessons":[ {"id":10,"title":"...","description":"...","video_url":"https://...",
                 "duration":300,"position":0,"is_free":true} ] } ] }
```
**InstructorLesson**: `{ id, section_id, title, description, video_url, duration, position, is_free }`

### 11.5 Frontend
- Pinia `instructor` store: actions for every endpoint above; keeps the edited course tree
  in state and updates optimistically where safe (reorder, publish toggle) with rollback.
- Router (role-guarded — `auth.user.role === 'instructor'`, else redirect Home):
  `/instructor` (InstructorCourses list + publish/delete), `/instructor/courses/new`
  (InstructorCourseForm), `/instructor/courses/:slug/edit` (InstructorCourseEdit — course
  fields + nested SectionEditor/LessonEditor with `VideoUrlInput` live preview, drag-or-button reorder).
- NavBar shows a "Panel instructor" link only when `role === 'instructor'`.
- Player (`Player.vue`) and the lesson form share `src/utils/video.js` so YouTube/Vimeo
  render as `<iframe>` and `.mp4` as `<video>`.
