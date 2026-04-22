<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

// Access schedule state/actions from the global schedule composable.
const {
  schedule,
  schedules,
  loadingSchedules,
  errorSchedules,
  loadingScheduleCreation,
  errorScheduleCreation,
  fetchSchedules,
  fetchSchedule,
  createSchedule,
  setSelectedPeriod,
} = useSchedule()

// Access authenticated user state to enforce role-based access in this page too.
const { user, fetchMe } = useAuth()

// Use shared schedule texts composable
const { currentLocale, texts, toggleLanguage, localeLabel, localeFlag } = useScheduleTexts()

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
const continuingDraftId = ref<number | null>(null)
const monthInputRef = ref<HTMLInputElement | null>(null)

const currentMonthMin = computed(() => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
})

const getScheduleMonthKey = (dateRaw: string) => dateRaw.slice(0, 7)

const draftSchedules = computed(() => {
  return schedules.value
    .filter((item) => {
      if (item.status !== 'draft') return false

      const scheduleMonth = getScheduleMonthKey(item.start_date)
      return scheduleMonth >= currentMonthMin.value
    })
    .sort((a, b) => {
      const monthA = getScheduleMonthKey(a.start_date)
      const monthB = getScheduleMonthKey(b.start_date)

      if (monthA === monthB) {
        return a.id - b.id
      }

      return monthA.localeCompare(monthB)
    })
})

const formatSchedulePeriod = (startDateRaw: string, endDateRaw: string) => {
  const startDate = new Date(startDateRaw)
  const endDate = new Date(endDateRaw)

  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
    return `${startDateRaw.slice(0, 10)} - ${endDateRaw.slice(0, 10)}`
  }

  if (currentLocale.value === 'pt') {
    const formatPtDate = (date: Date) => {
      const day = new Intl.DateTimeFormat('pt-PT', { day: '2-digit' }).format(date)
      const monthRaw = new Intl.DateTimeFormat('pt-PT', { month: 'short' }).format(date)
      const monthCapitalized = monthRaw.charAt(0).toUpperCase() + monthRaw.slice(1)
      const month = monthCapitalized.endsWith('.')
        ? `${monthCapitalized.slice(0, -1)},`
        : `${monthCapitalized},`
      const year = new Intl.DateTimeFormat('pt-PT', { year: 'numeric' }).format(date)
      return `${day} ${month} ${year}`
    }

    return `${formatPtDate(startDate)} - ${formatPtDate(endDate)}`
  }

  const formatter = new Intl.DateTimeFormat('en-US', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })

  return `${formatter.format(startDate)} - ${formatter.format(endDate)}`
}

const formatScheduleMonth = (startDateRaw: string) => {
  const startDate = new Date(startDateRaw)

  if (Number.isNaN(startDate.getTime())) {
    return startDateRaw.slice(0, 7)
  }

  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  const monthLabel = new Intl.DateTimeFormat(locale, {
    month: 'long',
    year: 'numeric',
  }).format(startDate)

  return monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1)
}

const openMonthPicker = () => {
  const monthInput = monthInputRef.value as (HTMLInputElement & { showPicker?: () => void }) | null
  if (!monthInput) return

  if (typeof monthInput.showPicker === 'function') {
    monthInput.showPicker()
    return
  }

  monthInput.focus()
}

const continueDraftSchedule = async (scheduleId: number) => {
  localError.value = ''
  continuingDraftId.value = scheduleId

  try {
    const currentSchedule = await fetchSchedule(scheduleId)

    const startDate = new Date(currentSchedule.start_date)
    if (!Number.isNaN(startDate.getTime())) {
      setSelectedPeriod(startDate.getMonth() + 1, startDate.getFullYear())
    }

    await navigateTo({
      path: '/schedule-create-grid',
      query: {
        scheduleId: String(currentSchedule.id),
      },
    })
  } catch {
    localError.value = errorScheduleCreation.value || texts.value.create.errors.loadDrafts
  } finally {
    continuingDraftId.value = null
  }
}

// Validate all mandatory fields before any API request.
const validateForm = () => {
  localError.value = ''

  if (!form.month) {
    localError.value = texts.value.create.errors.required
    return false
  }

  if (form.month < currentMonthMin.value) {
    localError.value = texts.value.create.errors.pastMonth
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

    await fetchSchedules()
  } catch {
    localError.value = errorSchedules.value || texts.value.create.errors.initialData
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
            <input
              ref="monthInputRef"
              v-model="form.month"
              type="month"
              name="month"
              :min="currentMonthMin"
              @click="openMonthPicker"
            >
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

      <section class="schedule-drafts" aria-label="Draft schedules list">
        <h2>{{ texts.create.draftsTitle }}</h2>
        <p class="schedule-intro">{{ texts.create.draftsIntro }}</p>

        <p v-if="loadingSchedules" class="form-success">
          {{ texts.create.loadingDrafts }}
        </p>

        <p v-else-if="errorSchedules" class="form-error">
          {{ errorSchedules || texts.create.errors.loadDrafts }}
        </p>

        <p v-else-if="!draftSchedules.length" class="form-success">
          {{ texts.create.noDrafts }}
        </p>

        <ul v-else class="schedule-drafts__list">
          <li v-for="draft in draftSchedules" :key="draft.id" class="schedule-drafts__item">
            <div class="schedule-drafts__meta">
              <strong>{{ formatScheduleMonth(draft.start_date) }}</strong>
              <span>
                {{ texts.create.periodLabel }}:
                {{ formatSchedulePeriod(draft.start_date, draft.end_date) }}
              </span>
            </div>

            <button
              type="button"
              class="login-button"
              :disabled="isBootstrapping || loadingScheduleCreation || continuingDraftId !== null"
              @click="continueDraftSchedule(draft.id)"
            >
              {{ continuingDraftId === draft.id ? texts.loading : texts.create.continueDraft }}
            </button>
          </li>
        </ul>
      </section>
    </section>
  </main>
</template>
