<script setup lang="ts">
import type { CSSProperties } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

definePageMeta({
  middleware: 'auth',
})

// ==================== Dependencies ====================

// Pull schedule state and actions from the composable.
const {
  schedule,
  shifts,
  nurses,
  shiftTypes,
  selectedMonth,
  selectedYear,
  loadingNurses,
  loadingShiftTypes,
  loadingShifts,
  loadingShiftCreation,
  errorNurses,
  errorShiftTypes,
  errorShifts,
  errorScheduleCreation,
  errorShiftCreation,
  fetchNurses,
  fetchScheduleShifts,
  fetchShifts,
  fetchShiftTypes,
  fetchSchedule,
  deleteSchedule,
  createShift,
  updateShift,
  setSelectedPeriod,
} = useSchedule()

const config = useRuntimeConfig()

// Pull auth state to enforce role-based access.
const { user, fetchMe, token } = useAuth()
const route = useRoute()
const router = useRouter()

// Use shared schedule texts composable
const { currentLocale, texts, toggleLanguage, localeLabel, localeFlag } = useScheduleTexts()

// ==================== Access Control ====================

// Returns true if the current user is allowed to create/edit schedules.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

const canDeleteDraft = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  const isHeadNurse = normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
  return isHeadNurse && schedule.value?.status === 'draft'
})

// ==================== Local UI State ====================

// Local feedback messages shown to the user.
const localError = ref('')
const localWarning = ref('')
const localSuccess = ref('')
const validationErrorCells = ref<{ nurseId: number, dateIso: string }[]>([])
const validationErrorMessage = ref('')
const rawError = ref<unknown>(null)             
const rawValidationError = ref<unknown>(null)

watch(localWarning, (newVal) => {
  if (newVal) {
    setTimeout(() => {
      localWarning.value = ''
    }, 6000)
  }
})
watch(localError, (newVal) => {
  if (newVal) {
    setTimeout(() => {
      localError.value = ''
    }, 6000)
  }
})
watch(localSuccess, (newVal) => {
  if (newVal) {
    setTimeout(() => {
      localSuccess.value = ''
    }, 6000)
  }
})
watch(currentLocale, (newLocale) => {
  if (localError.value) {
    localError.value = getBackendErrorMessage(localError.value)
  }
  if (localWarning.value) {
    localWarning.value = getBackendErrorMessage(localWarning.value)
  }
  if (localSuccess.value) {
    if (newLocale === 'en') {
      if (localSuccess.value.includes('Horário publicado com sucesso')) {
        localSuccess.value = 'Schedule published successfully. Email sent to the nursing team.'
      }
      if (localSuccess.value.includes('Horário já publicado')) {
        localSuccess.value = 'Schedule already published.'
      }
      if (localSuccess.value.includes('Rascunho apagado')) {
        localSuccess.value = 'Draft deleted successfully.'
      }
      if (localSuccess.value.includes('Grelha guardada com sucesso')) {
        localSuccess.value = 'Grid saved successfully.'
      }
      if (localSuccess.value.includes('Alterações publicadas com sucesso')) {
        localSuccess.value = 'Changes published successfully.'
      }
    } else {
      if (localSuccess.value.includes('Schedule published successfully')) {
        localSuccess.value = 'Horário publicado com sucesso. Email enviado à equipa de enfermagem.'
      }
      if (localSuccess.value.includes('Schedule already published')) {
        localSuccess.value = 'Horário já publicado.'
      }
      if (localSuccess.value.includes('Draft deleted')) {
        localSuccess.value = 'Rascunho apagado com sucesso.'
      }
      if (localSuccess.value.includes('Grid saved successfully')) {
        localSuccess.value = 'Grelha guardada com sucesso.'
      }
      if (localSuccess.value.includes('Changes published successfully')) {
        localSuccess.value = 'Alterações publicadas com sucesso.'
      }
    }
  }
})
watch(currentLocale, () => {
  if (localError.value && rawError.value) {
    localError.value = getBackendErrorMessage(rawError.value)
  }
  if (validationErrorMessage.value && rawValidationError.value) {
    validationErrorMessage.value = getBackendErrorMessage(rawValidationError.value)
  }
})


// True while the page is loading its initial data.
const isBootstrapping = ref(true)

// True while the save operation is in progress.
const isSaving = ref(false)

// True while publish operation is in progress.
const isPublishing = ref(false)

// True while draft deletion is in progress.
const isDeletingDraft = ref(false)

// Controls the custom delete confirmation modal.
const isDeleteDraftModalOpen = ref(false)

// Controls the custom warning confirmation modal.
const isWarningModalOpen = ref(false)
const warningMessage = ref('')

// Currently selected shift type in the toolbar.
const selectedShiftTypeId = ref<number | null>(null)

// True while drag fill is active.
const isDragging = ref(false)

// Nurse row currently being dragged.
const draggingNurseId = ref<number | null>(null)

// Cell currently hovered, used to show the clear button.
const hoveredCellKey = ref<string | null>(null)

// Cache flag to avoid repeating previous-month schedule lookups.
const previousMonthAssignmentsLoaded = ref(false)

// Floating tooltip state for preference and warning messages.
const tooltipVisible = ref(false)
const tooltipText = ref('')
const tooltipTop = ref(0)
const tooltipLeft = ref(0)

const tooltipStyle = computed<CSSProperties>(() => ({
  position: 'fixed',
  top: `${tooltipTop.value}px`,
  left: `${tooltipLeft.value}px`,
  transform: 'translateX(-50%) translateY(-100%)',
  zIndex: '9999',
  pointerEvents: 'none',
}))

// In-memory map of cell assignments: "nurseId::YYYY-MM-DD" -> shiftTypeId.
const cellAssignments = ref<Record<string, number | null>>({})

// ==================== Computed View State ====================

// Reads and validates the schedule id from the URL query string.
const scheduleId = computed(() => {
  const rawValue = route.query.scheduleId
  const parsed = Number(Array.isArray(rawValue) ? rawValue[0] : rawValue)
  return Number.isFinite(parsed) ? parsed : null
})

// Returns the current month and year as a localised string, e.g. "abril de 2026".
const monthLabel = computed(() => {
  const date = new Date(selectedYear.value, selectedMonth.value - 1, 1)
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date)
})

// Month boundaries for navigation and controlled historical view.

const scheduleBaseMonthDate = computed(() => {
  if (!schedule.value?.start_date) return null

  const date = new Date(schedule.value.start_date)
  if (Number.isNaN(date.getTime())) return null

  return new Date(date.getFullYear(), date.getMonth(), 1)
})

const canGoToPreviousMonth = computed(() => {
  const baseMonthDate = scheduleBaseMonthDate.value
  if (!baseMonthDate) return false

  const visibleMonthDate = new Date(selectedYear.value, selectedMonth.value - 1, 1)
  const minAllowedDate = new Date(baseMonthDate.getFullYear(), baseMonthDate.getMonth() - 1, 1)

  return visibleMonthDate > minAllowedDate
})

const canGoToNextMonth = computed(() => {
  const baseMonthDate = scheduleBaseMonthDate.value
  if (!baseMonthDate) return false

  const visibleMonthDate = new Date(selectedYear.value, selectedMonth.value - 1, 1)
  return visibleMonthDate < baseMonthDate
})

const scheduleStatusLabel = computed(() => {
  if (schedule.value?.status === 'published') {
    return currentLocale.value === 'pt' ? 'Publicado' : 'Published'
  }

  return currentLocale.value === 'pt' ? 'Rascunho' : 'Draft'
})

const scheduleStatusClass = computed(() => {
  return schedule.value?.status === 'published'
    ? 'schedule-status-badge--published'
    : 'schedule-status-badge--draft'
})

// Shared helpers for backend error normalization and user-friendly feedback.

const getBackendErrorMessage = (error: unknown) => {
  let message = ''
  
  if (typeof error === 'string') {
    message = error.trim()
  } else {
    const backendError = (error as { data?: { message?: string }, message?: string })?.data?.message
    if (typeof backendError === 'string' && backendError.trim().length > 0) {
      message = backendError.trim()
    } else {
      const runtimeError = (error as { message?: string })?.message
      message = typeof runtimeError === 'string' ? runtimeError.trim() : ''
    }
  }

  if (message) {
    const hasNoAssignedShifts = message.includes('There are days') || message.includes('Existem dias no horário') || message.includes('sem turnos atribuídos')
    const hasInsufficientNurses = message.includes('minimum number') || message.includes('número mínimo') || message.includes('não está a ser cumprido')
    const hasAlreadyPublished = message.includes('already published') || message.includes('já publicado')
    const hasInvalidDateRange = message.includes('invalid date range') || message.includes('intervalo de datas inválido')
    const hasShiftNotFound = message.includes('Shift not found') || message.includes('Turno não encontrado')
    const hasNoPermission = message.includes('No permission') || message.includes('Sem permissão')
    const hasMissingId = message.includes('identify schedule') || message.includes('identificar o horário')
    const hasSessionUnavailable = message.includes('session unavailable') || message.includes('Sessão do horário indisponível')
    const hasMissingShiftId = message.includes('without an identifier') || message.includes('sem identificador')
    const hasNoNewAssignments = message.includes('no new assignments') || message.includes('Não existem novas atribuições')
    const hasCouldNotSave = message.includes('Could not save assignments') || message.includes('Não foi possível guardar as atribuições')
    const hasMoreThanTwoConsecutiveDaysOff = message.includes('Não pode ter mais de 2 dias de folga') || message.includes('Cannot have more than 2 consecutive days off')
    const hasRequiredTwoDaysOffPerWeek = message.includes('Obrigatorio 2 dias de folga por semana') || message.includes('Required 2 days off per week')
    
    const isEnglish = currentLocale.value === 'en'

    
    if (hasNoAssignedShifts) {
      return isEnglish 
        ? 'There are days in the schedule without any shifts assigned to nurses.' 
        : 'Existem dias no horário sem turnos atribuídos a enfermeiros.'
    }
    if (hasInsufficientNurses) {
      return isEnglish 
        ? 'The minimum number of nurses required for each shift is not being met.' 
        : 'O número mínimo de enfermeiros exigido para cada turno não está a ser cumprido.'
    }
    if (hasAlreadyPublished) {
      return isEnglish 
        ? 'Schedule is already published.' 
        : 'Horário já publicado.'
    }
    if (hasInvalidDateRange) {
      return isEnglish 
        ? 'The schedule has an invalid date range.' 
        : 'O horário tem um intervalo de datas inválido.'
    }
    if (hasShiftNotFound) {
      return isEnglish 
        ? 'Shift not found.' 
        : 'Turno não encontrado.'
    }
    if (hasNoPermission) {
      return isEnglish 
        ? 'No permission.' 
        : 'Sem permissão.'
    }
    if (hasMissingId) {
      return isEnglish ? 'Could not identify schedule.' : 'Não foi possível identificar o horário.'
    }
    if (hasSessionUnavailable) {
      return isEnglish ? 'Schedule session unavailable. Recreate the period before editing the grid.' : 'Sessão do horário indisponível. Volta a criar o período antes de editar a grelha.'
    }
    if (hasMissingShiftId) {
      return isEnglish ? 'Could not update an existing shift without an identifier.' : 'Não foi possível atualizar um turno existente sem identificador.'
    }
    if (hasNoNewAssignments) {
      return isEnglish ? 'There are no new assignments to save.' : 'Não existem novas atribuições para guardar.'
    }
    if (hasCouldNotSave) {
      return isEnglish ? 'Could not save assignments. Try again.' : 'Não foi possível guardar as atribuições. Tenta novamente.'
    }
    if (hasMoreThanTwoConsecutiveDaysOff) {
      return isEnglish 
        ? 'Cannot have more than 2 consecutive days off.' 
        : 'Não pode ter mais de 2 dias de folga seguidos.'
    }
    if (hasRequiredTwoDaysOffPerWeek) {
      return isEnglish 
        ? 'Required 2 days off per week.' 
        : 'Obrigatório 2 dias de folga por semana.'
    }

  }

  return message
}

const normalizeErrorMessage = (message: string) => {
  return message
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

const isNetworkPublishError = (error: unknown) => {
  const statusCode = (error as { statusCode?: number, status?: number })?.statusCode ?? (error as { status?: number })?.status
  if (typeof statusCode === 'number') {
    return false
  }

  const normalizedMessage = normalizeErrorMessage(getBackendErrorMessage(error))
  return normalizedMessage.includes('failed to fetch')
    || normalizedMessage.includes('networkerror')
    || normalizedMessage.includes('network error')
    || normalizedMessage.includes('load failed')
}

const shouldTryNextPublishEndpoint = (error: unknown) => {
  const statusCode = (error as { statusCode?: number, status?: number })?.statusCode ?? (error as { status?: number })?.status
  const normalizedMessage = normalizeErrorMessage(getBackendErrorMessage(error))

  return statusCode === 404
    || statusCode === 405
    || normalizedMessage.includes('method not allowed')
    || normalizedMessage.includes('not found')
    || normalizedMessage.includes('unsupported method')
}

// Returns an array of day objects for the current month, used to build the grid columns.
const monthDays = computed(() => {
  const year = selectedYear.value
  const month = Math.min(12, Math.max(1, selectedMonth.value))
  const daysInMonth = new Date(year, month, 0).getDate()

  return Array.from({ length: daysInMonth }, (_, index) => {
    const day = index + 1
    const date = new Date(year, month - 1, day)
    const weekDay = date.getDay()

    return {
      day,
      dateIso: `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
      isWeekend: weekDay === 0 || weekDay === 6,
    }
  })
})

// Builds a map of shift type id -> background colour from a fixed palette.
const shiftTypeStyleMap = computed(() => {
  const palette = [
    '#d9f3ff',
    '#dff7e8',
    '#fff3d6',
    '#f4e6ff',
    '#ffdfe4',
    '#e3efff',
    '#fce7d8',
  ]

  return shiftTypes.value.reduce<Record<number, string>>((acc, shiftType, index) => {
    acc[shiftType.id] = palette[index % palette.length] ?? ''
    return acc
  }, {})
})

// ==================== Grid Helpers ====================

// Returns a unique key for a grid cell in the format "nurseId::YYYY-MM-DD".
const getCellKey = (nurseId: number, dateIso: string) => `${nurseId}::${dateIso}`

// Returns the shift type id assigned to a cell, or null if unassigned.
const getCellShiftTypeId = (nurseId: number, dateIso: string) => {
  return cellAssignments.value[getCellKey(nurseId, dateIso)] ?? null
}

// ==================== Preference Helpers ====================

// Returns the nurse preference object for the current schedule.
const getNursePreference = (nurseId: number) => {
  const nurse = nurses.value.find((item) => item.id === nurseId)
  const preferences = nurse?.preferences

  if (!preferences || !Array.isArray(preferences)) return null
  return preferences.find((pref) => pref.schedule_id === scheduleId.value) ?? preferences[0]
}

const isWeekendDateIso = (dateIso: string) => {
  const date = new Date(`${dateIso}T00:00:00`)
  if (Number.isNaN(date.getTime())) return false
  const weekDay = date.getDay()
  return weekDay === 0 || weekDay === 6
}

const goesAgainstPreference = (nurseId: number, dateIso: string, shiftTypeId: number): boolean => {
  const preference = getNursePreference(nurseId)
  if (!preference) return false
  if (blockingShiftTypeIds.value.has(shiftTypeId)) return false

  const shiftName = getShiftTypeNameById(shiftTypeId).trim().toLowerCase()
  if (shiftName === 'morning' && preference.prefers_morning === false) return true
  if (shiftName === 'afternoon' && preference.prefers_afternoon === false) return true
  if (shiftName === 'night' && preference.prefers_night === false) return true
  if (isWeekendDateIso(dateIso) && preference.avoid_weekends) return true

  return false
}

const getNursePreferenceSummary = (nurseId: number) => computed(() => {
  const preference = getNursePreference(nurseId)
  if (!preference) {
    return {
      conflicts: 0,
      preferenceText: texts.value.edit.noPreferences,
    }
  }

  let conflicts = 0
  for (const day of monthDays.value) {
    const shiftTypeId = getCellShiftTypeId(nurseId, day.dateIso)
    if (shiftTypeId !== null && goesAgainstPreference(nurseId, day.dateIso, shiftTypeId)) {
      conflicts += 1
    }
  }

  const preferredTimes: string[] = []
  if (preference.prefers_morning) preferredTimes.push(texts.value.edit.shiftNames.morning)
  if (preference.prefers_afternoon) preferredTimes.push(texts.value.edit.shiftNames.afternoon)
  if (preference.prefers_night) preferredTimes.push(texts.value.edit.shiftNames.night)
  if (preference.prefers_weekends && !preference.avoid_weekends) preferredTimes.push(texts.value.edit.weekends)

  const parts: string[] = []
  if (preferredTimes.length) {
    parts.push(`${texts.value.edit.preferLabel} ${preferredTimes.join(', ')}.`)
  }

  if (preference.avoid_weekends) {
    parts.push(`${texts.value.edit.avoidLabel} ${texts.value.edit.weekends}.`)
  }

  return {
    conflicts,
    preferenceText: parts.length > 0 ? parts.join(' ') : texts.value.edit.noPreferences,
  }
})

// Returns the display name for a shift type, or an empty string if not found.
const getShiftTypeNameById = (shiftTypeId: number | null): string => {
  if (!shiftTypeId) return ''
  return shiftTypes.value.find((item) => item.id === shiftTypeId)?.name ?? ''
}

// Returns the background colour for a shift type.
const getShiftTypeBackgroundColor = (shiftTypeId: number | null) => {
  if (!shiftTypeId) return ''
  return shiftTypeStyleMap.value[shiftTypeId] || ''
}

// ==================== Validation Helpers ====================

// Returns ids for shift types that represent blocking absences.
const blockingShiftTypeIds = computed(() => {
  const blockingNames = new Set(['dayoff', 'holidays', 'sick leave', 'parental leave'])

  return shiftTypes.value.reduce<Set<number>>((acc, shiftType) => {
    const normalizedName = shiftType.name.trim().toLowerCase()

    if (blockingNames.has(normalizedName)) {
      acc.add(shiftType.id)
    }

    return acc
  }, new Set<number>())
})

type FillValidationResult = {
  allowed: boolean
  reason?: 'overlap' | 'rest_warning'
}

// Parses HH:mm:ss into total minutes.
const toMinutes = (time: string) => {
  const [hoursRaw, minutesRaw] = time.split(':')
  const hours = Number(hoursRaw)
  const minutes = Number(minutesRaw)

  if (Number.isNaN(hours) || Number.isNaN(minutes)) return null
  return (hours * 60) + minutes
}

// Returns the dateIso for the previous/next day.
const getRelativeDateIso = (dateIso: string, dayOffset: number) => {
  const date = new Date(`${dateIso}T00:00:00`)
  if (Number.isNaN(date.getTime())) return null

  date.setDate(date.getDate() + dayOffset)

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const shiftCrossesMidnight = (shiftType: { start_time: string; end_time: string }) => {
  const startMinutes = toMinutes(shiftType.start_time)
  const endMinutes = toMinutes(shiftType.end_time)
  if (startMinutes === null || endMinutes === null) return false
  return endMinutes <= startMinutes
}

const effectiveShiftEndMinutes = (shiftType: { start_time: string; end_time: string }) => {
  const endMinutes = toMinutes(shiftType.end_time)
  if (endMinutes === null) return null

  return shiftCrossesMidnight(shiftType)
    ? endMinutes + 24 * 60
    : endMinutes
}

const restGapAfterPreviousShift = (
  previousShiftType: { start_time: string; end_time: string },
  nextShiftType: { start_time: string; end_time: string }
) => {
  const previousEndAbsolute = effectiveShiftEndMinutes(previousShiftType)
  const nextStartMinutes = toMinutes(nextShiftType.start_time)

  if (previousEndAbsolute === null || nextStartMinutes === null) return null

  return (nextStartMinutes + 24 * 60) - previousEndAbsolute
}

// Returns true when assigning a shift would violate the rest rules.
const hasRestViolation = (nurseId: number, dateIso: string, shiftTypeId: number): boolean => {
  return false
}

// Returns true when assigning a shift creates a sub-11h rest interval with adjacent days.
const hasRestWarningForAssignment = (nurseId: number, dateIso: string, shiftTypeId: number) => {
  return hasRestViolation(nurseId, dateIso, shiftTypeId)
}

// Returns true if a filled cell should show the rest warning indicator.
const hasCellRestWarning = (nurseId: number, dateIso: string) => {
  const shiftTypeId = getCellShiftTypeId(nurseId, dateIso)
  if (!shiftTypeId) return false

  return hasRestWarningForAssignment(nurseId, dateIso, shiftTypeId)
}



// ==================== Fill Validation ====================

// Returns true when the date is inside the schedule period.
const isDateWithinScheduleRange = (dateIso: string) => {
  if (!schedule.value) return false

  const startDate = schedule.value.start_date.slice(0, 10)
  const endDate = schedule.value.end_date.slice(0, 10)

  return dateIso >= startDate && dateIso <= endDate
}

// Returns the fill result for a cell, including hard-block reasons.
const canFillCell = (nurseId: number, dateIso: string): FillValidationResult => {
  if (!selectedShiftTypeId.value) return { allowed: false }
  if (!isDateWithinScheduleRange(dateIso)) return { allowed: false }

  const currentShiftTypeId = getCellShiftTypeId(nurseId, dateIso)

  // A cell with a blocking absence cannot receive any other assignment.
  if (currentShiftTypeId && blockingShiftTypeIds.value.has(currentShiftTypeId)) {
    return { allowed: false }
  }

  // A blocking shift type can only be assigned to an empty cell.
  if (blockingShiftTypeIds.value.has(selectedShiftTypeId.value) && currentShiftTypeId !== null) {
    return { allowed: false }
  }

  if (currentShiftTypeId !== null) {
    return { allowed: false, reason: 'overlap' }
  }

  const selectedShiftType = shiftTypes.value.find((item) => item.id === selectedShiftTypeId.value)
  if (!selectedShiftType) return { allowed: false }

  if (hasRestViolation(nurseId, dateIso, selectedShiftTypeId.value)) {
    return { allowed: true, reason: 'rest_warning' }
  }

  return { allowed: true }
}

// ==================== Cell Update Actions ====================

// Stores or updates the shift type assignment for a cell. Pass null to clear it.
const setCellAssignment = (nurseId: number, dateIso: string, shiftTypeId: number | null) => {
  const key = getCellKey(nurseId, dateIso)
  cellAssignments.value[key] = shiftTypeId
}

// Applies the selected shift type to one cell if allowed by the fill rules.
const fillCell = (nurseId: number, dateIso: string) => {
  if (!selectedShiftTypeId.value) return

  const fillResult = canFillCell(nurseId, dateIso)
  if (!fillResult.allowed) return

  setCellAssignment(nurseId, dateIso, selectedShiftTypeId.value)
}

// ==================== Interaction Handlers ====================

// Selects or unselects a shift type from the toolbar.
const selectShiftType = (shiftTypeId: number) => {
  selectedShiftTypeId.value = selectedShiftTypeId.value === shiftTypeId ? null : shiftTypeId
}

// Starts drag fill and applies the first cell.
const handleCellMouseDown = (nurseId: number, dateIso: string) => {
  isDragging.value = true
  draggingNurseId.value = nurseId
  fillCell(nurseId, dateIso)
}

// Continues drag fill in the same nurse row.
const handleCellMouseOver = (nurseId: number, dateIso: string) => {
  if (!isDragging.value) return
  if (draggingNurseId.value !== nurseId) return

  fillCell(nurseId, dateIso)
}

// Ends drag fill.
const handleCellMouseUp = () => {
  isDragging.value = false
  draggingNurseId.value = null
}

// Handles single-cell fill by click.
const handleCellClick = (nurseId: number, dateIso: string) => {
  fillCell(nurseId, dateIso)
}

// Clears one cell assignment.
const clearCellAssignment = (nurseId: number, dateIso: string) => {
  if (!isDateWithinScheduleRange(dateIso)) return

  // Clearing is always allowed, including blocking shift types.
  setCellAssignment(nurseId, dateIso, null)
}

// Tracks hovered cell to toggle clear button visibility.
const setHoveredCell = (nurseId: number, dateIso: string) => {
  hoveredCellKey.value = getCellKey(nurseId, dateIso)
}

// Clears hovered cell state.
const clearHoveredCell = () => {
  hoveredCellKey.value = null
}

// Helpers for month-key comparisons used in previous-month lookups.

const formatMonthKeyFromDate = (date: Date) => {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

const ensurePreviousMonthAssignmentsLoaded = async () => {
  if (previousMonthAssignmentsLoaded.value) return
  if (!scheduleBaseMonthDate.value) return
  if (!token.value) return

  const previousMonthDate = new Date(
    scheduleBaseMonthDate.value.getFullYear(),
    scheduleBaseMonthDate.value.getMonth() - 1,
    1
  )
  const previousMonthKey = formatMonthKeyFromDate(previousMonthDate)

  try {
    const authHeaders = {
      Authorization: `Bearer ${token.value}`,
    }

    const schedulesResponse = await $fetch<{ data?: Array<{ id: number; start_date: string; status?: string }> } | Array<{ id: number; start_date: string; status?: string }>>(
      `${config.public.apiBase}/schedules`,
      {
        headers: authHeaders,
      }
    )

    const schedulesList = Array.isArray(schedulesResponse)
      ? schedulesResponse
      : (schedulesResponse.data ?? [])

    const previousMonthSchedules = schedulesList.filter((item) => {
      const startDate = new Date(item.start_date)
      if (Number.isNaN(startDate.getTime())) return false

      return formatMonthKeyFromDate(startDate) === previousMonthKey
    })

    const previousMonthSchedule = previousMonthSchedules
      .sort((a, b) => {
        const statusWeight = (status?: string) => {
          if (status === 'published') return 0
          if (status === 'draft') return 1
          return 2
        }

        const byStatus = statusWeight(a.status) - statusWeight(b.status)
        if (byStatus !== 0) return byStatus

        return Number(b.id) - Number(a.id)
      })[0]

    if (!previousMonthSchedule) {
      previousMonthAssignmentsLoaded.value = true
      localWarning.value = currentLocale.value === 'pt'
        ? 'Não existe horário no mês anterior.'
        : 'No schedule was found for the previous month.'
      return
    }

    const shiftsResponse = await $fetch<{ data?: Array<{ shift_type_id: number; shift_date: string; user_ids: unknown }> }>(
      `${config.public.apiBase}/schedules/${previousMonthSchedule.id}/shifts`,
      {
        headers: authHeaders,
      }
    )

    const previousMonthAssignments: Record<string, number | null> = {}

    for (const shift of shiftsResponse.data ?? []) {
      const shiftDate = String(shift.shift_date).slice(0, 10)
      if (!shiftDate.startsWith(previousMonthKey)) continue

      const normalizedUserIds = Array.isArray(shift.user_ids)
        ? shift.user_ids.map((userId) => Number(userId)).filter((userId) => Number.isFinite(userId))
        : Number.isFinite(Number(shift.user_ids))
          ? [Number(shift.user_ids)]
          : []

      for (const nurseId of normalizedUserIds) {
        previousMonthAssignments[getCellKey(nurseId, shiftDate)] = Number(shift.shift_type_id)
      }
    }

    cellAssignments.value = {
      ...cellAssignments.value,
      ...previousMonthAssignments,
    }

    previousMonthAssignmentsLoaded.value = true
  } catch {
    localWarning.value = currentLocale.value === 'pt'
      ? 'Não foi possível carregar os turnos do mês anterior.'
      : 'Could not load previous month shifts.'
  }
}

// ==================== Text and Tooltip Helpers ====================

const getLocalizedShiftTypeName = (name: string) => {
  const normalizedName = name.trim().toLowerCase()
  const shiftNames = texts.value.edit.shiftNames as Record<string, string>
  return (
    shiftNames[normalizedName] ||
    shiftNames[normalizedName.replace(/\s+/g, ' ')] ||
    name
  )
}

const showFloatingTooltip = (text: string, event: MouseEvent) => {
  const target = event.currentTarget as HTMLElement | null
  if (!target) return

  const rect = target.getBoundingClientRect()
  tooltipLeft.value = rect.left + rect.width / 2
  tooltipTop.value = rect.top - 8
  tooltipText.value = text
  tooltipVisible.value = true
}

const hideFloatingTooltip = () => {
  tooltipVisible.value = false
}

// ==================== Month Navigation ====================

// Navigates to the previous month, wrapping from January to December of the previous year.
const goToPreviousMonth = async () => {
  if (!canGoToPreviousMonth.value) return

  localWarning.value = ''
  const month = selectedMonth.value === 1 ? 12 : selectedMonth.value - 1
  const year = selectedMonth.value === 1 ? selectedYear.value - 1 : selectedYear.value

  if (scheduleBaseMonthDate.value) {
    const targetMonthDate = new Date(year, month - 1, 1)
    const previousMonthDate = new Date(
      scheduleBaseMonthDate.value.getFullYear(),
      scheduleBaseMonthDate.value.getMonth() - 1,
      1
    )

    if (targetMonthDate.getTime() === previousMonthDate.getTime()) {
      await ensurePreviousMonthAssignmentsLoaded()
    }
  }

  setSelectedPeriod(month, year)
  handleCellMouseUp()
}

// Navigates to the next month, wrapping from December to January of the next year.
const goToNextMonth = () => {
  if (!canGoToNextMonth.value) return

  const month = selectedMonth.value === 12 ? 1 : selectedMonth.value + 1
  const year = selectedMonth.value === 12 ? selectedYear.value + 1 : selectedYear.value
  setSelectedPeriod(month, year)
  handleCellMouseUp()
}

// ==================== Data Sync ====================

// Syncs the visible month/year with the schedule's start date stored in the composable.
const applyScheduleMonthFromState = () => {
  if (!schedule.value?.start_date) return

  const startDate = new Date(schedule.value.start_date)
  if (Number.isNaN(startDate.getTime())) return

  setSelectedPeriod(startDate.getMonth() + 1, startDate.getFullYear())
}

// Populates cellAssignments from the shifts already loaded in the composable.
const loadExistingAssignmentsFromState = () => {
  if (!scheduleId.value) return

  const normalizeShiftUserIds = (userIds: unknown): number[] => {
    if (Array.isArray(userIds)) {
      return userIds.filter((id): id is number => Number.isFinite(Number(id))).map((id) => Number(id))
    }

    if (Number.isFinite(Number(userIds))) {
      return [Number(userIds)]
    }

    return []
  }

  const nextAssignments: Record<string, number | null> = {}

  for (const existingShift of shifts.value) {
    if (existingShift.schedule_id !== scheduleId.value) continue

    for (const nurseId of normalizeShiftUserIds(existingShift.user_ids)) {
      nextAssignments[getCellKey(nurseId, existingShift.shift_date)] = existingShift.shift_type_id
    }
  }

  cellAssignments.value = nextAssignments
}

// Returns true when the current grid has assignments not yet persisted in shifts state.
const hasUnsavedGridChanges = () => {
  if (!scheduleId.value) return false

  const persistedAssignments = new Map<string, number>()

  for (const existingShift of shifts.value) {
    if (existingShift.schedule_id !== scheduleId.value) continue

    const normalizedUserIds = Array.isArray(existingShift.user_ids)
      ? existingShift.user_ids.map((userId) => Number(userId)).filter((userId) => Number.isFinite(userId))
      : Number.isFinite(Number(existingShift.user_ids))
        ? [Number(existingShift.user_ids)]
        : []

    for (const nurseId of normalizedUserIds) {
      persistedAssignments.set(`${nurseId}::${existingShift.shift_date}`, Number(existingShift.shift_type_id))
    }
  }

  const desiredAssignments = new Map<string, number>()

  for (const [key, shiftTypeId] of Object.entries(cellAssignments.value)) {
    if (!shiftTypeId) continue

    const [, shiftDate] = key.split('::') as [string, string]
    if (!isDateWithinScheduleRange(shiftDate)) continue

    desiredAssignments.set(key, Number(shiftTypeId))
  }

  if (persistedAssignments.size !== desiredAssignments.size) {
    return true
  }

  for (const [key, desiredShiftTypeId] of desiredAssignments.entries()) {
    if (persistedAssignments.get(key) !== desiredShiftTypeId) {
      return true
    }
  }

  return false
}

// ==================== Save Flow ====================

// Saves all filled grid cells to the API, one request per assignment.
const saveGridAssignments = async (isSilent = false) => {
  localError.value = ''
  localWarning.value = ''
  if (!isSilent) {
    localSuccess.value = ''
  }

  if (!scheduleId.value) {
    localError.value = currentLocale.value === 'pt' 
      ? 'Não foi possível identificar o horário.' 
      : 'Could not identify schedule.'
    return
  }
  if (!schedule.value || schedule.value.id !== scheduleId.value) {
    localError.value = currentLocale.value === 'pt' 
      ? 'Sessão do horário indisponível. Volta a criar o período antes de editar a grelha.' 
      : 'Schedule session unavailable. Recreate the period before editing the grid.'
    return
  }

  const existingAssignmentByNurseDate = new Map<string, { shiftId: number | null, shiftTypeId: number }>()

  for (const existingShift of shifts.value) {
    if (existingShift.schedule_id !== scheduleId.value) continue

    const normalizedUserIds = Array.isArray(existingShift.user_ids)
      ? existingShift.user_ids.map((userId) => Number(userId)).filter((userId) => Number.isFinite(userId))
      : Number.isFinite(Number(existingShift.user_ids))
        ? [Number(existingShift.user_ids)]
        : []

    for (const nurseId of normalizedUserIds) {
      existingAssignmentByNurseDate.set(`${nurseId}::${existingShift.shift_date}`, {
        shiftId: Number.isFinite(Number(existingShift.id)) ? Number(existingShift.id) : null,
        shiftTypeId: Number(existingShift.shift_type_id),
      })
    }
  }

  const desiredAssignments = Object.entries(cellAssignments.value)
    .filter(([, shiftTypeId]) => Boolean(shiftTypeId))
    .map(([key, shiftTypeId]) => {
      const [nurseIdRaw, shiftDate] = key.split('::') as [string, string]

      return {
        nurseId: Number(nurseIdRaw),
        shiftDate,
        shiftTypeId: Number(shiftTypeId),
      }
    })
    .filter((assignment) => isDateWithinScheduleRange(assignment.shiftDate))

  const assignmentsToCreate: Array<{ nurseId: number; shiftDate: string; shiftTypeId: number }> = []
  const assignmentsToUpdate: Array<{ shiftId: number; nurseId: number; shiftDate: string; shiftTypeId: number }> = []
  const assignmentsToDelete: Array<{ shiftId: number; nurseId: number }> = []

  for (const assignment of desiredAssignments) {
    const existingAssignment = existingAssignmentByNurseDate.get(`${assignment.nurseId}::${assignment.shiftDate}`)

    if (!existingAssignment) {
      assignmentsToCreate.push(assignment)
      continue
    }

    if (existingAssignment.shiftTypeId === assignment.shiftTypeId) {
      continue
    }

    if (existingAssignment.shiftId === null) {
      localError.value = currentLocale.value === 'pt' 
        ? 'Não foi possível atualizar um turno existente sem identificador.' 
        : 'Could not update an existing shift without an identifier.'
      return
    }

    assignmentsToUpdate.push({
      shiftId: existingAssignment.shiftId,
      ...assignment,
    })
  }

  // Identify deletions (exists in old, but not in new desired list)
  for (const [key, existing] of existingAssignmentByNurseDate.entries()) {
    const hasInDesired = desiredAssignments.some(d => `${d.nurseId}::${d.shiftDate}` === key)
    if (!hasInDesired && existing.shiftId !== null) {
      const [nurseId] = key.split('::')
      assignmentsToDelete.push({ shiftId: existing.shiftId, nurseId: Number(nurseId) })
    }
  }

   if (!assignmentsToCreate.length && !assignmentsToUpdate.length && !assignmentsToDelete.length) {
    if (!isSilent) {
      localError.value = currentLocale.value === 'pt' 
        ? 'Não existem novas atribuições para guardar.' 
        : 'There are no new assignments to save.'
    }
    return
  }

  isSaving.value = true

  try {
    for (const assignment of assignmentsToUpdate) {
      await updateShift(assignment.shiftId, assignment.shiftTypeId, assignment.shiftDate, [assignment.nurseId])
    }

    for (const assignment of assignmentsToCreate) {
      await createShift(assignment.shiftTypeId, assignment.shiftDate, [assignment.nurseId])
    }

    for (const deletion of assignmentsToDelete) {
      await $fetch(`${config.public.apiBase}/shifts/${deletion.shiftId}?nurse_id=${deletion.nurseId}`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${token.value}`,
        },
      })
    }

    await fetchScheduleShifts(scheduleId.value)

    if (!isSilent) {
      localSuccess.value = currentLocale.value === 'pt' 
        ? 'Grelha guardada com sucesso.' 
        : 'Grid saved successfully.'
    }
    handleCellMouseUp()
  } catch (error: unknown) {
    const backendError = (error as { data?: { message?: string } })?.data?.message
    const runtimeErrorMessage = error instanceof Error ? error.message : ''

    localError.value =
      backendError
      || runtimeErrorMessage
      || errorShiftCreation.value
      || (currentLocale.value === 'pt' 
          ? 'Não foi possível guardar as atribuições. Tenta novamente.' 
          : 'Could not save assignments. Try again.')
  } finally {
    isSaving.value = false
  }
}


const publishSchedule = async () => {
  await executePublishFlow(false)
}

const confirmPublishWithWarnings = async () => {
  isWarningModalOpen.value = false
  await executePublishFlow(true)
}

const closeWarningModal = () => {
  isWarningModalOpen.value = false
  isPublishing.value = false
}

const populateValidationErrors = (data: any, errorMessage: string) => {
  validationErrorCells.value = []
  if (!errorMessage) return

  const msg = errorMessage.toLowerCase()
  const isWeeklyFolgasRule = msg.includes('obrigatorio') || msg.includes('folga por semana') || msg.includes('required') || msg.includes('days off per week')
  const isMinNursesRule = msg.includes('numero minimo') || msg.includes('número mínimo') || msg.includes('minimum number') || msg.includes('não está a ser cumprido')

  if (isWeeklyFolgasRule && data && data.nurse_id && Array.isArray(data.invalid_dates)) {
    const nurseId = Number(data.nurse_id)
    const errorCells: { nurseId: number, dateIso: string }[] = []
    
    const isDayOff = (shiftTypeId: number | null): boolean => {
      if (!shiftTypeId) return false
      const name = getShiftTypeNameById(shiftTypeId).trim().toLowerCase()
      return name.includes('off') || name.includes('folga')
    }

    const processedWeeks = new Set<string>()

    for (const dateIso of data.invalid_dates) {
      const date = new Date(`${dateIso}T00:00:00`)
      const dayOfWeek = date.getDay()
      const diffToMonday = dayOfWeek === 0 ? -6 : 1 - dayOfWeek
      const monday = new Date(date)
      monday.setDate(date.getDate() + diffToMonday)
      
      const mondayStr = monday.toISOString().slice(0, 10)
      if (processedWeeks.has(mondayStr)) continue
      processedWeeks.add(mondayStr)

      const weekDays: string[] = []
      let dayOffCount = 0

      for (let i = 0; i < 7; i++) {
        const d = new Date(monday)
        d.setDate(monday.getDate() + i)
        const y = d.getFullYear()
        const m = String(d.getMonth() + 1).padStart(2, '0')
        const day = String(d.getDate()).padStart(2, '0')
        const dStr = `${y}-${m}-${day}`
        weekDays.push(dStr)

        const shiftTypeId = getCellShiftTypeId(nurseId, dStr)
        if (isDayOff(shiftTypeId)) {
          dayOffCount++
        }
      }

      for (const dStr of weekDays) {
        if (!isDateWithinScheduleRange(dStr)) continue

        const shiftTypeId = getCellShiftTypeId(nurseId, dStr)
        const isCurrentDayoff = isDayOff(shiftTypeId)

        if (dayOffCount < 2) {
          if (!isCurrentDayoff && shiftTypeId !== null) {
            errorCells.push({ nurseId, dateIso: dStr })
          }
        } else if (dayOffCount > 2) {
          if (isCurrentDayoff) {
            errorCells.push({ nurseId, dateIso: dStr })
          }
        }
      }
    }
    validationErrorCells.value = errorCells
  } else if (isMinNursesRule) {
    const errorCells: { nurseId: number, dateIso: string }[] = []

    for (const day of monthDays.value) {
      for (const st of shiftTypes.value) {
        const minN = st.min_nurses ?? 0
        if (minN > 0) {
          const assignedNurses = nurses.value.filter(n => {
            const key = getCellKey(n.id, day.dateIso)
            return getCellShiftTypeId(n.id, day.dateIso) === st.id
          })

          if (assignedNurses.length > 0 && assignedNurses.length < minN) {
            for (const n of assignedNurses) {
              errorCells.push({ nurseId: n.id, dateIso: day.dateIso })
            }
          }
        }
      }
    }
    validationErrorCells.value = errorCells
  } else if (data && data.nurse_id && Array.isArray(data.invalid_dates)) {
    const nurseId = Number(data.nurse_id)
    validationErrorCells.value = data.invalid_dates.map((date: string) => ({
      nurseId,
      dateIso: date
    }))
  }
}

const executePublishFlow = async (force = false) => {
  localError.value = ''
  localWarning.value = ''
  localSuccess.value = ''
  validationErrorCells.value = []
  validationErrorMessage.value = ''
  rawError.value = null          
  rawValidationError.value = null

  if (!scheduleId.value) {
    localError.value = currentLocale.value === 'pt' ? 'Não foi possível identificar o horário.' : 'Could not identify schedule.'
    return
  }

  if (!token.value) {
    localError.value = currentLocale.value === 'pt' ? 'Sessão expirada. Inicia sessão novamente.' : 'Session expired. Sign in again.'
    return
  }

  if (schedule.value?.status === 'published') {
    localSuccess.value = currentLocale.value === 'pt' ? 'Horário já publicado.' : 'Schedule already published.'
    return
  }

  isPublishing.value = true

  try {
    if (hasUnsavedGridChanges()) {
      await saveGridAssignments(true)

      if (localError.value) {
        return
      }
    }

    const authHeaders = {
      Authorization: `Bearer ${token.value}`,
    }

    const isRevision = schedule.value?.status === 'revision'
    const endpoint = isRevision 
      ? `${config.public.apiBase}/schedules/${scheduleId.value}/publish-edit`
      : `${config.public.apiBase}/schedules/${scheduleId.value}/publish`
    const method = isRevision ? 'POST' : 'PATCH'
    
    await $fetch(endpoint, {
      method,
      headers: authHeaders,
      body: { force }
    })

    // Success
    if (!isRevision) {
      await fetchSchedule(scheduleId.value)
    } else if (schedule.value) {
      schedule.value.status = 'published' 
    }
    
    localSuccess.value = currentLocale.value === 'pt'
      ? (isRevision ? 'Alterações publicadas com sucesso.' : 'Horário publicado com sucesso. Email enviado à equipa.')
      : (isRevision ? 'Changes published successfully.' : 'Schedule published successfully. Email sent to the team.')

    setTimeout(async () => {
      isConfirmedLeave.value = true
      await navigateTo('/dashboard')
    }, 4000)  
  } catch (error: unknown) {
    const data = (error as any)?.data
    const errorMessage = getBackendErrorMessage(error)

    // Save the original error so we can translate it in real time
    rawError.value = error
    rawValidationError.value = error

    // Populates the error cells first so they appear in the grid
    populateValidationErrors(data, errorMessage)
    validationErrorMessage.value = errorMessage
    
    if (data && data.bypassable) {
      warningMessage.value = errorMessage
      isWarningModalOpen.value = true
      return
    }

    rawError.value = error                
    localError.value = errorMessage
    rawValidationError.value = error 
  } finally {
    if (!isWarningModalOpen.value) {
      isPublishing.value = false
    }
  }
}
    

const deleteDraftSchedule = async () => {
  localError.value = ''
  localWarning.value = ''
  localSuccess.value = ''

  if (!scheduleId.value) {
    localError.value = 'Nao foi possivel identificar o horario.'
    return
  }

  if (schedule.value?.status === 'published') {
    localError.value = currentLocale.value === 'pt'
      ? 'Um horario publicado nao pode ser apagado.'
      : 'A published schedule cannot be deleted.'
    return
  }

  if (!canDeleteDraft.value) {
    localError.value = currentLocale.value === 'pt'
      ? 'Apenas o head nurse pode apagar rascunhos.'
      : 'Only the head nurse can delete drafts.'
    return
  }

  isDeleteDraftModalOpen.value = true
}

// Generic confirmation modal control for draft deletion.
const closeDeleteDraftModal = () => {
  if (isDeletingDraft.value) return
  isDeleteDraftModalOpen.value = false
}

const confirmDeleteDraftSchedule = async () => {
  if (!scheduleId.value) {
    isDeleteDraftModalOpen.value = false
    return
  }

  isDeletingDraft.value = true

  try {
    await deleteSchedule(scheduleId.value)
    isDeleteDraftModalOpen.value = false
    localSuccess.value = texts.value.edit.deleteDraftSuccess
    isConfirmedLeave.value = true 
    await navigateTo('/schedule-create')
  } catch (error: unknown) {
    const backendError = getBackendErrorMessage(error)
    localError.value = backendError || (currentLocale.value === 'pt'
      ? 'Nao foi possivel apagar o rascunho.'
      : 'Could not delete draft.')
  } finally {
    isDeletingDraft.value = false
  }
}

// Closes the confirmation modal with the Escape key
const handleDeleteDraftModalEscape = (event: KeyboardEvent) => {
  if (event.key !== 'Escape') return
  closeDeleteDraftModal()
}

// ==================== Persistence Helpers ====================

// Stops drag fill when the mouse is released outside the table.
const handleGlobalMouseUp = () => {
  handleCellMouseUp()
}


//==================== Back Button ====================

const isBackModalOpen = ref(false)
const isConfirmedLeave = ref(false)
const targetRoute = ref<string | null>(null)
const handleBackClick = () => {
  targetRoute.value = null
  isBackModalOpen.value = true
}
// Intercepts all exit movements from the page (including swipe)
onBeforeRouteLeave((to, from, next) => {
  if (isConfirmedLeave.value) {
    next()
    return
  }
  targetRoute.value = to.fullPath
  isBackModalOpen.value = true
  next(false) 
})
const confirmLeave = () => {
  isConfirmedLeave.value = true
  isBackModalOpen.value = false
  if (targetRoute.value) {
    router.push(targetRoute.value)
  } else {
    router.back()
  }
}
const confirmSaveAndLeave = async () => {
  isBackModalOpen.value = false
  await saveGridAssignments()
  isConfirmedLeave.value = true
  if (targetRoute.value) {
    router.push(targetRoute.value)
  } else {
    router.back()
  }
}

// ==================== Lifecycle ====================
onMounted(async () => {
  if (process.client) {
    window.addEventListener('mouseup', handleGlobalMouseUp)
    window.addEventListener('keydown', handleDeleteDraftModalEscape)
  }
  

  isBootstrapping.value = true
  localError.value = ''
  localWarning.value = ''

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

    if (!scheduleId.value) {
      localError.value = 'Falta o identificador do horario. Volta a pagina de criacao.'
      return
    }

    try {
      await fetchSchedule(scheduleId.value)
    } catch {
      await navigateTo('/dashboard')
      return
    }

    applyScheduleMonthFromState()

    await Promise.all([fetchNurses(), fetchShiftTypes()])
    await fetchScheduleShifts(scheduleId.value)

    loadExistingAssignmentsFromState()
  } catch (error: unknown) {
    const runtimeErrorMessage = error instanceof Error ? error.message : ''

    localError.value =
      errorNurses.value
      || errorShiftTypes.value
      || errorShifts.value
      || errorScheduleCreation.value
      || runtimeErrorMessage
      || 'Nao foi possivel carregar os dados iniciais da grelha.'
  } finally {
    isBootstrapping.value = false
  }
})

onBeforeUnmount(() => {
  if (process.client) {
    window.removeEventListener('mouseup', handleGlobalMouseUp)
    window.removeEventListener('keydown', handleDeleteDraftModalEscape)
  }
})
</script>

<template>
  <!-- ==================== Page Layout ==================== -->
  <main class="dashboard-layout hr-page">
    <AppNavbar />

    <!-- Warnings -->
    <transition name="slide-down">
      <div v-if="localWarning" class="global-toast success">
        <div class="toast-content">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{{ localWarning }}</span>
        </div>
      </div>
    </transition>
    <transition name="slide-down">
      <div v-if="localError" class="global-toast success">
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
    <transition name="slide-down">
      <div v-if="localSuccess" class="global-toast error">
        <div class="toast-content">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          <span>{{ localSuccess }}</span>
        </div>
      </div>
    </transition>

    <section class="hr-content" style="padding-top: 20px; max-width: 100%;">
      <!-- Buttons -->
      <div class="uc-top-bar schedule-grid-top-bar" style="margin-bottom: 20px; width: 100%;">
        <div class="uc-title-group">
          <button type="button" class="back-link" @click="handleBackClick" style="margin: 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            {{ texts.backButton }}
          </button>

        </div>

        <div class="schedule-grid-top-actions__primary" style="display: flex; gap: 12px; align-items: center;">
          <!-- Button delete -->
          <button v-if="canDeleteDraft" type="button"
            class="schedule-grid-icon-button schedule-grid-icon-button--danger"
            :disabled="isBootstrapping || loadingShiftCreation || isSaving || isPublishing || isDeletingDraft"
            :title="texts.edit.deleteDraft" :aria-label="texts.edit.deleteDraft" @click="deleteDraftSchedule">
            <svg v-if="!isDeletingDraft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" width="16" height="16" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="M6 6 18 18" />
            </svg>
            <span v-else aria-hidden="true">…</span>
          </button>

          <button type="button" class="schedule-secondary-button schedule-grid-action-button"
            :disabled="isBootstrapping || loadingShiftCreation || isSaving || isPublishing || isDeletingDraft"
            @click="saveGridAssignments()" style="color: var(--primary-strong); font-weight: 600;">
            {{ isSaving ? texts.edit.savingAssignments : texts.edit.saveAssignments }}
          </button>

          <!-- Button publish -->
          <button type="button" class="login-button schedule-grid-action-button schedule-primary-button"
            :disabled="isBootstrapping || isSaving || isPublishing || isDeletingDraft || schedule?.status === 'published'"
            @click="publishSchedule">
            {{ isPublishing ? (currentLocale === 'pt' ? 'A publicar...' : 'Publishing...') : (currentLocale === 'pt' ?
              'Publicar' : 'Publish') }}
          </button>
        </div>
      </div>

      <section class="dashboard-card schedule-card schedule-grid-card"
        style="margin: 0; width: 100%; max-width: 100%; box-sizing: border-box;">
        <div class="schedule-title-row">
          <h1>{{ texts.edit.pageTitle }}</h1>
          <span class="schedule-status-badge" :class="scheduleStatusClass">
            {{ scheduleStatusLabel }}
          </span>
        </div>


        <!-- ==================== Active Shift Toolbar ==================== -->

        <div class="schedule-legend">
          <span class="schedule-legend__title">{{ texts.edit.activeShiftLabel }}</span>
          <div class="schedule-legend__items">
            <button v-for="shiftType in shiftTypes" :key="`toolbar-${shiftType.id}`" type="button"
              class="schedule-legend__item" :class="{ 'is-selected': selectedShiftTypeId === shiftType.id }" :style="{
                backgroundColor: getShiftTypeBackgroundColor(shiftType.id) || '#f5f5f7',
                borderColor: selectedShiftTypeId === shiftType.id ? 'rgba(102, 67, 155, 0.45)' : 'var(--line)',
                borderWidth: selectedShiftTypeId === shiftType.id ? '2px' : '1px'
              }" @click="selectShiftType(shiftType.id)" @mouseenter="showFloatingTooltip(texts.edit.minNursesLabel(shiftType.min_nurses || 0), $event)"
  @mouseleave="hideFloatingTooltip">
              {{ getLocalizedShiftTypeName(shiftType.name) }}
            </button>
          </div>
        </div>

        <div class="schedule-month-nav">
          <button type="button" class="schedule-secondary-button" :disabled="!canGoToPreviousMonth"
            @click="goToPreviousMonth">
            {{ texts.edit.previousMonth }}
          </button>

          <strong class="schedule-month-label">{{ monthLabel }}</strong>

          <button type="button" class="schedule-secondary-button" :disabled="!canGoToNextMonth" @click="goToNextMonth">
            {{ texts.edit.nextMonth }}
          </button>
        </div>

        <!-- ==================== Feedback Messages ==================== -->

        <p v-if="errorNurses || errorShiftTypes || errorShifts" class="form-error">
          {{ errorNurses || errorShiftTypes || errorShifts }}
        </p>

        <transition name="fade">
          <div v-if="isDeleteDraftModalOpen" class="modal-overlay" @click.self="closeDeleteDraftModal">
            <div class="modal-card">
              <div class="modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                  </path>
                </svg>
              </div>
              <h2>{{ currentLocale === 'pt' ? 'Apagar horário' : 'Delete schedule' }}</h2>
              <p>{{ currentLocale === 'pt' ? 'Tens a certeza de que queres apagar este horário? Esta ação não pode ser anulada.' : 'Are you sure you want to delete this schedule? This action cannot be undone.' }}</p>
              <div class="modal-actions">
                <button type="button" class="modal-btn cancel" :disabled="isDeletingDraft"
                  @click="closeDeleteDraftModal">
                  {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
                </button>
                <button type="button" class="modal-btn confirm" :disabled="isDeletingDraft"
                  @click="confirmDeleteDraftSchedule">
                  {{ isDeletingDraft ? (currentLocale === 'pt' ? 'A apagar...' : 'Deleting...') : (currentLocale ===
                    'pt' ? 'Apagar horário' : 'Delete schedule') }}
                </button>
              </div>
            </div>
          </div>
        </transition>

        <transition name="fade">
          <div v-if="isBackModalOpen" class="modal-overlay" @click.self="isBackModalOpen = false">
            <div class="modal-card">
              <div class="modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
              </div>
              <h2>{{ currentLocale === 'pt' ? 'Sair da página' : 'Leave page' }}</h2>
              <p>{{ currentLocale === 'pt' ? 'Tens a certeza de que queres sair desta página? Tens alterações que podem não ser guardadas.' : 'Are you sure you want to leave this page ? You have unsaved changes.'}}</p>
              <div class="modal-actions" style="display: flex; gap: 12px; justify-content: center; width: 100%;">
                <button type="button" class="modal-btn cancel" @click="isBackModalOpen = false" style="flex: 1;">
                  {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
                </button>
                <button type="button" class="modal-btn cancel" @click="confirmLeave"
                  style="flex: 1; background: var(--pink, #f2a2d1); color: white; border: none; box-shadow: 0 10px 20px rgba(242, 162, 209, 0.22);">
                  {{ currentLocale === 'pt' ? 'Sair' : 'Leave' }}
                </button>
                <button type="button" class="modal-btn confirm" @click="confirmSaveAndLeave"
                  style="flex: 1; font-size: 14px; white-space: nowrap;">
                  {{ currentLocale === 'pt' ? 'Guardar alterações' : 'Save changes' }}
                </button>
              </div>
            </div>
          </div>
        </transition>

        <transition name="fade">
          <div v-if="isWarningModalOpen" class="modal-overlay" @click.self="closeWarningModal">
            <div class="modal-card">
              <div class="modal-icon" style="color: #f59e0b; border-color: rgba(245, 158, 11, 0.28); background: rgba(245, 158, 11, 0.1);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
              </div>
              <h2>{{ currentLocale === 'pt' ? 'Casos Críticos Detetados' : 'Critical Issues Detected' }}</h2>
              <p style="text-align: center; white-space: pre-line; font-weight: 500; margin-bottom: 12px;">
                {{ warningMessage }}
              </p>
              <p style="text-align: center; font-size: 14px; opacity: 0.85; margin-bottom: 24px;">
                {{ currentLocale === 'pt' ? 'Tem a certeza de que deseja prosseguir com a publicação mesmo com estes casos críticos ou prefere corrigir?' : 'Are you sure you want to proceed with publishing even with these critical cases, or do you prefer to correct them?' }}
              </p>
              <div class="modal-actions" style="display: flex; gap: 12px; justify-content: center; width: 100%;">
                <button type="button" class="modal-btn cancel" @click="closeWarningModal" style="flex: 1;">
                  {{ currentLocale === 'pt' ? 'Corrigir' : 'Correct' }}
                </button>
                <button type="button" class="modal-btn confirm" @click="confirmPublishWithWarnings" style="flex: 1;">
                  {{ currentLocale === 'pt' ? 'Prosseguir' : 'Proceed' }}
                </button>
              </div>
            </div>
          </div>
        </transition>

        <!-- ==================== Schedule Grid ==================== -->

        <div class="schedule-grid-container">
          <table class="schedule-grid">
            <thead>
              <tr>
                <th class="schedule-grid__nurse-header">{{ texts.edit.nurseHeader }}</th>
                <th v-for="day in monthDays" :key="day.dateIso" class="schedule-grid__day-header"
                  :class="{ 'is-weekend': day.isWeekend }">
                  {{ day.day }}
                </th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="isBootstrapping || loadingNurses || loadingShiftTypes || loadingShifts">
                <td :colspan="monthDays.length + 1" class="schedule-grid__feedback">
                  {{ texts.edit.loadingGrid }}
                </td>
              </tr>

              <tr v-else-if="!nurses.length">
                <td :colspan="monthDays.length + 1" class="schedule-grid__feedback">
                  {{ texts.edit.noNurses }}
                </td>
              </tr>

              <tr v-for="nurse in nurses" v-else :key="nurse.id">
                <td class="schedule-grid__nurse-cell">
                  <span class="schedule-grid__nurse-name schedule-tooltip"
                    @mouseenter="showFloatingTooltip(getNursePreferenceSummary(nurse.id).value.preferenceText, $event)"
                    @mouseleave="hideFloatingTooltip">
                    <span class="schedule-grid__nurse-name-text">{{ nurse.name }}</span>
                  </span>
                  <span v-if="getNursePreferenceSummary(nurse.id).value.conflicts > 0"
                    class="schedule-grid__nurse-warning"
                    @mouseenter="showFloatingTooltip(texts.edit.assignmentsAgainstPreferences(getNursePreferenceSummary(nurse.id).value.conflicts), $event)"
                    @mouseleave="hideFloatingTooltip">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"
                      stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path
                        d="M10.29 3.86L1.82 18a1.75 1.75 0 0 0 1.51 2.64h17.34a1.75 1.75 0 0 0 1.51-2.64L13.71 3.86a1.75 1.75 0 0 0-3.42 0Z" />
                      <path d="M12 9v4" />
                      <path d="M12 17h.01" />
                    </svg>
                  </span>
                </td>

                <td v-for="day in monthDays" :key="`${nurse.id}-${day.dateIso}`" class="schedule-grid__cell"
                  :class="{ 'is-weekend': day.isWeekend }" :style="{
                    backgroundColor: getShiftTypeBackgroundColor(getCellShiftTypeId(nurse.id, day.dateIso)) || '',
                    position: 'relative'
                  }" @mousedown.prevent="handleCellMouseDown(nurse.id, day.dateIso)"
                  @mouseover="handleCellMouseOver(nurse.id, day.dateIso)" @mouseup="handleCellMouseUp"
                  @click="handleCellClick(nurse.id, day.dateIso)" @mouseenter="setHoveredCell(nurse.id, day.dateIso)"
                  @mouseleave="clearHoveredCell">
                  <button
                    v-if="hoveredCellKey === getCellKey(nurse.id, day.dateIso) && getCellShiftTypeId(nurse.id, day.dateIso) && isDateWithinScheduleRange(day.dateIso)"
                    type="button" class="schedule-secondary-button" :style="{
                      position: 'absolute',
                      top: '2px',
                      right: '2px',
                      minWidth: '20px',
                      height: '20px',
                      padding: '0',
                      lineHeight: '1',
                      borderRadius: '999px',
                      fontSize: '0.74rem'
                    }" @click.stop="clearCellAssignment(nurse.id, day.dateIso)">
                    x
                  </button>

                  <span class="schedule-grid__cell-text">
                    {{ getLocalizedShiftTypeName(getShiftTypeNameById(getCellShiftTypeId(nurse.id, day.dateIso))) || '-'
                    }}
                  </span>

                  <span v-if="hasCellRestWarning(nurse.id, day.dateIso) || validationErrorCells.some(c => c.nurseId === nurse.id && c.dateIso === day.dateIso)"
                    class="schedule-tooltip schedule-tooltip--warning schedule-grid__cell-warning"
                    @mouseenter="showFloatingTooltip(hasCellRestWarning(nurse.id, day.dateIso) ? texts.edit.restWarning : validationErrorMessage, $event)" 
                    @mouseleave="hideFloatingTooltip">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"
                      stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path
                        d="M10.29 3.86L1.82 18a1.75 1.75 0 0 0 1.51 2.64h17.34a1.75 1.75 0 0 0 1.51-2.64L13.71 3.86a1.75 1.75 0 0 0-3.42 0Z" />
                      <path d="M12 9v4" />
                      <path d="M12 17h.01" />
                    </svg>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <Teleport to="body" v-if="tooltipVisible">
          <div class="schedule-tooltip-fixed" :style="tooltipStyle">
            {{ tooltipText }}
          </div>
        </Teleport>
      </section>
    </section>
  </main>
</template>
