<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

// Access schedule state/actions from the global schedule composable.
const {
  loadingScheduleCreation,
  errorScheduleCreation,
  createSchedule,
  setSelectedPeriod,
} = useSchedule()

// Access authenticated user state to enforce role-based access in this page too.
const { user, fetchMe } = useAuth()

// Use shared schedule texts composable
const { texts, toggleLanguage, localeLabel, localeFlag } = useScheduleTexts()

// Protect this page at component level: only admin/head nurse can use schedule creation.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

// Schedule period form model.
const form = reactive({
  month: '',
})

const localError = ref('')
const isBootstrapping = ref(true)
const isSubmitting = ref(false)

// Validate all mandatory fields before any API request.
const validateForm = () => {
  localError.value = ''

  if (!form.month) {
    localError.value = texts.value.create.errors.required
    return false
  }

  return true
}

// Submit flow: create the schedule period and continue to the grid editor page.
const handleSubmit = async () => {
  localError.value = ''

  if (!validateForm()) return

  isSubmitting.value = true

  try {
    const [yearRaw, monthRaw] = form.month.split('-')
    const year = Number(yearRaw)
    const month = Number(monthRaw)
    const start_date = `${form.month}-01`
    const lastDay = new Date(year, month, 0).getDate()
    const end_date = `${form.month}-${String(lastDay).padStart(2, '0')}`

    const createdSchedule = await createSchedule(start_date, end_date)

    await navigateTo({
      path: '/schedule-create-grid',
      query: {
        scheduleId: String(createdSchedule.id),
      },
    })
  } catch {
    localError.value =
      errorScheduleCreation.value
      || texts.value.create.errors.createFailed
  } finally {
    isSubmitting.value = false
  }
}

// Keep the composable selected month/year in sync with the chosen month.
watch(
  () => form.month,
  (value) => {
    if (!value) return

    const selectedDate = new Date(`${value}-01`)
    if (Number.isNaN(selectedDate.getTime())) return

    setSelectedPeriod(selectedDate.getMonth() + 1, selectedDate.getFullYear())
  }
)

onMounted(async () => {
  isBootstrapping.value = true
  localError.value = ''

  try {
    // Ensure user profile is available after refresh before role validation.
    if (!user.value) {
      await fetchMe().catch(() => null)
    }

    // Hard guard: even with direct URL access, only admin/head nurse can proceed.
    if (!canCreateSchedule.value) {
      await navigateTo('/dashboard')
      return
    }
  } catch {
    localError.value = texts.value.create.errors.initialData
  } finally {
    isBootstrapping.value = false
  }
})
</script>

<template>
  <main class="dashboard-page schedule-page">
    <section class="dashboard-card schedule-card" style="position: relative;">
      <button class="language-switch" type="button" @click="toggleLanguage">
        <span class="language-switch__flag">{{ localeFlag }}</span>
        <span>{{ localeLabel }}</span>
      </button>

      <p class="eyebrow">{{ texts.create.pageEyebrow }}</p>
      <h1>{{ texts.create.pageTitle }}</h1>

      <button type="button" class="schedule-secondary-button" @click="navigateTo('/dashboard')">
        {{ texts.backButton }}
      </button>

      <p class="schedule-intro">
        {{ texts.create.intro }}
      </p>

      <p v-if="localError" class="form-error">
        {{ localError }}
      </p>

      <form class="login-form" novalidate @submit.prevent="handleSubmit">
        <div class="schedule-period">
          <label class="field">
            <span>{{ texts.create.month }}</span>
            <input v-model="form.month" type="month" name="month">
          </label>
        </div>

        <div class="schedule-actions-row">
          <button
            type="submit"
            class="login-button"
            :disabled="
              isBootstrapping
              || loadingScheduleCreation
              || isSubmitting
            "
          >
            {{ isSubmitting ? texts.create.submitting : texts.create.submit }}
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
