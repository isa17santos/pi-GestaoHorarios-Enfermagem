// Represents a schedule object created by the head nurse
type Schedule = {
  id: number
  start_date: string
  end_date: string
  created_by: number
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
  nurses: Nurse[]
}

// Response shape for fetching a single nurse's preferences
type NursePreferencesResponse = {
  preferences: NursePreference[]
}

// Response shape for fetching shift types
type ShiftTypesResponse = {
  shift_types: ShiftType[]
}

// Response shape when creating a schedule
type CreateScheduleResponse = {
  message: string
  schedule: Schedule
}

// Response shape when creating a shift
type CreateShiftResponse = {
  message: string
  shift: Shift
}

export const useSchedule = () => {
  const config = useRuntimeConfig()
  const { token } = useAuth()

  // ==================== STATE ====================

  // The current schedule being created/edited
  const schedule = useState<Schedule | null>('schedule.current', () => null)

  // Array of shifts for the current schedule
  const shifts = useState<Shift[]>('schedule.shifts', () => [])

  // List of available nurses fetched from the API
  const nurses = useState<Nurse[]>('schedule.nurses', () => [])

  // List of available shift types fetched from the API
  const shiftTypes = useState<ShiftType[]>('schedule.shiftTypes', () => [])

  // Currently selected month (1-12)
  const selectedMonth = useState<number>('schedule.selectedMonth', () => new Date().getMonth() + 1)

  // Currently selected year
  const selectedYear = useState<number>('schedule.selectedYear', () => new Date().getFullYear())

  // ==================== LOADING STATES ====================

  const loadingNurses = useState<boolean>('schedule.loadingNurses', () => false)
  const loadingShiftTypes = useState<boolean>('schedule.loadingShiftTypes', () => false)
  const loadingScheduleCreation = useState<boolean>('schedule.loadingCreation', () => false)
  const loadingShiftCreation = useState<boolean>('schedule.loadingShiftCreation', () => false)

  // ==================== ERROR STATES ====================

  const errorNurses = useState<string | null>('schedule.errorNurses', () => null)
  const errorShiftTypes = useState<string | null>('schedule.errorShiftTypes', () => null)
  const errorScheduleCreation = useState<string | null>('schedule.errorCreation', () => null)
  const errorShiftCreation = useState<string | null>('schedule.errorShiftCreation', () => null)

  // ==================== COMPUTED ====================

  // Check if any operation is currently loading
  const isLoading = computed(() =>
    loadingNurses.value ||
    loadingShiftTypes.value ||
    loadingScheduleCreation.value ||
    loadingShiftCreation.value
  )

  // Check if there are any errors present
  const hasErrors = computed(() =>
    Boolean(errorNurses.value || errorShiftTypes.value || errorScheduleCreation.value || errorShiftCreation.value)
  )

  const requireAuthToken = () => {
    if (!token.value) {
      throw new Error('Authentication required. Please sign in again.')
    }

    return token.value
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
        nursesResponse.nurses.map(async (nurse) => {
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
              preferences: preferencesResponse.preferences,
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

      shiftTypes.value = response.shift_types
      return response.shift_types
    } catch (error) {
      errorShiftTypes.value = error instanceof Error ? error.message : 'Failed to fetch shift types'
      console.error('Error fetching shift types:', error)
      throw error
    } finally {
      loadingShiftTypes.value = false
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

      schedule.value = response.schedule
      shifts.value = [] // Reset shifts for the new schedule
      return response.schedule
    } catch (error) {
      errorScheduleCreation.value = error instanceof Error ? error.message : 'Failed to create schedule'
      console.error('Error creating schedule:', error)
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
      shifts.value.push(response.shift)
      return response.shift
    } catch (error) {
      errorShiftCreation.value = error instanceof Error ? error.message : 'Failed to create shift'
      console.error('Error creating shift:', error)
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
    errorNurses.value = null
    errorShiftTypes.value = null
    errorScheduleCreation.value = null
    errorShiftCreation.value = null
  }

  return {
    // State
    schedule,
    shifts,
    nurses,
    shiftTypes,
    selectedMonth,
    selectedYear,

    // Loading states
    loadingNurses,
    loadingShiftTypes,
    loadingScheduleCreation,
    loadingShiftCreation,
    isLoading,

    // Error states
    errorNurses,
    errorShiftTypes,
    errorScheduleCreation,
    errorShiftCreation,
    hasErrors,

    // Functions
    fetchNurses,
    fetchShiftTypes,
    createSchedule,
    createShift,
    setSelectedPeriod,
    clearScheduleState,
  }
}
