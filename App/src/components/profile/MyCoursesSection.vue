<script setup>
import { useMyCoursesStore } from '../../stores/myCourses.js'

// "Mis cursos" — the enrolled-courses section of the profile screen.
//
// Follows the loading -> error -> empty -> list order PurchaseHistory.vue and
// ProductCatalog.vue already established, so every API-backed surface in this
// app distinguishes "something failed" from "there is nothing here".
//
// Tapping a course opens the WEB lesson player in the system browser: this app
// has no player of its own, and building one is a separate project. The link
// is served by the API (MyCourseResource's web_url) rather than assembled
// here — see stores/myCourses.js's openCourse().
const store = useMyCoursesStore()
</script>

<template>
  <div>
    <!-- Loading skeleton -->
    <div v-if="store.loading" class="space-y-3">
      <div
        v-for="i in 2"
        :key="i"
        data-my-courses-skeleton
        class="bg-surface-muted rounded-2xl border border-blush-canvas/30 p-4 flex items-center gap-4 animate-pulse"
      >
        <div class="w-20 h-14 bg-surface-container rounded-lg flex-shrink-0" />
        <div class="flex-1 space-y-2">
          <div class="h-4 bg-surface-container rounded w-3/4" />
          <div class="h-3 bg-surface-container rounded w-1/2" />
        </div>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="store.error" data-my-courses-error class="text-center state-y">
      <span class="material-symbols-outlined text-error text-5xl mb-4" aria-hidden="true">error</span>
      <p class="font-body-lg text-body-lg text-on-surface">{{ store.error }}</p>
      <button
        type="button"
        data-my-courses-retry
        class="mt-4 text-primary hover:underline font-label-md text-label-md min-h-11 px-4"
        @click="store.fetchMyCourses()"
      >
        Intentar de nuevo
      </button>
    </div>

    <!-- Empty -->
    <div v-else-if="!store.courses.length" data-my-courses-empty class="text-center state-y">
      <span class="material-symbols-outlined text-blush-canvas text-5xl mb-3" aria-hidden="true">school</span>
      <p class="font-body-lg text-body-lg text-on-surface mb-4">Todavía no estás inscrito en ningún curso.</p>
      <RouterLink
        to="/cursos"
        data-browse-courses
        class="inline-flex items-center min-h-11 px-6 py-3 rounded-xl bg-apricot-glow text-deep-marsala font-label-md text-label-md"
      >
        Explorar cursos
      </RouterLink>
    </div>

    <!-- List -->
    <div v-else data-my-courses-list class="space-y-3">
      <!-- openError belongs to the whole section, not one row: only one course
           can be opened at a time, and the failure is about the browser or a
           malformed link, not about which card was tapped. -->
      <p
        v-if="store.openError"
        data-my-courses-open-error
        class="bg-error-container rounded-xl px-4 py-3 font-body-md text-body-md text-on-error-container"
        role="alert"
      >
        {{ store.openError }}
      </p>

      <button
        v-for="course in store.courses"
        :key="course.id"
        type="button"
        data-my-course-row
        class="w-full text-left flex items-center gap-4 bg-surface rounded-2xl border border-blush-canvas/30 shadow-sm shadow-primary/5 p-4 transition-colors hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
        @click="store.openCourse(course)"
      >
        <div class="w-20 h-14 rounded-lg overflow-hidden bg-surface-container flex-shrink-0 flex items-center justify-center">
          <img
            v-if="course.thumbnail"
            :src="course.thumbnail"
            :alt="course.title"
            loading="lazy"
            class="w-full h-full object-cover"
          />
          <span v-else class="material-symbols-outlined text-outline text-2xl" aria-hidden="true">school</span>
        </div>

        <div class="flex-1 min-w-0">
          <!-- line-clamp-2, not truncate: on a phone this row is narrow enough
               that `truncate` cut a 26-character title ("Fundamentos del
               Maquillaje") in half. Two lines matches ProductCard.vue's own
               title treatment. -->
          <p class="font-title-sm text-title-sm text-on-surface line-clamp-2">{{ course.title }}</p>
          <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5 truncate">
            {{ course.instructor?.name }}
          </p>

          <!-- Progress. The bar is decorative; the adjacent text carries the
               same information for anyone who cannot see it (Skill:
               color-not-only). -->
          <div class="mt-2 flex items-center gap-2">
            <div class="flex-1 h-1.5 rounded-full bg-surface-container overflow-hidden" aria-hidden="true">
              <div
                data-progress-bar
                class="h-full bg-primary rounded-full"
                :style="{ width: `${course.progress_percentage ?? 0}%` }"
              />
            </div>
            <span class="font-label-sm text-label-sm text-on-surface-variant tabular-nums whitespace-nowrap">
              {{ course.completed_lessons ?? 0 }}/{{ course.total_lessons ?? 0 }} · {{ course.progress_percentage ?? 0 }}%
            </span>
          </div>
        </div>

        <span class="material-symbols-outlined text-outline text-[20px] flex-shrink-0" aria-hidden="true">
          open_in_new
        </span>
      </button>

      <p class="font-body-sm text-body-sm text-on-surface-variant flex items-start gap-1.5 pt-1">
        <span class="material-symbols-outlined text-[16px] mt-0.5" aria-hidden="true">info</span>
        Las lecciones se abren en tu navegador. Puede que debas iniciar sesión allí la primera vez.
      </p>
    </div>
  </div>
</template>
