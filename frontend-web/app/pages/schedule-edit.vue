<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

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

// Returns true if the current user is allowed to create/edit schedules.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

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

// In-memory map of cell assignments: "nurseId::YYYY-MM-DD" -> shiftTypeId.
const cellAssignments = ref<Record<string, number | null>>({})

// Reads and validates the schedule id from the URL query string.
const scheduleId = computed(() => {
  const rawValue = route.query.scheduleId
  const parsed = Number(Array.isArray(rawValue) ? rawValue[0] : rawValue)
  return Number.isFinite(parsed) ? parsed : null
})

// Returns the current month and year as a localised string, e.g. "abril de 2026".
const monthLabel = computed(() => {
  const date = new Date(selectedYear.value, selectedMonth.value - 1, 1)
  return new Intl.DateTimeFormat('pt-PT', { month: 'long', year: 'numeric' }).format(date)
})

// Returns an array of day objects for the current month, used to build the grid columns.
const monthDays = computed(() => {
  const year = selectedYear.value
  const month = selectedMonth.value
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

// Returns a unique key for a grid cell in the format "nurseId::YYYY-MM-DD".
const getCellKey = (nurseId: number, dateIso: string) => `${nurseId}::${dateIso}`

// Returns the shift type id assigned to a cell, or null if unassigned.
const getCellShiftTypeId = (nurseId: number, dateIso: string) => {
  return cellAssignments.value[getCellKey(nurseId, dateIso)] ?? null
}

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

// Returns true when the date is inside the schedule period.
const isDateWithinScheduleRange = (dateIso: string) => {
  if (!schedule.value) return false

  const startDate = schedule.value.start_date.slice(0, 10)
  const endDate = schedule.value.end_date.slice(0, 10)

  return dateIso >= startDate && dateIso <= endDate
}

// Returns true if a cell can receive a new assignment.
const canFillCell = (nurseId: number, dateIso: string) => {
  if (!selectedShiftTypeId.value) return false
  if (!isDateWithinScheduleRange(dateIso)) return false

  const currentShiftTypeId = getCellShiftTypeId(nurseId, dateIso)
  return currentShiftTypeId === null
}

// Stores or updates the shift type assignment for a cell. Pass null to clear it.
const setCellAssignment = (nurseId: number, dateIso: string, shiftTypeId: number | null) => {
  const key = getCellKey(nurseId, dateIso)
  cellAssignments.value[key] = shiftTypeId
}

// Applies the selected shift type to one cell if allowed by the fill rules.
const fillCell = (nurseId: number, dateIso: string) => {
  if (!selectedShiftTypeId.value) return
  if (!canFillCell(nurseId, dateIso)) return

  setCellAssignment(nurseId, dateIso, selectedShiftTypeId.value)
}

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
  <main class="dashboard-page schedule-page schedule-grid-page">
    <section class="dashboard-card schedule-card schedule-grid-card">
      <p class="eyebrow">Edicao de horario</p>
      <h1>Horário mensal</h1>

      <div class="schedule-grid-top-actions">
        <button type="button" class="schedule-secondary-button" @click="navigateTo('/dashboard')">
          Voltar
        </button>

        <button
          type="button"
          class="login-button"
          :disabled="isBootstrapping || loadingShiftCreation || isSaving"
          @click="saveGridAssignments"
        >
          {{ isSaving ? 'A guardar...' : 'Guardar atribuições' }}
        </button>
      </div>

      <p class="schedule-intro">
        Seleciona o tipo de turno em cada celula para atribuir o enfermeiro nesse dia.
      </p>

      <div class="schedule-legend">
        <span class="schedule-legend__title">Turno ativo:</span>
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
            {{ shiftType.name }}
          </button>
        </div>
      </div>

      <div class="schedule-month-nav">
        <button type="button" class="schedule-secondary-button" @click="goToPreviousMonth">
          Mes anterior
        </button>

        <strong class="schedule-month-label">{{ monthLabel }}</strong>

        <button type="button" class="schedule-secondary-button" @click="goToNextMonth">
          Mes seguinte
        </button>
      </div>

      <p v-if="localError" class="form-error">
        {{ localError }}
      </p>

      <p v-if="localSuccess" class="form-success">
        {{ localSuccess }}
      </p>

      <p v-if="errorNurses || errorShiftTypes" class="form-error">
        {{ errorNurses || errorShiftTypes }}
      </p>

      <div class="schedule-grid-container">
        <table class="schedule-grid">
          <thead>
            <tr>
              <th class="schedule-grid__nurse-header">Enfermeiro</th>
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
                A carregar grelha...
              </td>
            </tr>

            <tr v-else-if="!nurses.length">
              <td :colspan="monthDays.length + 1" class="schedule-grid__feedback">
                Nao existem enfermeiros para mostrar.
              </td>
            </tr>

            <tr v-for="nurse in nurses" v-else :key="nurse.id">
              <td class="schedule-grid__nurse-cell">{{ nurse.name }}</td>

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
                  {{ getShiftTypeNameById(getCellShiftTypeId(nurse.id, day.dateIso)) || '-' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>
