<script setup>
import { ref, watch } from 'vue'
import BaseButton from '../ui/BaseButton.vue'
import CourseDeliveryFields from '../course/CourseDeliveryFields.vue'

/**
 * Shared metadata form for the admin course create/edit views.
 *
 * Covers catalog fields only. Sections and lessons are authored in the
 * instructor editor, which admins may open for any course — so there is no
 * content tree here to drift out of sync with it.
 */
const props = defineProps({
  course: { type: Object, default: null },
  instructors: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  validationErrors: { type: Object, default: () => ({}) },
  submitLabel: { type: String, default: 'Guardar' },
})

const emit = defineEmits(['submit', 'cancel'])

const title = ref(props.course?.title ?? '')
const description = ref(props.course?.description ?? '')
const price = ref(props.course?.price ?? '')
const thumbnail = ref(props.course?.thumbnail ?? '')
const instructorId = ref(props.course?.instructor_id ?? '')
const categoryId = ref(props.course?.category_id ?? '')
const offersCertificate = ref(props.course?.offers_certificate ?? false)
const delivery = ref({
  delivery_mode: props.course?.delivery_mode ?? 'on_demand',
  starts_on: props.course?.starts_on ?? null,
  ends_on: props.course?.ends_on ?? null,
  total_hours: props.course?.total_hours == null ? null : Number(props.course.total_hours),
})

// Edit views load the course after mount, so the prop arrives filled later.
watch(
  () => props.course,
  (course) => {
    if (!course) return
    title.value = course.title ?? ''
    description.value = course.description ?? ''
    price.value = course.price ?? ''
    thumbnail.value = course.thumbnail ?? ''
    instructorId.value = course.instructor_id ?? ''
    categoryId.value = course.category_id ?? ''
    offersCertificate.value = course.offers_certificate ?? false
    delivery.value = {
      delivery_mode: course.delivery_mode ?? 'on_demand',
      starts_on: course.starts_on ?? null,
      ends_on: course.ends_on ?? null,
      total_hours: course.total_hours == null ? null : Number(course.total_hours),
    }
  },
)

function fieldError(field) {
  const messages = props.validationErrors?.[field]
  return Array.isArray(messages) ? messages[0] : messages
}

function handleSubmit() {
  emit('submit', {
    title: title.value,
    description: description.value,
    price: price.value === '' ? 0 : Number(price.value),
    // The API validates `thumbnail` as a URL, so an empty input must be sent
    // as null rather than "" or the request fails validation on a blank field.
    thumbnail: thumbnail.value === '' ? null : thumbnail.value,
    instructor_id: instructorId.value === '' ? null : Number(instructorId.value),
    category_id: categoryId.value === '' ? null : Number(categoryId.value),
    offers_certificate: offersCertificate.value,
    ...delivery.value,
  })
}

const inputClass =
  'w-full rounded-xl border border-blush-canvas/40 px-4 py-2.5 font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40'
const labelClass = 'block font-label-md text-label-md text-on-surface-variant mb-1.5'
const errorClass = 'mt-1 font-body-sm text-body-sm text-error'
</script>

<template>
  <form class="space-y-6" @submit.prevent="handleSubmit">
    <!-- Title -->
    <div>
      <label for="title" :class="labelClass">Título</label>
      <input id="title" v-model="title" name="title" type="text" required :class="inputClass" />
      <p v-if="fieldError('title')" data-error-title :class="errorClass">
        {{ fieldError('title') }}
      </p>
    </div>

    <!-- Instructor -->
    <div>
      <label for="instructor_id" :class="labelClass">Instructor a cargo</label>
      <select
        id="instructor_id"
        v-model="instructorId"
        name="instructor_id"
        required
        :class="inputClass"
      >
        <option value="">Seleccionar instructor</option>
        <option v-for="i in instructors" :key="i.id" :value="i.id">{{ i.name }}</option>
      </select>
      <p v-if="fieldError('instructor_id')" data-error-instructor :class="errorClass">
        {{ fieldError('instructor_id') }}
      </p>
    </div>

    <!-- Price + Category -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="price" :class="labelClass">Precio</label>
        <input
          id="price"
          v-model="price"
          name="price"
          type="number"
          step="0.01"
          min="0"
          :class="inputClass"
        />
        <p v-if="fieldError('price')" :class="errorClass">{{ fieldError('price') }}</p>
      </div>
      <div>
        <label for="category_id" :class="labelClass">Categoría</label>
        <select id="category_id" v-model="categoryId" name="category_id" :class="inputClass">
          <option value="">Sin categoría</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>
    </div>

    <!-- Thumbnail -->
    <div>
      <label for="thumbnail" :class="labelClass">Portada (URL)</label>
      <input
        id="thumbnail"
        v-model="thumbnail"
        name="thumbnail"
        type="url"
        placeholder="https://..."
        :class="inputClass"
      />
      <p v-if="fieldError('thumbnail')" :class="errorClass">{{ fieldError('thumbnail') }}</p>
    </div>

    <!-- Description -->
    <div>
      <label for="description" :class="labelClass">Descripción</label>
      <textarea
        id="description"
        v-model="description"
        name="description"
        rows="5"
        required
        :class="inputClass"
      />
      <p v-if="fieldError('description')" data-error-description :class="errorClass">
        {{ fieldError('description') }}
      </p>
    </div>

    <!-- Delivery mode + calendar -->
    <CourseDeliveryFields
      v-model="delivery"
      :validation-errors="validationErrors"
      :input-class="inputClass"
      :label-class="labelClass"
      :error-class="errorClass"
    />

    <!-- Certificate -->
    <label for="offers_certificate" class="flex items-center gap-3 cursor-pointer">
      <input
        id="offers_certificate"
        v-model="offersCertificate"
        name="offers_certificate"
        type="checkbox"
        class="w-4 h-4 rounded accent-primary"
      />
      <span class="font-body-md text-body-md text-on-surface">Este curso emite certificado</span>
    </label>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3 pt-2">
      <BaseButton type="button" variant="outline" @click="emit('cancel')">Cancelar</BaseButton>
      <BaseButton type="submit" variant="primary" :disabled="loading">
        {{ loading ? 'Guardando...' : submitLabel }}
      </BaseButton>
    </div>
  </form>
</template>
