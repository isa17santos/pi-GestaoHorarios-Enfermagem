<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

// ==================== Dependencies ====================

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
  deleteSchedule,
  setSelectedPeriod,
} = useSchedule()

// Access authenticated user state to enforce role-based access in this page too.
const { user, fetchMe } = useAuth()

// Use shared schedule texts composable.
const { currentLocale, texts, toggleLanguage, localeLabel, localeFlag } = useScheduleTexts()

// ==================== Access Control ====================

// Protect this page at component level: only admin/head nurse can use schedule creation.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

// Only the head nurse can delete draft schedules.
const canDeleteDrafts = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

// Schedule period form model.
const form = reactive({
  month: '',
})

// ==================== Local UI State ====================

const localError = ref('')
const isBootstrapping = ref(true)
const isSubmitting = ref(false)
const continuingDraftId = ref<number | null>(null)
const deletingDraftId = ref<number | null>(null)
const isDeleteDraftModalOpen = ref(false)
const pendingDeleteDraftId = ref<number | null>(null)
const isMonthPickerOpen = ref(false)
const monthPickerRef = ref<HTMLElement | null>(null)
const visiblePickerYear = ref(new Date().getFullYear())

// ==================== Month Selection Helpers ====================

const currentMonthMin = computed(() => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
})

const minPickerYear = computed(() => Number(currentMonthMin.value.slice(0, 4)))

const pickerMonthNames = computed(() => {
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'

  return Array.from({ length: 12 }, (_, index) => {
    const date = new Date(2026, index, 1)
    const label = new Intl.DateTimeFormat(locale, { month: 'short' }).format(date)
    return label.charAt(0).toUpperCase() + label.slice(1).replace('.', '')
  })
})

const pickerMonths = computed(() => {
  return Array.from({ length: 12 }, (_, index) => {
    const month = index + 1
    const value = `${visiblePickerYear.value}-${String(month).padStart(2, '0')}`
    return {
      value,
      label: pickerMonthNames.value[index] || String(month),
      disabled: value < currentMonthMin.value,
    }
  })
})

const selectedMonthLabel = computed(() => {
  if (!form.month) {
    return currentLocale.value === 'pt' ? 'Selecionar mês' : 'Select month'
  }

  const selectedDate = new Date(`${form.month}-01`)
  if (Number.isNaN(selectedDate.getTime())) {
    return form.month
  }

  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(selectedDate)
  return label.charAt(0).toUpperCase() + label.slice(1)
})

const canGoToPreviousPickerYear = computed(() => visiblePickerYear.value > minPickerYear.value)

// ==================== Draft List Helpers ====================

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
  isMonthPickerOpen.value = true

  if (form.month) {
    const selectedYear = Number(form.month.slice(0, 4))
    if (Number.isFinite(selectedYear)) {
      visiblePickerYear.value = selectedYear
      return
    }
  }

  visiblePickerYear.value = minPickerYear.value
}

const closeMonthPicker = () => {
  isMonthPickerOpen.value = false
}

const toggleMonthPicker = () => {
  if (isMonthPickerOpen.value) {
    closeMonthPicker()
    return
  }

  openMonthPicker()
}

const selectMonthFromPicker = (monthValue: string) => {
  if (monthValue < currentMonthMin.value) return
  form.month = monthValue
  closeMonthPicker()
}

const goToPreviousPickerYear = () => {
  if (!canGoToPreviousPickerYear.value) return
  visiblePickerYear.value -= 1
}

const goToNextPickerYear = () => {
  visiblePickerYear.value += 1
}

const handleMonthPickerOutsideClick = (event: MouseEvent) => {
  if (!isMonthPickerOpen.value) return

  const target = event.target as Node | null
  if (!target) return

  if (monthPickerRef.value?.contains(target)) return
  closeMonthPicker()
}

// ==================== Draft Actions ====================

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

// Deletes a draft directly from the list, using the same rules as the grid page.
const deleteDraftFromList = (draftId: number) => {
  localError.value = ''

  const draft = draftSchedules.value.find((item) => item.id === draftId) ?? null

  if (!draft) {
    localError.value = texts.value.create.errors.loadDrafts
    return
  }

  if (draft.status === 'published') {
    localError.value = texts.value.create.errors.deleteDraftPublished
    return
  }

  if (!canDeleteDrafts.value) {
    localError.value = texts.value.create.errors.deleteDraftUnauthorized
    return
  }

  pendingDeleteDraftId.value = draftId
  isDeleteDraftModalOpen.value = true
}

const closeDeleteDraftModal = () => {
  if (deletingDraftId.value !== null) return
  isDeleteDraftModalOpen.value = false
  pendingDeleteDraftId.value = null
}

const confirmDeleteDraftFromList = async () => {
  const draftId = pendingDeleteDraftId.value
  if (draftId === null) return

  localError.value = ''
  deletingDraftId.value = draftId

  try {
    await deleteSchedule(draftId)
    isDeleteDraftModalOpen.value = false
    pendingDeleteDraftId.value = null
  } catch {
    localError.value = errorScheduleCreation.value || texts.value.create.errors.deleteDraft
  } finally {
    deletingDraftId.value = null
  }
}

// ==================== Form Flow ====================

// Fecha o modal de confirmação com a tecla Escape.
const handleDeleteDraftModalEscape = (event: KeyboardEvent) => {
  if (event.key !== 'Escape') return

  if (isMonthPickerOpen.value) {
    closeMonthPicker()
    return
  }

  closeDeleteDraftModal()
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

// ==================== Lifecycle ====================

onMounted(async () => {
  if (process.client) {
    window.addEventListener('keydown', handleDeleteDraftModalEscape)
    window.addEventListener('mousedown', handleMonthPickerOutsideClick)
  }

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

onBeforeUnmount(() => {
  if (process.client) {
    window.removeEventListener('keydown', handleDeleteDraftModalEscape)
    window.removeEventListener('mousedown', handleMonthPickerOutsideClick)
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

            <!-- Custom month picker to keep a consistent visual style across browsers. -->
            <div ref="monthPickerRef" class="schedule-month-picker">
              <button
                type="button"
                class="schedule-month-picker__trigger"
                :aria-expanded="isMonthPickerOpen"
                :aria-label="texts.create.month"
                @click="toggleMonthPicker"
              >
                <span>{{ selectedMonthLabel }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" />
                </svg>
              </button>

              <div v-if="isMonthPickerOpen" class="schedule-month-picker__panel" role="dialog" :aria-label="texts.create.month">
                <div class="schedule-month-picker__header">
                  <button
                    type="button"
                    class="schedule-secondary-button"
                    :disabled="!canGoToPreviousPickerYear"
                    @click="goToPreviousPickerYear"
                  >
                    {{ currentLocale === 'pt' ? 'Ano anterior' : 'Previous year' }}
                  </button>

                  <strong>{{ visiblePickerYear }}</strong>

                  <button
                    type="button"
                    class="schedule-secondary-button"
                    @click="goToNextPickerYear"
                  >
                    {{ currentLocale === 'pt' ? 'Ano seguinte' : 'Next year' }}
                  </button>
                </div>

                <div class="schedule-month-picker__months">
                  <button
                    v-for="monthOption in pickerMonths"
                    :key="monthOption.value"
                    type="button"
                    class="schedule-month-picker__month"
                    :class="{ 'is-selected': form.month === monthOption.value }"
                    :disabled="monthOption.disabled"
                    @click="selectMonthFromPicker(monthOption.value)"
                  >
                    {{ monthOption.label }}
                  </button>
                </div>
              </div>
            </div>
          </label>
        </div>

        <div class="schedule-actions-row">
          <button
            type="submit"
            class="login-button schedule-primary-button"
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

            <div class="schedule-drafts__actions">
              <button
                v-if="canDeleteDrafts"
                type="button"
                class="schedule-drafts__icon-button schedule-drafts__icon-button--danger"
                :disabled="isBootstrapping || loadingScheduleCreation || continuingDraftId !== null || deletingDraftId !== null"
                :title="texts.create.deleteDraft"
                :aria-label="texts.create.deleteDraft"
                @click="deleteDraftFromList(draft.id)"
              >
                <svg v-if="deletingDraftId !== draft.id" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="16" height="16" aria-hidden="true">
                  <path d="M18 6 6 18" />
                  <path d="M6 6 18 18" />
                </svg>
                <span v-else aria-hidden="true">…</span>
              </button>

              <button
                type="button"
                class="login-button schedule-primary-button"
                :disabled="isBootstrapping || loadingScheduleCreation || continuingDraftId !== null || deletingDraftId !== null"
                @click="continueDraftSchedule(draft.id)"
              >
                {{ continuingDraftId === draft.id ? texts.loading : texts.create.continueDraft }}
              </button>
            </div>
          </li>
        </ul>
      </section>

      <div
        v-if="isDeleteDraftModalOpen"
        class="schedule-confirm-overlay"
        role="presentation"
        @click.self="closeDeleteDraftModal"
      >
        <!-- Generic confirmation modal used for destructive draft actions. -->
        <div class="schedule-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="delete-draft-title">
          <h3 id="delete-draft-title">
            {{ texts.create.deleteDraft }}
          </h3>

          <p>{{ texts.create.deleteDraftConfirmation }}</p>

          <div class="schedule-confirm-actions">
            <button
              type="button"
              class="schedule-secondary-button"
              :disabled="deletingDraftId !== null"
              @click="closeDeleteDraftModal"
            >
              {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
            </button>

            <button
              type="button"
              class="login-button schedule-danger-button"
              :disabled="deletingDraftId !== null"
              @click="confirmDeleteDraftFromList"
            >
              {{ deletingDraftId !== null ? texts.create.deletingDraft : texts.create.deleteDraft }}
            </button>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>
