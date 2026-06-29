<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})
// ==================== Dependencies ====================
const {
  schedules,
  loadingSchedules,
  errorSchedules,
  fetchSchedules,
  fetchSchedule,
  deleteSchedule,
  setSelectedPeriod,
} = useSchedule()
const { user, fetchMe, token } = useAuth()
const config = useRuntimeConfig()
const { currentLocale, texts, toggleLanguage, localeLabel, localeFlag } = useScheduleTexts()
// ==================== Local Texts ====================
const pageTexts = computed(() => ({
  pageEyebrow: currentLocale.value === 'pt' ? 'EDIÇÃO DE HORÁRIO' : 'SCHEDULE EDITING',
  pageTitle: currentLocale.value === 'pt' ? 'Editar horário' : 'Edit schedule',
  intro: currentLocale.value === 'pt'
    ? 'Selecione um horário publicado para editar.'
    : 'Select a published schedule to edit.',
  selectSchedule: currentLocale.value === 'pt' ? 'Selecionar horário' : 'Select schedule',
  submit: currentLocale.value === 'pt' ? 'Iniciar edição' : 'Start editing',
  submitting: currentLocale.value === 'pt' ? 'A iniciar...' : 'Starting...',
  revisionsTitle: currentLocale.value === 'pt' ? 'Edições em curso' : 'Ongoing edits',
  revisionsIntro: currentLocale.value === 'pt'
    ? 'Escolha um rascunho para continuar as alterações.'
    : 'Choose a draft to continue editing.',
  noRevisions: currentLocale.value === 'pt'
    ? 'Não existem edições em curso.'
    : 'There are no ongoing edits.',
  loadingRevisions: currentLocale.value === 'pt' ? 'A carregar edições...' : 'Loading edits...',
  errors: {
    required: currentLocale.value === 'pt'
      ? 'Selecione um horário para editar.'
      : 'Please select a schedule to edit.',
    startEditFailed: currentLocale.value === 'pt'
      ? 'Não foi possível iniciar a edição. Tenta novamente.'
      : 'Could not start editing. Please try again.',
  }
}))
// ==================== Access Control ====================
const canEditSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})
// ==================== Local UI State ====================
const localError = ref('')
const isBootstrapping = ref(true)
const isSubmitting = ref(false)
const continuingRevisionId = ref<number | null>(null)
const deletingRevisionId = ref<number | null>(null)
const isDeleteRevisionModalOpen = ref(false)
const pendingDeleteRevisionId = ref<number | null>(null)
// ==================== API Error Helper ====================
const extractApiErrorMessage = (error: unknown, fallback: string) => {
  const apiError = error as any
  if (apiError?.data?.message) return apiError.data.message
  if (apiError?.data?.error) return apiError.data.error
  const errorEntries = apiError?.data?.errors
  if (errorEntries && typeof errorEntries === 'object') {
    const firstErrorValue = Object.values(errorEntries)[0]
    if (Array.isArray(firstErrorValue) && firstErrorValue.length > 0) return firstErrorValue[0] || fallback
    if (typeof firstErrorValue === 'string' && firstErrorValue.trim()) return firstErrorValue
  }
  if (apiError?.statusMessage) return apiError.statusMessage
  return fallback
}
// ==================== Schedule List Helpers ====================
const publishedSchedules = computed(() => {
  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() // 0 = Janeiro, 1 = Fevereiro, etc.

  return schedules.value
    .filter((item) => {
      if (item.status !== 'published') return false
      if (!item.start_date) return false

      const parts = item.start_date.split('-')
      const partYear = parts[0]
      const partMonth = parts[1]

      if (partYear === undefined || partMonth === undefined) return false

      const year = parseInt(partYear, 10)
      const month = parseInt(partMonth, 10) - 1 

      if (Number.isNaN(year) || Number.isNaN(month)) return false

      if (year > currentYear) return true
      if (year === currentYear && month >= currentMonth) return true
      return false
    })
    .sort((a, b) => new Date(b.start_date).getTime() - new Date(a.start_date).getTime())
})

const revisionSchedules = computed(() => {
  return schedules.value
    .filter((item) => item.status === 'revision')
    .sort((a, b) => new Date(b.start_date).getTime() - new Date(a.start_date).getTime())
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
// Form and dropdown helpers removed since we use a list now
// ==================== Revision Actions ====================
const continueRevisionSchedule = async (scheduleId: number) => {
  localError.value = ''
  continuingRevisionId.value = scheduleId
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
    localError.value = errorSchedules.value || texts.value.create.errors.loadDrafts
  } finally {
    continuingRevisionId.value = null
  }
}
const deleteRevisionFromList = (draftId: number) => {
  localError.value = ''
  const draft = revisionSchedules.value.find((item) => item.id === draftId) ?? null
  if (!draft) {
    localError.value = texts.value.create.errors.loadDrafts
    return
  }
  if (!canEditSchedule.value) {
    localError.value = texts.value.create.errors.deleteDraftUnauthorized
    return
  }
  pendingDeleteRevisionId.value = draftId
  isDeleteRevisionModalOpen.value = true
}
const closeDeleteRevisionModal = () => {
  if (deletingRevisionId.value !== null) return
  isDeleteRevisionModalOpen.value = false
  pendingDeleteRevisionId.value = null
}
const confirmDeleteRevisionFromList = async () => {
  const draftId = pendingDeleteRevisionId.value
  if (draftId === null) return
  localError.value = ''
  deletingRevisionId.value = draftId
  try {
    await deleteSchedule(draftId)
    isDeleteRevisionModalOpen.value = false
    pendingDeleteRevisionId.value = null
  } catch {
    localError.value = errorSchedules.value || texts.value.create.errors.deleteDraft
  } finally {
    deletingRevisionId.value = null
  }
}
// ==================== Form Flow ====================
const handleDeleteRevisionModalEscape = (event: KeyboardEvent) => {
  if (event.key !== 'Escape') return
  closeDeleteRevisionModal()
}
const handleSubmit = async (scheduleId: number) => {
  localError.value = ''
  isSubmitting.value = true
  try {
    // Calling the endpoint to start the edition process (cloning a published schedule)
    const response = await $fetch<{ data: { id: number }, message?: string }>(
      `${config.public.apiBase}/schedules/${scheduleId}/edit`,
      {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      }
    )
    const revisionId = response.data.id
    const createdSchedule = await fetchSchedule(revisionId)
    const startDate = new Date(createdSchedule.start_date)
    if (!Number.isNaN(startDate.getTime())) {
      setSelectedPeriod(startDate.getMonth() + 1, startDate.getFullYear())
    }
    await navigateTo({
      path: '/schedule-create-grid',
      query: {
        scheduleId: String(revisionId),
      },
    })
  } catch (err) {
    localError.value = extractApiErrorMessage(err, pageTexts.value.errors.startEditFailed)
  } finally {
    isSubmitting.value = false
  }
}
// ==================== Lifecycle ====================
onMounted(async () => {
  if (process.client) {
    window.addEventListener('keydown', handleDeleteRevisionModalEscape)
  }
  isBootstrapping.value = true
  localError.value = ''
  try {
    if (!user.value) {
      await fetchMe().catch(() => null)
    }
    if (!canEditSchedule.value) {
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
    window.removeEventListener('keydown', handleDeleteRevisionModalEscape)
  }
})
</script>


<template>
  <main class="dashboard-layout hr-page">
    <AppNavbar />

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
          <h1>{{ pageTexts.pageTitle }}</h1>
          <p class="uc-subtitle">{{ pageTexts.intro }}</p>
        </div>
      </div>

      <!-- Published Schedules -->
      <div class="hr-toolbar" style="margin-bottom: 20px;">
        <div class="hr-title-group">
          <h2 style="font-size: 1.5rem; color: var(--text); margin: 0;">{{ pageTexts.selectSchedule }}</h2>
        </div>
      </div>

      <div class="hr-card">
        <div v-if="publishedSchedules.length === 0" class="hr-state-msg">
          <p>{{ currentLocale === 'pt' ? 'Não existem horários publicados para editar.' : 'There are no published schedules to edit.' }}</p>
        </div>
        <div v-else class="table-responsive">
          <table class="hr-table">
            <thead>
              <tr>
                <th class="col-left">{{ texts.create.month }}</th>
                <th class="col-left">{{ texts.create.periodLabel }}</th>
                <th class="col-right">{{ currentLocale === 'pt' ? 'Ação' : 'Action' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sch in publishedSchedules" :key="sch.id">
                <td class="col-left">
                  <div class="user-cell">
                    <span class="user-name-text">{{ formatScheduleMonth(sch.start_date) }}</span>
                  </div>
                </td>
                <td class="col-left">
                  <span style="color: var(--muted); font-size: 0.95rem;">
                    {{ formatSchedulePeriod(sch.start_date, sch.end_date) }}
                  </span>
                </td>
                <td class="actions-cell col-right">
                  <button
                    type="button"
                    class="action-btn edit"
                    :title="currentLocale === 'pt' ? 'Editar' : 'Edit'"
                    :disabled="isBootstrapping || loadingSchedules || isSubmitting"
                    @click="handleSubmit(sch.id)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Ongoing Edits -->
      <div class="hr-toolbar" style="margin-bottom: 20px; margin-top: 50px;">
        <div class="hr-title-group">
          <h2 style="font-size: 1.5rem; color: var(--text); margin: 0 0 8px;">{{ pageTexts.revisionsTitle }}</h2>
          <p class="uc-subtitle">{{ pageTexts.revisionsIntro }}</p>
        </div>
      </div>

      <div class="hr-card">
        <div v-if="loadingSchedules" class="hr-state-msg">
          <div class="spinner"></div>
          <p>{{ pageTexts.loadingRevisions }}</p>
        </div>
        <div v-else-if="errorSchedules" class="hr-state-msg error">
          <p>{{ errorSchedules || texts.create.errors.loadDrafts }}</p>
        </div>
        <div v-else-if="!revisionSchedules.length" class="hr-state-msg">
          <p>{{ pageTexts.noRevisions }}</p>
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
              <tr v-for="draft in revisionSchedules" :key="draft.id">
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
                    :disabled="isBootstrapping || loadingSchedules || continuingRevisionId !== null || deletingRevisionId !== null"
                    :title="texts.create.continueDraft"
                    @click="continueRevisionSchedule(draft.id)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </button>
                  <button
                    v-if="canEditSchedule"
                    type="button"
                    class="action-btn delete"
                    :disabled="isBootstrapping || loadingSchedules || continuingRevisionId !== null || deletingRevisionId !== null"
                    :title="texts.create.deleteDraft"
                    @click="deleteRevisionFromList(draft.id)"
                  >
                    <svg v-if="deletingRevisionId !== draft.id" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
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

      <!-- Delete Confirmation Modal -->
      <transition name="fade">
        <div v-if="isDeleteRevisionModalOpen" class="modal-overlay" @click.self="closeDeleteRevisionModal">
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
                :disabled="deletingRevisionId !== null" 
                @click="closeDeleteRevisionModal"
              >
                {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
              </button>
              <button 
                class="modal-btn confirm" 
                :disabled="deletingRevisionId !== null" 
                @click="confirmDeleteRevisionFromList"
              >
                {{ deletingRevisionId !== null ? texts.create.deletingDraft : texts.create.deleteDraft }}
              </button>
            </div>
          </div>
        </div>
      </transition>
    </section>
  </main>
</template>
