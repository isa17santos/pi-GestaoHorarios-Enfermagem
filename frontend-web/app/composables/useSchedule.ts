// Represents a schedule object created by the head nurse
type Schedule = {
  id: number
  start_date: string
  end_date: string
  created_by: number
  status: 'draft' | 'published'
}

// Represents a single shift within a schedule
type Shift = {
  id?: number
  schedule_id: number
  shift_type_id: number
  shift_date: string
  user_ids: number[]
}

// Represents a nurse/user in the system with their preferences
type Nurse = {
  id: number
  name: string
  email: string
  role: string
  preferences?: NursePreference[]
}

// Represents schedule preferences stored for a nurse
type NursePreference = {
  id: number
  user_id: number
  schedule_id: number
  prefers_morning: boolean
  prefers_afternoon: boolean
  prefers_night: boolean
  avoid_weekends: boolean
  prefers_weekends: boolean
  notes: string | null
}

// Represents the available shift types in the system
type ShiftType = {
  id: number
  name: string
  start_time: string
  end_time: string
}

// Response shape for fetching multiple nurses
type NursesResponse = {
  data: Nurse[]
}

// Response shape for fetching a single nurse's preferences
type NursePreferencesResponse = {
  data: NursePreference[]
}

// Response shape for fetching shift types
type ShiftTypesResponse = {
  data: ShiftType[]
}

// Response shape when creating a schedule
type CreateScheduleResponse = {
  message: string
  data: Schedule
}

// Response shape when creating a shift
type CreateShiftResponse = {
  message: string
  data: Shift
}

// Response shape for fetching multiple shifts
type ShiftsResponse = {
  data: Shift[]
}

// Response shape for fetching a single schedule
type ScheduleResponse = {
  data: Schedule
}

// Response shape for fetching multiple schedules
type SchedulesResponse = {
  data: Schedule[]
}

export const useSchedule = () => {
  const config = useRuntimeConfig()
  const { token, user } = useAuth()

  // ==================== STATE ====================

  // The current schedule being created/edited
  const schedule = useState<Schedule | null>('schedule.current', () => {
    if (process.client) {
      const rawSchedule = localStorage.getItem('schedule.current')
      if (rawSchedule) {
        try {
          return JSON.parse(rawSchedule) as Schedule
        } catch {
          localStorage.removeItem('schedule.current')
        }
      }
    }

    return null
  })

  // Array of shifts for the current schedule
  const shifts = useState<Shift[]>('schedule.shifts', () => {
    if (process.client) {
      const rawShifts = localStorage.getItem('schedule.shifts')
      if (rawShifts) {
        try {
          const parsedShifts = JSON.parse(rawShifts) as Shift[]
          return Array.isArray(parsedShifts) ? parsedShifts : []
        } catch {
          localStorage.removeItem('schedule.shifts')
        }
      }
    }

    return []
  })

  // List of available nurses fetched from the API
  const nurses = useState<Nurse[]>('schedule.nurses', () => [])

  // List of available shift types fetched from the API
  const shiftTypes = useState<ShiftType[]>('schedule.shiftTypes', () => [])

  // List of schedules available to the current user
  const schedules = useState<Schedule[]>('schedule.list', () => [])

  // Currently selected month (1-12)
  const selectedMonth = useState<number>('schedule.selectedMonth', () => new Date().getMonth() + 1)

  // Currently selected year
  const selectedYear = useState<number>('schedule.selectedYear', () => new Date().getFullYear())

  // ==================== LOADING STATES ====================

  const loadingNurses = useState<boolean>('schedule.loadingNurses', () => false)
  const loadingShiftTypes = useState<boolean>('schedule.loadingShiftTypes', () => false)
  const loadingSchedules = useState<boolean>('schedule.loadingSchedules', () => false)
  const loadingShifts = useState<boolean>('schedule.loadingShifts', () => false)
  const loadingScheduleCreation = useState<boolean>('schedule.loadingCreation', () => false)
  const loadingShiftCreation = useState<boolean>('schedule.loadingShiftCreation', () => false)

  // ==================== ERROR STATES ====================

  const errorNurses = useState<string | null>('schedule.errorNurses', () => null)
  const errorShiftTypes = useState<string | null>('schedule.errorShiftTypes', () => null)
  const errorSchedules = useState<string | null>('schedule.errorSchedules', () => null)
  const errorShifts = useState<string | null>('schedule.errorShifts', () => null)
  const errorScheduleCreation = useState<string | null>('schedule.errorCreation', () => null)
  const errorShiftCreation = useState<string | null>('schedule.errorShiftCreation', () => null)

  // ==================== COMPUTED ====================

  // Check if any operation is currently loading
  const isLoading = computed(() =>
    loadingNurses.value ||
    loadingShiftTypes.value ||
    loadingSchedules.value ||
    loadingShifts.value ||
    loadingScheduleCreation.value ||
    loadingShiftCreation.value
  )

  // Check if there are any errors present
  const hasErrors = computed(() =>
    Boolean(
      errorNurses.value
      || errorShiftTypes.value
      || errorSchedules.value
      || errorShifts.value
      || errorScheduleCreation.value
      || errorShiftCreation.value
    )
  )

  const requireAuthToken = () => {
    if (!token.value) {
      throw new Error('Authentication required. Please sign in again.')
    }

    return token.value
  }

  const isHeadNurseRole = () => {
    const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
    return normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
  }

  const extractApiErrorMessage = (error: unknown, fallback: string) => {
    const apiError = error as {
      data?: {
        message?: string
        error?: string
        errors?: Record<string, string[] | string>
      }
      statusMessage?: string
      message?: string
    }

    if (apiError?.data?.message) return apiError.data.message
    if (apiError?.data?.error) return apiError.data.error

    const errorEntries = apiError?.data?.errors
    if (errorEntries && typeof errorEntries === 'object') {
      const firstErrorValue = Object.values(errorEntries)[0]
      if (Array.isArray(firstErrorValue) && firstErrorValue.length > 0) {
        return firstErrorValue[0] || fallback
      }

      if (typeof firstErrorValue === 'string' && firstErrorValue.trim()) {
        return firstErrorValue
      }
    }

    if (apiError?.statusMessage) return apiError.statusMessage
    return fallback
  }

  const persistShifts = () => {
    if (!process.client) return
    localStorage.setItem('schedule.shifts', JSON.stringify(shifts.value))
  }

  // ==================== FUNCTIONS ====================

  /**
   * Fetch all nurses from the API and their preferences
   */
  const fetchNurses = async () => {
    loadingNurses.value = true
    errorNurses.value = null

    try {
      const authToken = requireAuthToken()

      // Fetch the list of all nurses
      const nursesResponse = await $fetch<NursesResponse>(
        `${config.public.apiBase}/users`,
        {
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
        }
      )

      // Fetch preferences for each nurse
      const nursesWithPreferences = await Promise.all(
        nursesResponse.data.map(async (nurse) => {
          try {
            const preferencesResponse = await $fetch<NursePreferencesResponse>(
              `${config.public.apiBase}/users/${nurse.id}/preferences`,
              {
                headers: {
                  Authorization: `Bearer ${authToken}`,
                },
              }
            )

            return {
              ...nurse,
              preferences: preferencesResponse.data,
            }
          } catch (error) {
            // If preferences fail for a specific nurse, continue without them
            console.warn(`Failed to fetch preferences for nurse ${nurse.id}:`, error)
            return nurse
          }
        })
      )

      nurses.value = nursesWithPreferences
      return nursesWithPreferences
    } catch (error) {
      errorNurses.value = error instanceof Error ? error.message : 'Failed to fetch nurses'
      console.error('Error fetching nurses:', error)
      throw error
    } finally {
      loadingNurses.value = false
    }
  }

  /**
   * Fetch all schedules visible to the authenticated user.
   */
  const fetchSchedules = async () => {
    loadingSchedules.value = true
    errorSchedules.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<SchedulesResponse | Schedule[]>(
        `${config.public.apiBase}/schedules`,
        {
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
        }
      )

      const fetchedSchedules = Array.isArray(response)
        ? response
        : (response.data ?? [])

      schedules.value = fetchedSchedules
      return fetchedSchedules
    } catch (error) {
      errorSchedules.value = extractApiErrorMessage(error, 'Failed to fetch schedules')
      console.error('Error fetching schedules:', error)
      throw error
    } finally {
      loadingSchedules.value = false
    }
  }

  /**
   * Fetch shifts for a specific schedule from /schedules/{id}/shifts.
   */
  const fetchScheduleShifts = async (scheduleId: number) => {
    loadingShifts.value = true
    errorShifts.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<ShiftsResponse>(
        `${config.public.apiBase}/schedules/${scheduleId}/shifts`,
        {
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
        }
      )

      const normalizedShifts = (response.data ?? []).map((shift) => ({
        id: shift.id,
        schedule_id: scheduleId,
        shift_type_id: Number(shift.shift_type_id),
        shift_date: String(shift.shift_date).slice(0, 10),
        user_ids: Array.isArray(shift.user_ids)
          ? shift.user_ids.map((userId) => Number(userId)).filter((userId) => Number.isFinite(userId))
          : [],
      }))

      shifts.value = normalizedShifts
      persistShifts()
      return normalizedShifts
    } catch (error) {
      errorShifts.value = extractApiErrorMessage(error, 'Failed to fetch schedule shifts')
      console.error('Error fetching schedule shifts:', error)
      throw error
    } finally {
      loadingShifts.value = false
    }
  }

  /**
   * Fetch shifts from backend and optionally filter by schedule id.
   * Tries dedicated schedule endpoint first, then query endpoint, then full list.
   */
  const fetchShifts = async (scheduleId?: number) => {
    loadingShifts.value = true
    errorShifts.value = null

    const toFiniteNumber = (value: unknown): number | null => {
      const parsed = Number(value)
      return Number.isFinite(parsed) ? parsed : null
    }

    const normalizeUserIds = (rawUserIds: unknown): number[] => {
      if (Array.isArray(rawUserIds)) {
        return rawUserIds.flatMap((item) => {
          if (typeof item === 'object' && item !== null) {
            const objectItem = item as { id?: unknown; user_id?: unknown }
            const nestedId = toFiniteNumber(objectItem.id ?? objectItem.user_id)
            return nestedId !== null ? [nestedId] : []
          }

          const scalarId = toFiniteNumber(item)
          return scalarId !== null ? [scalarId] : []
        })
      }

      if (typeof rawUserIds === 'object' && rawUserIds !== null) {
        const objectUser = rawUserIds as { id?: unknown; user_id?: unknown }
        const nestedId = toFiniteNumber(objectUser.id ?? objectUser.user_id)
        if (nestedId !== null) {
          return [nestedId]
        }
      }

      const singleId = toFiniteNumber(rawUserIds)
      if (singleId !== null) {
        return [singleId]
      }

      return []
    }

    const normalizeShift = (rawShift: unknown): Shift | null => {
      const shift = rawShift as {
        id?: unknown
        schedule_id?: unknown
        scheduleId?: unknown
        schedule?: { id?: unknown }
        shift_type_id?: unknown
        shiftTypeId?: unknown
        shift_type?: { id?: unknown }
        shift_date?: unknown
        shiftDate?: unknown
        date?: unknown
        assigned_date?: unknown
        user_ids?: unknown
        userIds?: unknown
        users?: unknown
        nurses?: unknown
        user?: unknown
        user_id?: unknown
        nurse_id?: unknown
      }

      const normalizedScheduleId = toFiniteNumber(
        shift.schedule_id
        ?? shift.scheduleId
        ?? shift.schedule?.id
      )

      const normalizedShiftTypeId = toFiniteNumber(
        shift.shift_type_id
        ?? shift.shiftTypeId
        ?? shift.shift_type?.id
      )

      const normalizedShiftDateRaw = typeof shift.shift_date === 'string'
        ? shift.shift_date
        : typeof shift.shiftDate === 'string'
          ? shift.shiftDate
          : typeof shift.date === 'string'
            ? shift.date
            : typeof shift.assigned_date === 'string'
              ? shift.assigned_date
          : ''

      const normalizedShiftDate = normalizedShiftDateRaw.slice(0, 10)

      if (normalizedScheduleId === null || normalizedShiftTypeId === null || !normalizedShiftDate) {
        return null
      }

      const normalizedId = toFiniteNumber(shift.id)

      return {
        id: normalizedId ?? undefined,
        schedule_id: normalizedScheduleId,
        shift_type_id: normalizedShiftTypeId,
        shift_date: normalizedShiftDate,
        user_ids: normalizeUserIds(
          shift.user_ids
          ?? shift.userIds
          ?? shift.users
          ?? shift.nurses
          ?? shift.user
          ?? shift.nurse_id
          ?? shift.user_id
        ),
      }
    }

    const extractShiftsList = (payload: unknown): unknown[] => {
      const collected: unknown[] = []
      const visited = new Set<unknown>()

      const isShiftLike = (value: unknown) => {
        if (!value || typeof value !== 'object') return false

        const candidate = value as Record<string, unknown>

        const hasScheduleId = candidate.schedule_id !== undefined
          || candidate.scheduleId !== undefined
          || (candidate.schedule && typeof candidate.schedule === 'object' && (candidate.schedule as Record<string, unknown>).id !== undefined)

        const hasShiftTypeId = candidate.shift_type_id !== undefined
          || candidate.shiftTypeId !== undefined
          || (candidate.shift_type && typeof candidate.shift_type === 'object' && (candidate.shift_type as Record<string, unknown>).id !== undefined)

        const hasDate = candidate.shift_date !== undefined
          || candidate.shiftDate !== undefined
          || candidate.date !== undefined
          || candidate.assigned_date !== undefined

        return hasScheduleId && hasShiftTypeId && hasDate
      }

      const walk = (value: unknown, depth: number) => {
        if (depth > 6) return
        if (!value) return
        if (visited.has(value)) return

        if (typeof value === 'object') {
          visited.add(value)
        }

        if (Array.isArray(value)) {
          for (const item of value) {
            walk(item, depth + 1)
          }
          return
        }

        if (typeof value !== 'object') return

        if (isShiftLike(value)) {
          collected.push(value)
          return
        }

        const record = value as Record<string, unknown>
        const preferredKeys = [
          'data',
          'items',
          'results',
          'shifts',
          'schedule_shifts',
          'scheduleShifts',
          'schedule',
        ]

        for (const key of preferredKeys) {
          if (record[key] !== undefined) {
            walk(record[key], depth + 1)
          }
        }

        for (const valueItem of Object.values(record)) {
          walk(valueItem, depth + 1)
        }
      }

      walk(payload, 0)
      return collected
    }

    try {
      const authToken = requireAuthToken()
      let rawShifts: unknown[] = []

      if (scheduleId) {
        try {
          const response = await $fetch<ShiftsResponse | Shift[]>(
            `${config.public.apiBase}/schedules/${scheduleId}/shifts`,
            {
              headers: {
                Authorization: `Bearer ${authToken}`,
              },
            }
          )

          rawShifts = extractShiftsList(response)
        } catch {
          try {
            const scheduleResponse = await $fetch<{ data?: { shifts?: unknown[] } } | { shifts?: unknown[] }>(
              `${config.public.apiBase}/schedules/${scheduleId}`,
              {
                headers: {
                  Authorization: `Bearer ${authToken}`,
                },
              }
            )

            const responseWithData = scheduleResponse as { data?: { shifts?: unknown[] } }
            const responseWithShifts = scheduleResponse as { shifts?: unknown[] }
            const shiftsFromSchedule = responseWithData.data?.shifts ?? responseWithShifts.shifts

            rawShifts = Array.isArray(shiftsFromSchedule) ? shiftsFromSchedule : []
          } catch {
            const schedulesResponse = await $fetch<SchedulesResponse | Schedule[]>(
              `${config.public.apiBase}/schedules`,
              {
                headers: {
                  Authorization: `Bearer ${authToken}`,
                },
              }
            )

            const schedulesList = Array.isArray(schedulesResponse)
              ? schedulesResponse
              : (schedulesResponse.data ?? [])

            const matchedSchedule = schedulesList.find((item) => item.id === scheduleId) as
              | (Schedule & { shifts?: unknown[] })
              | undefined

            rawShifts = Array.isArray(matchedSchedule?.shifts) ? matchedSchedule.shifts : []
          }
        }
      }

      const normalizedShifts = rawShifts
        .map((item) => normalizeShift(item))
        .filter((item): item is Shift => Boolean(item))

      shifts.value = scheduleId
        ? normalizedShifts.filter((item) => item.schedule_id === scheduleId)
        : normalizedShifts

      persistShifts()

      return shifts.value
    } catch (error) {
      const apiError = error as { status?: number; statusCode?: number }
      const statusCode = apiError.status ?? apiError.statusCode

      if (statusCode === 404 || statusCode === 405) {
        errorShifts.value = null
        return scheduleId
          ? shifts.value.filter((item) => item.schedule_id === scheduleId)
          : shifts.value
      }

      errorShifts.value = extractApiErrorMessage(error, 'Failed to fetch shifts')
      console.error('Error fetching shifts:', error)
      throw error
    } finally {
      loadingShifts.value = false
    }
  }

  /**
   * Fetch all available shift types from the API
   */
  const fetchShiftTypes = async () => {
    loadingShiftTypes.value = true
    errorShiftTypes.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<ShiftTypesResponse>(
        `${config.public.apiBase}/shift-types`,
        {
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
        }
      )

      shiftTypes.value = response.data
      return response.data
    } catch (error) {
      errorShiftTypes.value = error instanceof Error ? error.message : 'Failed to fetch shift types'
      console.error('Error fetching shift types:', error)
      throw error
    } finally {
      loadingShiftTypes.value = false
    }
  }

  /**
   * Fetch a schedule by id.
   * Tries GET /schedules/{id}; falls back to GET /schedules and finds by id.
   */
  const fetchSchedule = async (scheduleId: number) => {
    loadingScheduleCreation.value = true
    errorScheduleCreation.value = null

    try {
      const authToken = requireAuthToken()

      let fetchedSchedule: Schedule | null = null

      try {
        const response = await $fetch<ScheduleResponse>(
          `${config.public.apiBase}/schedules/${scheduleId}`,
          {
            headers: {
              Authorization: `Bearer ${authToken}`,
            },
          }
        )

        fetchedSchedule = Array.isArray(response)
          ? null
          : ('data' in response ? response.data : response)
      } catch {
        const response = await $fetch<SchedulesResponse | Schedule[]>(
          `${config.public.apiBase}/schedules`,
          {
            headers: {
              Authorization: `Bearer ${authToken}`,
            },
          }
        )

        const schedulesList = Array.isArray(response)
          ? response
          : (response.data ?? [])

        fetchedSchedule = schedulesList.find((item) => item.id === scheduleId) ?? null
      }

      if (!fetchedSchedule) {
        throw new Error('Schedule not found')
      }

      schedule.value = fetchedSchedule

      if (process.client) {
        localStorage.setItem('schedule.current', JSON.stringify(fetchedSchedule))
      }

      return fetchedSchedule
    } catch (error) {
      errorScheduleCreation.value = extractApiErrorMessage(error, 'Failed to fetch schedule')
      console.error('Error fetching schedule:', error)
      throw error
    } finally {
      loadingScheduleCreation.value = false
    }
  }

  /**
   * Create a new schedule on the backend
   */
  const createSchedule = async (startDate: string, endDate: string) => {
    loadingScheduleCreation.value = true
    errorScheduleCreation.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<CreateScheduleResponse>(
        `${config.public.apiBase}/schedules`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
          body: {
            start_date: startDate,
            end_date: endDate,
          },
        }
      )

      schedule.value = response.data

      if (process.client) {
        localStorage.setItem('schedule.current', JSON.stringify(response.data))
      }

      shifts.value = [] // Reset shifts for the new schedule
      persistShifts()
      return response.data
    } catch (error) {
      errorScheduleCreation.value = extractApiErrorMessage(error, 'Failed to create schedule')
      console.error('Error creating schedule:', error)
      throw error
    } finally {
      loadingScheduleCreation.value = false
    }
  }

  /**
   * Delete a schedule on the backend and clear local state when it matches the current schedule.
   */
  const deleteSchedule = async (scheduleId: number) => {
    loadingScheduleCreation.value = true
    errorScheduleCreation.value = null

    try {
      if (!isHeadNurseRole()) {
        throw new Error('Only the head nurse can delete draft schedules.')
      }

      const targetSchedule = schedule.value?.id === scheduleId
        ? schedule.value
        : schedules.value.find((item) => item.id === scheduleId) ?? null

      if (targetSchedule?.status === 'published') {
        throw new Error('Published schedules cannot be deleted.')
      }

      const authToken = requireAuthToken()

      await $fetch(`${config.public.apiBase}/schedules/${scheduleId}`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${authToken}`,
        },
      })

      schedules.value = schedules.value.filter((item) => item.id !== scheduleId)

      if (schedule.value?.id === scheduleId) {
        clearScheduleState()
      }
    } catch (error) {
      errorScheduleCreation.value = extractApiErrorMessage(error, 'Failed to delete schedule')
      console.error('Error deleting schedule:', error)
      throw error
    } finally {
      loadingScheduleCreation.value = false
    }
  }

  /**
   * Create a new shift within the current schedule
   */
  const createShift = async (shiftTypeId: number, shiftDate: string, userIds: number[]) => {
    if (!schedule.value) {
      throw new Error('No schedule selected. Please create or select a schedule first.')
    }

    loadingShiftCreation.value = true
    errorShiftCreation.value = null

    try {
      const authToken = requireAuthToken()

      const response = await $fetch<CreateShiftResponse>(
        `${config.public.apiBase}/shifts`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${authToken}`,
          },
          body: {
            schedule_id: schedule.value.id,
            shift_type_id: shiftTypeId,
            shift_date: shiftDate,
            user_ids: userIds,
          },
        }
      )

      // Add the new shift to the shifts array
      shifts.value.push(response.data)
      persistShifts()
      return response.data
    } catch (error) {
      errorShiftCreation.value = error instanceof Error ? error.message : 'Failed to create shift'
      console.error('Error creating shift:', error)
      throw error
    } finally {
      loadingShiftCreation.value = false
    }
  }

  /**
   * Update an existing shift assignment.
   */
  const updateShift = async (shiftId: number, shiftTypeId: number, shiftDate: string, userIds: number[]) => {
    if (!schedule.value) {
      throw new Error('No schedule selected. Please create or select a schedule first.')
    }

    loadingShiftCreation.value = true
    errorShiftCreation.value = null

    try {
      const authToken = requireAuthToken()
      const currentScheduleId = schedule.value.id

      const requestBody = {
        schedule_id: currentScheduleId,
        shift_type_id: shiftTypeId,
        shift_date: shiftDate,
        user_ids: userIds,
      }

      const updateAttempts = [
        () => $fetch<CreateShiftResponse>(
          `${config.public.apiBase}/shifts/${shiftId}`,
          {
            method: 'PATCH',
            headers: {
              Authorization: `Bearer ${authToken}`,
            },
            body: requestBody,
          }
        ),
        () => $fetch<CreateShiftResponse>(
          `${config.public.apiBase}/shifts/${shiftId}`,
          {
            method: 'PUT',
            headers: {
              Authorization: `Bearer ${authToken}`,
            },
            body: requestBody,
          }
        ),
        () => $fetch<CreateShiftResponse>(
          `${config.public.apiBase}/schedules/${currentScheduleId}/shifts/${shiftId}`,
          {
            method: 'PATCH',
            headers: {
              Authorization: `Bearer ${authToken}`,
            },
            body: requestBody,
          }
        ),
        () => $fetch<CreateShiftResponse>(
          `${config.public.apiBase}/schedules/${currentScheduleId}/shifts/${shiftId}`,
          {
            method: 'PUT',
            headers: {
              Authorization: `Bearer ${authToken}`,
            },
            body: requestBody,
          }
        ),
      ]

      let response: CreateShiftResponse | null = null
      let lastError: unknown = null

      for (const attempt of updateAttempts) {
        try {
          response = await attempt()
          break
        } catch (error) {
          lastError = error
        }
      }

      if (!response) {
        const apiError = lastError as { status?: number; statusCode?: number }
        const statusCode = apiError?.status ?? apiError?.statusCode

        if (statusCode === 404 || statusCode === 405) {
          throw new Error('No backend endpoint is available to update existing shifts.')
        }

        throw lastError instanceof Error ? lastError : new Error('Failed to update shift')
      }

      const existingShiftIndex = shifts.value.findIndex((shift) => shift.id === shiftId)
      if (existingShiftIndex >= 0) {
        shifts.value[existingShiftIndex] = {
          ...shifts.value[existingShiftIndex],
          ...response.data,
        }
      }

      persistShifts()
      return response.data
    } catch (error) {
      errorShiftCreation.value = extractApiErrorMessage(error, 'Failed to update shift')
      console.error('Error updating shift:', error)
      throw error
    } finally {
      loadingShiftCreation.value = false
    }
  }

  /**
   * Set the currently selected month and year for the schedule view
   */
  const setSelectedPeriod = (month: number, year: number) => {
    selectedMonth.value = month
    selectedYear.value = year
  }

  /**
   * Clear all schedule state (useful when navigating away)
   */
  const clearScheduleState = () => {
    schedule.value = null
    shifts.value = []
    nurses.value = []
    shiftTypes.value = []
    schedules.value = []
    errorNurses.value = null
    errorShiftTypes.value = null
    errorSchedules.value = null
    errorShifts.value = null
    errorScheduleCreation.value = null
    errorShiftCreation.value = null

    if (process.client) {
      localStorage.removeItem('schedule.current')
      localStorage.removeItem('schedule.shifts')
    }
  }

  return {
    // State
    schedule,
    shifts,
    nurses,
    shiftTypes,
    schedules,
    selectedMonth,
    selectedYear,

    // Loading states
    loadingNurses,
    loadingShiftTypes,
    loadingSchedules,
    loadingShifts,
    loadingScheduleCreation,
    loadingShiftCreation,
    isLoading,

    // Error states
    errorNurses,
    errorShiftTypes,
    errorSchedules,
    errorShifts,
    errorScheduleCreation,
    errorShiftCreation,
    hasErrors,

    // Functions
    fetchNurses,
    fetchSchedules,
    fetchScheduleShifts,
    fetchShifts,
    fetchShiftTypes,
    fetchSchedule,
    createSchedule,
    deleteSchedule,
    createShift,
    updateShift,
    setSelectedPeriod,
    clearScheduleState,
  }
}
