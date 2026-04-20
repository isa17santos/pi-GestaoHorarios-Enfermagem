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
  loadingShiftCreation,
  errorNurses,
  errorShiftTypes,
  errorShiftCreation,
  fetchNurses,
  fetchShiftTypes,
  createShift,
  setSelectedPeriod,
} = useSchedule()

// Pull auth state to enforce role-based access.
const { user, fetchMe } = useAuth()
const route = useRoute()

// Use shared schedule texts composable
const { currentLocale, texts, toggleLanguage, localeLabel, localeFlag } = useScheduleTexts()

// ==================== Access Control ====================

// Returns true if the current user is allowed to create/edit schedules.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

// ==================== Local UI State ====================

// Local feedback messages shown to the user.
const localError = ref('')
const localSuccess = ref('')

// True while the page is loading its initial data.
const isBootstrapping = ref(true)

// True while the save operation is in progress.
const isSaving = ref(false)

// Currently selected shift type in the toolbar.
const selectedShiftTypeId = ref<number | null>(null)

// True while drag fill is active.
const isDragging = ref(false)

// Nurse row currently being dragged.
const draggingNurseId = ref<number | null>(null)

// Cell currently hovered, used to show the clear button.
const hoveredCellKey = ref<string | null>(null)

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
const goToPreviousMonth = () => {
  const month = selectedMonth.value === 1 ? 12 : selectedMonth.value - 1
  const year = selectedMonth.value === 1 ? selectedYear.value - 1 : selectedYear.value
  setSelectedPeriod(month, year)
  handleCellMouseUp()
}

// Navigates to the next month, wrapping from December to January of the next year.
const goToNextMonth = () => {
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

  const nextAssignments: Record<string, number | null> = {}

  for (const existingShift of shifts.value) {
    if (existingShift.schedule_id !== scheduleId.value) continue

    for (const nurseId of existingShift.user_ids) {
      nextAssignments[getCellKey(nurseId, existingShift.shift_date)] = existingShift.shift_type_id
    }
  }

  cellAssignments.value = nextAssignments
}

// ==================== Save Flow ====================

// Saves all filled grid cells to the API, one request per assignment.
const saveGridAssignments = async () => {
  localError.value = ''
  localSuccess.value = ''

  if (!scheduleId.value) {
    localError.value = 'Nao foi possivel identificar o horario.'
    return
  }

  if (!schedule.value || schedule.value.id !== scheduleId.value) {
    localError.value = 'Sessao do horario indisponivel. Volta a criar o periodo antes de editar a grelha.'
    return
  }

  // Tracks assignments already persisted to avoid duplicate POST requests.
  const existingAssignments = new Set<string>()

  for (const existingShift of shifts.value) {
    if (existingShift.schedule_id !== scheduleId.value) continue

    for (const nurseId of existingShift.user_ids) {
      existingAssignments.add(`${nurseId}::${existingShift.shift_date}::${existingShift.shift_type_id}`)
    }
  }

  const assignmentsToSave = Object.entries(cellAssignments.value)
    .filter(([, shiftTypeId]) => Boolean(shiftTypeId))
    .map(([key, shiftTypeId]) => {
      const [nurseIdRaw, shiftDate] = key.split('::') as [string, string]

      return {
        nurseId: Number(nurseIdRaw),
        shiftDate,
        shiftTypeId: Number(shiftTypeId),
      }
    })
    // Sends only new assignments that are not already stored in shifts state.
    .filter((assignment) => !existingAssignments.has(
      `${assignment.nurseId}::${assignment.shiftDate}::${assignment.shiftTypeId}`
    ))

  if (!assignmentsToSave.length) {
    localError.value = 'Nao existem novas atribuicoes para guardar.'
    return
  }

  isSaving.value = true

  try {
    for (const assignment of assignmentsToSave) {
      await createShift(assignment.shiftTypeId, assignment.shiftDate, [assignment.nurseId])
    }

    localSuccess.value = 'Grelha guardada com sucesso.'
    handleCellMouseUp()
  } catch (error: unknown) {
    // Uses backend validation message when available.
    const backendError = (error as { data?: { message?: string } })?.data?.message

    localError.value =
      backendError
      ||
      errorShiftCreation.value
      || 'Nao foi possivel guardar as atribuicoes. Tenta novamente.'
  } finally {
    isSaving.value = false
  }
}

// ==================== Persistence Helpers ====================

// Stops drag fill when the mouse is released outside the table.
const handleGlobalMouseUp = () => {
  handleCellMouseUp()
}

// Restores the current schedule from localStorage when it is missing or stale in memory.
const tryRestoreScheduleFromStorage = (expectedScheduleId: number): boolean => {
  if (!process.client) return false

  const rawSchedule = localStorage.getItem('schedule.current')
  if (!rawSchedule) return false

  try {
    const parsedSchedule = JSON.parse(rawSchedule) as { id?: number }

    if (parsedSchedule.id !== expectedScheduleId) {
      return false
    }

    schedule.value = parsedSchedule as typeof schedule.value
    return true
  } catch {
    return false
  }
}

// ==================== Lifecycle ====================

onMounted(async () => {
  if (process.client) {
    window.addEventListener('mouseup', handleGlobalMouseUp)
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

    if (!scheduleId.value) {
      localError.value = 'Falta o identificador do horario. Volta a pagina de criacao.'
      return
    }

    if (!schedule.value || schedule.value.id !== scheduleId.value) {
      const restored = tryRestoreScheduleFromStorage(scheduleId.value)

      if (!restored || !schedule.value || schedule.value.id !== scheduleId.value) {
        localError.value = 'Sessao do horario indisponivel. Volta a criar o periodo antes de editar a grelha.'
        await navigateTo('/dashboard?error=schedule_unavailable')
        return
      }
    }

    applyScheduleMonthFromState()

    await Promise.all([fetchNurses(), fetchShiftTypes()])

    loadExistingAssignmentsFromState()
  } catch {
    localError.value =
      errorNurses.value
      || errorShiftTypes.value
      || 'Nao foi possivel carregar os dados iniciais da grelha.'
  } finally {
    isBootstrapping.value = false
  }
})

onBeforeUnmount(() => {
  if (process.client) {
    window.removeEventListener('mouseup', handleGlobalMouseUp)
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
      <h1>{{ texts.edit.pageTitle }}</h1>

      <!-- ==================== Header Actions ==================== -->

      <div class="schedule-grid-top-actions">
        <button type="button" class="schedule-secondary-button" @click="navigateTo('/dashboard')">
          {{ texts.backButton }}
        </button>

        <button
          type="button"
          class="login-button"
          :disabled="isBootstrapping || loadingShiftCreation || isSaving"
          @click="saveGridAssignments"
        >
          {{ isSaving ? texts.edit.savingAssignments : texts.edit.saveAssignments }}
        </button>
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
            :style="{
              backgroundColor: getShiftTypeBackgroundColor(shiftType.id) || '#f5f5f7',
              borderColor: selectedShiftTypeId === shiftType.id ? '#0f172a' : 'var(--line)',
              borderWidth: selectedShiftTypeId === shiftType.id ? '2px' : '1px'
            }"
            @click="selectShiftType(shiftType.id)"
          >
            {{ getLocalizedShiftTypeName(shiftType.name) }}
          </button>
        </div>
      </div>

      <div class="schedule-month-nav">
        <button type="button" class="schedule-secondary-button" @click="goToPreviousMonth">
          {{ texts.edit.previousMonth }}
        </button>

        <strong class="schedule-month-label">{{ monthLabel }}</strong>

        <button type="button" class="schedule-secondary-button" @click="goToNextMonth">
          {{ texts.edit.nextMonth }}
        </button>
      </div>

      <!-- ==================== Feedback Messages ==================== -->

      <p v-if="localError" class="form-error">
        {{ localError }}
      </p>

      <p v-if="localSuccess" class="form-success">
        {{ localSuccess }}
      </p>

      <p v-if="errorNurses || errorShiftTypes" class="form-error">
        {{ errorNurses || errorShiftTypes }}
      </p>

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
            <tr v-if="isBootstrapping || loadingNurses || loadingShiftTypes">
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
                  v-if="hoveredCellKey === getCellKey(nurse.id, day.dateIso) && getCellShiftTypeId(nurse.id, day.dateIso)"
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

