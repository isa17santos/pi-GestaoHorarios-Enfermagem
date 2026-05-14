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

const getTranslatedError = (msg: string) => {
  if (!msg) return ''
  
  const isEn = currentLocale.value === 'en'
  
  // Dicionário de traduções (PT <-> EN)
  const translations: Record<string, string> = {
    'Já existe um horário para este mês.': 'A schedule already exists for this month.',
    'Já existe uma edição em curso para este horário.': 'An edit is already in progress for this schedule.',
    'Sem permissão para editar horários.': 'No permission to edit schedules.',
    'Horário não encontrado.': 'Schedule not found.',
    'Ocorreu um erro interno no servidor.': 'An internal server error occurred.',
    'Seleciona o mês do horário.': 'Please select the schedule month.',
    'Não podes selecionar um mês anterior ao atual.': 'You cannot select a month earlier than the current month.',
    'Não foi possível criar o horário. Tenta novamente.': 'Could not create the schedule. Please try again.',
    'Não foi possível carregar os dados iniciais.': 'Could not load initial data.',
    'Não foi possível carregar os horários em rascunho.': 'Could not load draft schedules.',
    'Não foi possível apagar o rascunho.': 'Could not delete draft.',
    'Um horário publicado não pode ser apagado.': 'A published schedule cannot be deleted.',
    'Apenas o head nurse pode apagar rascunhos.': 'Only the head nurse can delete drafts.'
  }

  if (isEn) {
    // Se estivermos em EN, procuramos o valor correspondente à chave PT
    return translations[msg] || msg
  } else {
    // Se estivermos em PT, procuramos qual é a chave PT que tem este valor EN
    const ptKey = Object.keys(translations).find(key => translations[key] === msg)
    return ptKey || msg
  }
}


// ==================== Local UI State ====================

const localError = ref('')
watch(localError, (newValue) => {
  if (newValue) {
    const translated = getTranslatedError(newValue)
    if (translated !== newValue) {
      localError.value = translated
      return
    }
    // Timer 
    setTimeout(() => {
      localError.value = ''
    }, 5000)
  }
})

watch(currentLocale, () => {
  if (localError.value) {
    localError.value = getTranslatedError(localError.value)
  }
})

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
  <main class="dashboard-layout hr-page">
    <AppNavbar />

    <!-- AVISO ESTILO TOAST SUSPENSO -->
    <transition name="slide-down">
      <div v-if="localError" class="global-toast error">
        <div class="toast-content">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{{ localError }}</span>
        </div>
      </div>
    </transition>

    <section class="hr-content">
      <!-- Section header -->
      <div class="uc-top-bar">
        <div class="uc-title-group">
          <NuxtLink to="/dashboard" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            {{ texts.backButton }}
          </NuxtLink>
          <h1>{{ texts.create.pageTitle }}</h1>
          <p class="uc-subtitle">{{ texts.create.intro }}</p>
        </div>
      </div>

      <!-- Create Schedule Card -->
      <div class="hr-toolbar" style="margin-bottom: 20px;">
        <div class="hr-title-group">
          <h2 style="font-size: 1.5rem; color: var(--text); margin: 0;">{{ texts.create.month }}</h2>
        </div>
      </div>

      <div class="hr-card" style="padding: 30px; margin-bottom: 40px; overflow: visible !important; position: relative; z-index: 10;">
        <form class="login-form" novalidate @submit.prevent="handleSubmit" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
          <div class="schedule-period" style="flex: 1; min-width: 280px; margin: 0;">
            <!-- Custom month picker -->
            <div ref="monthPickerRef" class="schedule-month-picker" style="max-width: 100%;">
              <button
                type="button"
                class="schedule-month-picker__trigger"
                :aria-expanded="isMonthPickerOpen"
                :aria-label="texts.create.month"
                @click="toggleMonthPicker"
                style="height: 48px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; padding: 0 16px; display: flex; justify-content: space-between; align-items: center; width: 100%;"
              >
                <span>{{ selectedMonthLabel }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" />
                </svg>
              </button>

              <div v-if="isMonthPickerOpen" class="schedule-month-picker__panel" role="dialog" :aria-label="texts.create.month" style="top: 52px;">
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
          </div>

          <button
            type="submit"
            class="login-button schedule-primary-button"
            :disabled="isBootstrapping || loadingScheduleCreation || isSubmitting"
            style="height: 48px; padding: 0 28px; border-radius: 12px; font-weight: 600; white-space: nowrap;"
          >
            {{ isSubmitting ? texts.create.submitting : texts.create.submit }}
          </button>
        </form>
      </div>

      <!-- Drafts Toolbar/Card -->
      <div class="hr-toolbar" style="margin-bottom: 20px;">
        <div class="hr-title-group">
          <h2 style="font-size: 1.5rem; color: var(--text); margin: 0 0 8px;">{{ texts.create.draftsTitle }}</h2>
          <p class="uc-subtitle">{{ texts.create.draftsIntro }}</p>
        </div>
      </div>

      <div class="hr-card">
        <div v-if="loadingSchedules" class="hr-state-msg">
          <div class="spinner"></div>
          <p>{{ texts.create.loadingDrafts }}</p>
        </div>
        <div v-else-if="errorSchedules" class="hr-state-msg error">
          <p>{{ errorSchedules || texts.create.errors.loadDrafts }}</p>
        </div>
        <div v-else-if="!draftSchedules.length" class="hr-state-msg">
          <p>{{ texts.create.noDrafts }}</p>
        </div>
        <div v-else class="table-responsive">
          <table class="hr-table">
            <thead>
              <tr>
                <th class="col-left">{{ texts.create.month }}</th>
                <th class="col-left">{{ texts.create.periodLabel }}</th>
                <th class="col-right">{{ currentLocale === 'pt' ? 'Ações' : 'Actions' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="draft in draftSchedules" :key="draft.id">
                <td class="col-left">
                  <div class="user-cell">
                    <span class="user-name-text">{{ formatScheduleMonth(draft.start_date) }}</span>
                  </div>
                </td>
                <td class="col-left">
                  <span style="color: var(--muted); font-size: 0.95rem;">
                    {{ formatSchedulePeriod(draft.start_date, draft.end_date) }}
                  </span>
                </td>
                <td class="actions-cell col-right">
                  <button
                    type="button"
                    class="action-btn edit"
                    :disabled="isBootstrapping || loadingScheduleCreation || continuingDraftId !== null || deletingDraftId !== null"
                    :title="texts.create.continueDraft"
                    @click="continueDraftSchedule(draft.id)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </button>
                  <button
                    v-if="canDeleteDrafts"
                    type="button"
                    class="action-btn delete"
                    :disabled="isBootstrapping || loadingScheduleCreation || continuingDraftId !== null || deletingDraftId !== null"
                    :title="texts.create.deleteDraft"
                    @click="deleteDraftFromList(draft.id)"
                  >
                    <svg v-if="deletingDraftId !== draft.id" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      <line x1="10" y1="11" x2="10" y2="17"></line>
                      <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                    <span v-else style="font-size: 0.8rem;">…</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Eliminação -->
      <transition name="fade">
        <div v-if="isDeleteDraftModalOpen" class="modal-overlay" @click.self="closeDeleteDraftModal">
          <div class="modal-card">
            <div class="modal-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
              </svg>
            </div>
            <h2>{{ texts.create.deleteDraft }}</h2>
            <p>{{ texts.create.deleteDraftConfirmation }}</p>
            <div class="modal-actions">
              <button 
                class="modal-btn cancel" 
                :disabled="deletingDraftId !== null" 
                @click="closeDeleteDraftModal"
              >
                {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
              </button>
              <button 
                class="modal-btn confirm" 
                :disabled="deletingDraftId !== null" 
                @click="confirmDeleteDraftFromList"
              >
                {{ deletingDraftId !== null ? texts.create.deletingDraft : texts.create.deleteDraft }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </section>
  </main>
</template>

