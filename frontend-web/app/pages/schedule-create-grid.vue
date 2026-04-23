<script setup lang="ts">
import type { CSSProperties } from 'vue'

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
  const backendError = (error as { data?: { message?: string }, message?: string })?.data?.message
  if (typeof backendError === 'string' && backendError.trim().length > 0) {
    return backendError.trim()
  }

  const runtimeError = (error as { message?: string })?.message
  return typeof runtimeError === 'string' ? runtimeError.trim() : ''
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

// Returns true when assigning a shift would violate the 11h rest rule with adjacent days.
const hasRestViolation = (nurseId: number, dateIso: string, shiftTypeId: number): boolean => {
  if (blockingShiftTypeIds.value.has(shiftTypeId)) return false

  const previousDateIso = getRelativeDateIso(dateIso, -1)
  if (previousDateIso) {
    const previousShiftTypeId = getCellShiftTypeId(nurseId, previousDateIso)

    if (previousShiftTypeId && !blockingShiftTypeIds.value.has(previousShiftTypeId)) {
      const previousShiftType = shiftTypes.value.find((item) => item.id === previousShiftTypeId)
      const currentShiftType = shiftTypes.value.find((item) => item.id === shiftTypeId)

      if (previousShiftType && currentShiftType) {
        const gap = restGapAfterPreviousShift(previousShiftType, currentShiftType)
        if (gap !== null && gap < 11 * 60) {
          return true
        }
      }
    }
  }

  const nextDateIso = getRelativeDateIso(dateIso, 1)
  if (nextDateIso) {
    const nextShiftTypeId = getCellShiftTypeId(nurseId, nextDateIso)

    if (nextShiftTypeId && !blockingShiftTypeIds.value.has(nextShiftTypeId)) {
      const currentShiftType = shiftTypes.value.find((item) => item.id === shiftTypeId)
      const nextShiftType = shiftTypes.value.find((item) => item.id === nextShiftTypeId)

      if (currentShiftType && nextShiftType) {
        const gap = restGapAfterPreviousShift(currentShiftType, nextShiftType)
        if (gap !== null && gap < 11 * 60) {
          return true
        }
      }
    }
  }

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
const saveGridAssignments = async () => {
  localError.value = ''
  localWarning.value = ''
  localSuccess.value = ''

  if (!scheduleId.value) {
    localError.value = 'Nao foi possivel identificar o horario.'
    return
  }

  if (!schedule.value || schedule.value.id !== scheduleId.value) {
    localError.value = 'Sessao do horario indisponivel. Volta a criar o periodo antes de editar a grelha.'
    return
  }

  // Groups existing shift assignments by nurse/date to decide between create and update.
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
    // Keeps previous month cells read-only by saving only dates inside the schedule range.
    .filter((assignment) => isDateWithinScheduleRange(assignment.shiftDate))

  const assignmentsToCreate: Array<{ nurseId: number; shiftDate: string; shiftTypeId: number }> = []
  const assignmentsToUpdate: Array<{ shiftId: number; nurseId: number; shiftDate: string; shiftTypeId: number }> = []

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
      localError.value = 'Nao foi possivel atualizar um turno existente sem identificador.'
      return
    }

    assignmentsToUpdate.push({
      shiftId: existingAssignment.shiftId,
      ...assignment,
    })
  }

  if (!assignmentsToCreate.length && !assignmentsToUpdate.length) {
    localError.value = 'Nao existem novas atribuicoes para guardar.'
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

    localSuccess.value = 'Grelha guardada com sucesso.'
    handleCellMouseUp()
  } catch (error: unknown) {
    // Uses backend validation message when available.
    const backendError = (error as { data?: { message?: string } })?.data?.message
    const runtimeErrorMessage = error instanceof Error ? error.message : ''

    localError.value =
      backendError
      || runtimeErrorMessage
      ||
      errorShiftCreation.value
      || 'Nao foi possivel guardar as atribuicoes. Tenta novamente.'
  } finally {
    isSaving.value = false
  }
}

const publishSchedule = async () => {
  localError.value = ''
  localWarning.value = ''
  localSuccess.value = ''

  if (!scheduleId.value) {
    localError.value = 'Nao foi possivel identificar o horario.'
    return
  }

  if (!token.value) {
    localError.value = 'Sessao expirada. Inicia sessao novamente.'
    return
  }

  if (schedule.value?.status === 'published') {
    localSuccess.value = currentLocale.value === 'pt' ? 'Horario ja publicado.' : 'Schedule already published.'
    return
  }

  isPublishing.value = true

  try {
    if (hasUnsavedGridChanges()) {
      await saveGridAssignments()

      if (localError.value) {
        return
      }
    }

    const authHeaders = {
      Authorization: `Bearer ${token.value}`,
    }

    const publishAttempts = [
      () => $fetch(`${config.public.apiBase}/schedules/${scheduleId.value}/publish`, {
        method: 'POST',
        headers: authHeaders,
      }),
      () => $fetch(`${config.public.apiBase}/schedules/${scheduleId.value}/publish`, {
        method: 'PATCH',
        headers: authHeaders,
      }),
      () => $fetch(`${config.public.apiBase}/schedules/${scheduleId.value}`, {
        method: 'PATCH',
        headers: authHeaders,
        body: { status: 'published' },
      }),
    ]

    let publishSucceeded = false
    let lastPublishError: unknown = null
    for (const attempt of publishAttempts) {
      try {
        await attempt()
        publishSucceeded = true
        break
      } catch (error: unknown) {
        lastPublishError = error
        if (!shouldTryNextPublishEndpoint(error)) {
          throw error
        }
      }
    }

    if (!publishSucceeded) {
      throw lastPublishError || new Error('Nao foi possivel publicar o horario.')
    }

    await fetchSchedule(scheduleId.value)
    localSuccess.value = currentLocale.value === 'pt' ? 'Horario publicado com sucesso.' : 'Schedule published successfully.'
  } catch (error: unknown) {
    const statusCode = (error as { statusCode?: number, status?: number })?.statusCode ?? (error as { status?: number })?.status
    const backendError = getBackendErrorMessage(error)

    if (statusCode === 422 && backendError) {
      localWarning.value = backendError
      return
    }

    if (backendError) {
      localError.value = backendError
      return
    }

    localError.value = isNetworkPublishError(error)
      ? (currentLocale.value === 'pt' ? 'Erro de rede ao publicar o horario. Verifica a ligacao e tenta novamente.' : 'Network error while publishing schedule. Check your connection and try again.')
      : (currentLocale.value === 'pt' ? 'Ocorreu um erro inesperado ao publicar o horario.' : 'An unexpected error occurred while publishing schedule.')
  } finally {
    isPublishing.value = false
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

// Fecha o modal de confirmação com a tecla Escape.
const handleDeleteDraftModalEscape = (event: KeyboardEvent) => {
  if (event.key !== 'Escape') return
  closeDeleteDraftModal()
}

// ==================== Persistence Helpers ====================

// Stops drag fill when the mouse is released outside the table.
const handleGlobalMouseUp = () => {
  handleCellMouseUp()
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
  <main class="dashboard-page schedule-page schedule-grid-page">
    <section class="dashboard-card schedule-card schedule-grid-card">
      <button class="language-switch" type="button" @click="toggleLanguage">
        <span class="language-switch__flag">{{ localeFlag }}</span>
        <span>{{ localeLabel }}</span>
      </button>

      <p class="eyebrow">{{ texts.edit.pageEyebrow }}</p>
      <div class="schedule-title-row">
        <h1>{{ texts.edit.pageTitle }}</h1>
        <span class="schedule-status-badge" :class="scheduleStatusClass">
          {{ scheduleStatusLabel }}
        </span>
      </div>

      <!-- ==================== Header Actions ==================== -->

      <div class="schedule-grid-top-actions">
        <button type="button" class="schedule-secondary-button" @click="() => router.back()">
          {{ texts.backButton }}
        </button>

        <div class="schedule-grid-top-actions__primary">
          <button
            v-if="canDeleteDraft"
            type="button"
            class="schedule-grid-icon-button schedule-grid-icon-button--danger"
            :disabled="isBootstrapping || loadingShiftCreation || isSaving || isPublishing || isDeletingDraft"
            :title="texts.edit.deleteDraft"
            :aria-label="texts.edit.deleteDraft"
            @click="deleteDraftSchedule"
          >
            <svg v-if="!isDeletingDraft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="16" height="16" aria-hidden="true">
              <path d="M18 6 6 18" />
              <path d="M6 6 18 18" />
            </svg>
            <span v-else aria-hidden="true">…</span>
          </button>

          <button
            type="button"
            class="schedule-secondary-button schedule-grid-action-button"
            :disabled="isBootstrapping || loadingShiftCreation || isSaving || isPublishing || isDeletingDraft"
            @click="saveGridAssignments"
          >
            {{ isSaving ? texts.edit.savingAssignments : texts.edit.saveAssignments }}
          </button>

          <button
            type="button"
            class="login-button schedule-grid-action-button schedule-primary-button"
            :disabled="isBootstrapping || isSaving || isPublishing || isDeletingDraft || schedule?.status === 'published'"
            @click="publishSchedule"
          >
            {{ isPublishing ? (currentLocale === 'pt' ? 'A publicar...' : 'Publishing...') : (currentLocale === 'pt' ? 'Publicar' : 'Publish') }}
          </button>
        </div>
      </div>

      <p class="schedule-intro">
        {{ texts.edit.pageSubtitle }}
      </p>

      <!-- ==================== Active Shift Toolbar ==================== -->

      <div class="schedule-legend">
        <span class="schedule-legend__title">{{ texts.edit.activeShiftLabel }}</span>
        <div class="schedule-legend__items">
          <button
            v-for="shiftType in shiftTypes"
            :key="`toolbar-${shiftType.id}`"
            type="button"
            class="schedule-legend__item"
            :class="{ 'is-selected': selectedShiftTypeId === shiftType.id }"
            :style="{
              backgroundColor: getShiftTypeBackgroundColor(shiftType.id) || '#f5f5f7',
              borderColor: selectedShiftTypeId === shiftType.id ? 'rgba(102, 67, 155, 0.45)' : 'var(--line)',
              borderWidth: selectedShiftTypeId === shiftType.id ? '2px' : '1px'
            }"
            @click="selectShiftType(shiftType.id)"
          >
            {{ getLocalizedShiftTypeName(shiftType.name) }}
          </button>
        </div>
      </div>

      <div class="schedule-month-nav">
        <button
          type="button"
          class="schedule-secondary-button"
          :disabled="!canGoToPreviousMonth"
          @click="goToPreviousMonth"
        >
          {{ texts.edit.previousMonth }}
        </button>

        <strong class="schedule-month-label">{{ monthLabel }}</strong>

        <button
          type="button"
          class="schedule-secondary-button"
          :disabled="!canGoToNextMonth"
          @click="goToNextMonth"
        >
          {{ texts.edit.nextMonth }}
        </button>
      </div>

      <!-- ==================== Feedback Messages ==================== -->

      <p v-if="localWarning" class="form-warning">
        {{ localWarning }}
      </p>

      <p v-if="localError" class="form-error">
        {{ localError }}
      </p>

      <p v-if="localSuccess" class="form-success">
        {{ localSuccess }}
      </p>

      <p v-if="errorNurses || errorShiftTypes || errorShifts" class="form-error">
        {{ errorNurses || errorShiftTypes || errorShifts }}
      </p>

      <div
        v-if="isDeleteDraftModalOpen"
        class="schedule-confirm-overlay"
        role="presentation"
        @click.self="closeDeleteDraftModal"
      >
        <!-- Generic confirmation modal used for destructive draft actions. -->
        <div class="schedule-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="delete-draft-grid-title">
          <h3 id="delete-draft-grid-title">
            {{ texts.edit.deleteDraft }}
          </h3>

          <p>{{ texts.edit.deleteDraftConfirmation }}</p>

          <div class="schedule-confirm-actions">
            <button
              type="button"
              class="schedule-secondary-button"
              :disabled="isDeletingDraft"
              @click="closeDeleteDraftModal"
            >
              {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
            </button>

            <button
              type="button"
              class="login-button schedule-danger-button"
              :disabled="isDeletingDraft"
              @click="confirmDeleteDraftSchedule"
            >
              {{ isDeletingDraft ? texts.edit.deletingDraft : texts.edit.deleteDraft }}
            </button>
          </div>
        </div>
      </div>

      <!-- ==================== Schedule Grid ==================== -->

      <div class="schedule-grid-container">
        <table class="schedule-grid">
          <thead>
            <tr>
              <th class="schedule-grid__nurse-header">{{ texts.edit.nurseHeader }}</th>
              <th
                v-for="day in monthDays"
                :key="day.dateIso"
                class="schedule-grid__day-header"
                :class="{ 'is-weekend': day.isWeekend }"
              >
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
                <span
                  class="schedule-grid__nurse-name schedule-tooltip"
                  @mouseenter="showFloatingTooltip(getNursePreferenceSummary(nurse.id).value.preferenceText, $event)"
                  @mouseleave="hideFloatingTooltip"
                >
                  <span class="schedule-grid__nurse-name-text">{{ nurse.name }}</span>
                </span>
                <span
                  v-if="getNursePreferenceSummary(nurse.id).value.conflicts > 0"
                  class="schedule-grid__nurse-warning"
                  @mouseenter="showFloatingTooltip(texts.edit.assignmentsAgainstPreferences(getNursePreferenceSummary(nurse.id).value.conflicts), $event)"
                  @mouseleave="hideFloatingTooltip"
                >
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.29 3.86L1.82 18a1.75 1.75 0 0 0 1.51 2.64h17.34a1.75 1.75 0 0 0 1.51-2.64L13.71 3.86a1.75 1.75 0 0 0-3.42 0Z" />
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                  </svg>
                </span>
              </td>

              <td
                v-for="day in monthDays"
                :key="`${nurse.id}-${day.dateIso}`"
                class="schedule-grid__cell"
                :class="{ 'is-weekend': day.isWeekend }"
                :style="{
                  backgroundColor: getShiftTypeBackgroundColor(getCellShiftTypeId(nurse.id, day.dateIso)) || '',
                  position: 'relative'
                }"
                @mousedown.prevent="handleCellMouseDown(nurse.id, day.dateIso)"
                @mouseover="handleCellMouseOver(nurse.id, day.dateIso)"
                @mouseup="handleCellMouseUp"
                @click="handleCellClick(nurse.id, day.dateIso)"
                @mouseenter="setHoveredCell(nurse.id, day.dateIso)"
                @mouseleave="clearHoveredCell"
              >
                <button
                  v-if="hoveredCellKey === getCellKey(nurse.id, day.dateIso) && getCellShiftTypeId(nurse.id, day.dateIso) && isDateWithinScheduleRange(day.dateIso)"
                  type="button"
                  class="schedule-secondary-button"
                  :style="{
                    position: 'absolute',
                    top: '2px',
                    right: '2px',
                    minWidth: '20px',
                    height: '20px',
                    padding: '0',
                    lineHeight: '1',
                    borderRadius: '999px',
                    fontSize: '0.74rem'
                  }"
                  @click.stop="clearCellAssignment(nurse.id, day.dateIso)"
                >
                  x
                </button>

                <span class="schedule-grid__cell-text">
                  {{ getLocalizedShiftTypeName(getShiftTypeNameById(getCellShiftTypeId(nurse.id, day.dateIso))) || '-' }}
                </span>

                <span
                  v-if="hasCellRestWarning(nurse.id, day.dateIso)"
                  class="schedule-tooltip schedule-tooltip--warning schedule-grid__cell-warning"
                  @mouseenter="showFloatingTooltip(texts.edit.restWarning, $event)"
                  @mouseleave="hideFloatingTooltip"
                >
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M10.29 3.86L1.82 18a1.75 1.75 0 0 0 1.51 2.64h17.34a1.75 1.75 0 0 0 1.51-2.64L13.71 3.86a1.75 1.75 0 0 0-3.42 0Z" />
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
        <div
          class="schedule-tooltip-fixed"
          :style="tooltipStyle"
        >
          {{ tooltipText }}
        </div>
      </Teleport>
    </section>
  </main>
</template>
