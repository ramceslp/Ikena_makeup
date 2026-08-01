<script setup>
import { computed } from 'vue'

/**
 * Delivery mode and calendar for a course, shared by the three places that
 * author course metadata: the instructor create form, the instructor edit
 * form, and the admin form.
 *
 * The two authoring surfaces use different visual languages (brand-* vs the
 * on-surface tokens), so the classes are props rather than baked in — the
 * alternative was three copies of a conditional date form, which is exactly
 * how the "live course with no dates" bug gets reintroduced in one of them.
 */
const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
    // { delivery_mode, starts_on, ends_on, total_hours }
  },
  validationErrors: { type: Object, default: () => ({}) },
  inputClass: {
    type: String,
    default:
      'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-accent',
  },
  labelClass: { type: String, default: 'block text-sm font-medium text-gray-700 mb-1' },
  errorClass: { type: String, default: 'text-red-600 text-xs mt-1' },
})

const emit = defineEmits(['update:modelValue'])

const isLive = computed(() => props.modelValue.delivery_mode === 'live')

function update(field, value) {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function setMode(mode) {
  // Clearing the calendar on the way back to on-demand keeps the payload
  // honest: the API rejects these fields outright for an on-demand course.
  if (mode === 'on_demand') {
    emit('update:modelValue', {
      delivery_mode: 'on_demand',
      starts_on: null,
      ends_on: null,
      total_hours: props.modelValue.total_hours,
    })
    return
  }

  update('delivery_mode', mode)
}

function fieldError(field) {
  const messages = props.validationErrors?.[field]
  return Array.isArray(messages) ? messages[0] : messages
}
</script>

<template>
  <div class="space-y-4">
    <!-- Mode -->
    <fieldset>
      <legend :class="labelClass">Modalidad del curso</legend>

      <div class="grid gap-2 sm:grid-cols-2">
        <label
          class="flex items-start gap-2 border rounded-lg px-3 py-2.5 cursor-pointer transition-colors"
          :class="!isLive ? 'border-brand-accent bg-brand-accent/5' : 'border-gray-300'"
        >
          <input
            type="radio"
            name="delivery_mode"
            value="on_demand"
            :checked="!isLive"
            class="mt-0.5 accent-brand-accent"
            @change="setMode('on_demand')"
          />
          <span>
            <span class="block text-sm font-medium text-gray-800">Lecciones en video</span>
            <span class="block text-xs text-gray-500">El alumno avanza a su propio ritmo.</span>
          </span>
        </label>

        <label
          class="flex items-start gap-2 border rounded-lg px-3 py-2.5 cursor-pointer transition-colors"
          :class="isLive ? 'border-brand-accent bg-brand-accent/5' : 'border-gray-300'"
        >
          <input
            type="radio"
            name="delivery_mode"
            value="live"
            :checked="isLive"
            class="mt-0.5 accent-brand-accent"
            @change="setMode('live')"
          />
          <span>
            <span class="block text-sm font-medium text-gray-800">Transmitido en vivo</span>
            <span class="block text-xs text-gray-500">Sesiones agendadas por Meet o Zoom.</span>
          </span>
        </label>
      </div>

      <p v-if="fieldError('delivery_mode')" data-error-delivery-mode :class="errorClass">
        {{ fieldError('delivery_mode') }}
      </p>
    </fieldset>

    <!-- Calendar — live only -->
    <div v-if="isLive" class="grid gap-4 sm:grid-cols-2">
      <div>
        <label for="course-starts-on" :class="labelClass">
          Fecha de inicio <span class="text-red-500">*</span>
        </label>
        <input
          id="course-starts-on"
          name="starts_on"
          type="date"
          :value="modelValue.starts_on ?? ''"
          :class="inputClass"
          @input="update('starts_on', $event.target.value || null)"
        />
        <p v-if="fieldError('starts_on')" data-error-starts-on :class="errorClass">
          {{ fieldError('starts_on') }}
        </p>
      </div>

      <div>
        <label for="course-ends-on" :class="labelClass">
          Fecha de finalización <span class="text-red-500">*</span>
        </label>
        <input
          id="course-ends-on"
          name="ends_on"
          type="date"
          :value="modelValue.ends_on ?? ''"
          :class="inputClass"
          @input="update('ends_on', $event.target.value || null)"
        />
        <p v-if="fieldError('ends_on')" data-error-ends-on :class="errorClass">
          {{ fieldError('ends_on') }}
        </p>
      </div>
    </div>

    <!-- Advertised workload. Offered for both modes: an on-demand course
         still wants to advertise "20 horas de contenido". -->
    <div>
      <label for="course-total-hours" :class="labelClass">Horas totales (opcional)</label>
      <input
        id="course-total-hours"
        name="total_hours"
        type="number"
        min="0"
        step="0.5"
        placeholder="Ej: 20"
        :value="modelValue.total_hours ?? ''"
        :class="inputClass"
        @input="update('total_hours', $event.target.value === '' ? null : Number($event.target.value))"
      />
      <p v-if="fieldError('total_hours')" data-error-total-hours :class="errorClass">
        {{ fieldError('total_hours') }}
      </p>
    </div>
  </div>
</template>
