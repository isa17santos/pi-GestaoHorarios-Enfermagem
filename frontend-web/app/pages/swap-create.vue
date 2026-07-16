<script setup lang="ts">
import { nextTick, onBeforeUnmount, watch } from 'vue'

definePageMeta({
  middleware: 'auth',
})

type ShiftOption = {
  id: number
  date: string
  shift_type: {
    id: number
    name: string
    color: string
    start_time: string
    end_time: string
  }
  users: { id: number; name: string }[]
}

type ValidationWarning = {
  nurse_name?: string
  nurse?: {
    name?: string
  }
  message?: string
  text?: string
}

type SwapMode = 'shift' | 'shift_for_dayoff'

const { user } = useAuth()
const { fetchShifts, createSwap, validateShift, errorSwaps } = useSwap()
const { shiftTypes, fetchShiftTypes } = useSchedule()
const route = useRoute()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Below AVAILABLE_LABEL_MAX_WIDTH the "Available" label starts losing trailing characters
// proportionally to the remaining width, down to nothing at AVAILABLE_LABEL_MIN_WIDTH — so the
// day-off calendar cells (which can stack a badge per shift type) shrink gradually instead of
// abruptly, and never grow tall enough to overlap the row below.
const AVAILABLE_LABEL_MAX_WIDTH = 840
const AVAILABLE_LABEL_MIN_WIDTH = 480
const screenWidth = ref(typeof window !== 'undefined' ? window.innerWidth : AVAILABLE_LABEL_MAX_WIDTH)
const updateScreenWidth = () => { screenWidth.value = window.innerWidth }
const isNarrowScreen = computed(() => screenWidth.value <= 640)
onBeforeUnmount(() => window.removeEventListener('resize', updateScreenWidth))
const availableLabel = computed(() => {
  const full = currentLocale.value === 'pt' ? 'Disponíveis' : 'Available'
  if (screenWidth.value >= AVAILABLE_LABEL_MAX_WIDTH) return full

  const range = AVAILABLE_LABEL_MAX_WIDTH - AVAILABLE_LABEL_MIN_WIDTH
  const progress = Math.min(1, Math.max(0, (AVAILABLE_LABEL_MAX_WIDTH - screenWidth.value) / range))
  const visibleChars = Math.round(full.length * (1 - progress))
  return full.slice(0, visibleChars)
})

const swapMode = ref<SwapMode>('shift')
const requestedShiftTypeId = ref<number | null>(null)
const sourceShiftId = ref<number | null>(null)
const offeredShift = ref<ShiftOption | null>(null)
const selectedResults = ref<Set<number>>(new Set())

const myShifts = ref<ShiftOption[]>([])
const availableShifts = ref<ShiftOption[]>([])
const validationWarnings = ref<Record<number, ValidationWarning[]>>({})
const loadingMyShifts = ref(false)
const loadingAvailable = ref(false)
const loadingDayShifts = ref(false)
const submitting = ref(false)
const submitError = ref<string | null>(null)
const submitSuccess = ref(false)
const toast = ref<{ key: string; type: 'success' | 'error' } | null>(null)

const showToast = (key: string, type: 'success' | 'error' = 'success') => {
  toast.value = { key, type }
  setTimeout(() => { toast.value = null }, 4000)
}

const toastTexts = computed(() => ({
  createSuccess: currentLocale.value === 'pt' ? 'Pedido criado com sucesso!' : 'Request created successfully!',
  createError: currentLocale.value === 'pt' ? 'Erro ao criar pedido.' : 'Failed to create request.',
  noShiftFound: currentLocale.value === 'pt' ? 'Turno não encontrado. Tenta novamente.' : 'Shift not found. Please try again.',
}))
const showConfirmModal = ref(false)
const notes = ref('')
const searchQuery = ref('')
const filterShiftTypeId = ref<number | null>(null)
const isShiftTypeFilterOpen = ref(false)

if (process.client) {
  window.addEventListener('click', (e: MouseEvent) => {
    if (!(e.target as HTMLElement).closest('.custom-select-wrapper')) {
      isShiftTypeFilterOpen.value = false
    }
  })
}
const resultsPerPage = 10
const currentPage = ref(1)

// --- Shift-for-dayoff two-step flow state ---
// Step 1: user picks one of their own day-off shifts from the calendar.
// Step 2: people list on the work shift date (always the offered shift's date).
const ownDayOffShifts = ref<ShiftOption[]>([])
const selectedOwnDayOff = ref<ShiftOption | null>(null)
const loadingOwnDayOffs = ref(false)
// Controls which step is visible: false = calendar (step 1), true = people list (step 2).
const ownDayOffSelected = ref(false)
// Independent calendar month for step 1 own day-off selection.
const ownDayOffCalendarMonth = ref<string>(new Date().toISOString().slice(0, 7))

// Candidate counts per date for step 1 calendar enrichment.
// date → (shift_type_id → count)
const dayOffCandidateCounts = ref<Map<string, Map<number, number>>>(new Map())
const loadingCandidateCounts = ref(false)

const dayOffShiftNames = ['dayoff', 'day off', 'folga', 'holidays', 'sick leave', 'parental leave']
const pureLeaveNames = ['holidays', 'sick leave', 'parental leave']
const isSwappableDayOff = (s: ShiftOption) => {
  const name = s.shift_type.name.toLowerCase()
  return dayOffShiftNames.includes(name) && !pureLeaveNames.includes(name)
}

const isDayOffShift = (shift: ShiftOption | null | undefined) => {
  if (!shift?.shift_type?.name) return false
  const name = shift.shift_type.name.toLowerCase()
  return dayOffShiftNames.includes(name)
}

const isShiftForDayoffMode = computed(() => swapMode.value === 'shift_for_dayoff')

const normalizeDateKey = (value: string | null | undefined) => {
  if (!value) return ''
  return String(value).slice(0, 10)
}

const resultShifts = computed(() => {
  if (swapMode.value === 'shift_for_dayoff') {
    // In shift_for_dayoff mode, availableShifts is populated per selected date.
    // Exclude candidates also on a day-off on the offered day-off's date — they have no work shift to give up.
    if (loadingTargetShifts.value) return availableShifts.value
    return availableShifts.value.filter((shift) => getTargetWorkShift(Number(shift.users[0]?.id)) !== null)
  }

  const originDate = normalizeDateKey(offeredShift.value?.date)
  if (!originDate) return []

  return availableShifts.value.filter((shift) => {
    if (normalizeDateKey(shift.date) !== originDate) return false
    if (requestedShiftTypeId.value !== null && Number(shift.shift_type.id) !== Number(requestedShiftTypeId.value)) return false
    return true
  })
})

const availableShiftTypeFilters = computed(() => {
  const seen = new Map<number, typeof shiftTypes.value[0]>()
  const source = isShiftForDayoffMode.value ? targetWorkShifts.value : resultShifts.value
  for (const shift of source) {
    const tid = Number(shift.shift_type.id)
    if (!seen.has(tid)) seen.set(tid, shiftTypes.value.find((t) => Number(t.id) === tid) ?? { id: tid, name: shift.shift_type.name, start_time: shift.shift_type.start_time ?? '', end_time: shift.shift_type.end_time ?? '', color: shift.shift_type.color ?? '' })
  }
  const shiftOrder: Record<string, number> = { morning: 0, afternoon: 1, night: 2 }
  return [...seen.values()].sort((a, b) => {
    const aOrder = shiftOrder[a.name.toLowerCase()] ?? 99
    const bOrder = shiftOrder[b.name.toLowerCase()] ?? 99
    if (aOrder !== bOrder) return aOrder - bOrder
    return (a.start_time ?? '').localeCompare(b.start_time ?? '')
  })
})

const filteredResultShifts = computed(() => {
  let list = resultShifts.value
  const query = searchQuery.value.trim().toLowerCase()
  if (query) list = list.filter((shift) => (shift.users[0]?.name || '').toLowerCase().includes(query))
  if (filterShiftTypeId.value !== null) {
    if (isShiftForDayoffMode.value) {
      list = list.filter((shift) => Number(getTargetWorkShift(Number(shift.users[0]?.id))?.shift_type?.id) === filterShiftTypeId.value)
    } else {
      list = list.filter((shift) => Number(shift.shift_type.id) === filterShiftTypeId.value)
    }
  }
  return list
})

const totalPages = computed(() => Math.ceil(filteredResultShifts.value.length / 10))

const paginatedResultShifts = computed(() => {
  const start = (currentPage.value - 1) * resultsPerPage
  return filteredResultShifts.value.slice(start, start + resultsPerPage)
})

// In shift_for_dayoff mode only shifts with a found target work shift count as selectable.
const allVisibleSelected = computed(() => {
  const selectable = isShiftForDayoffMode.value
    ? paginatedResultShifts.value.filter((shift) => getTargetWorkShift(Number(shift.users[0]?.id)) !== null)
    : paginatedResultShifts.value
  if (selectable.length === 0) return false
  return selectable.every((shift) => selectedResults.value.has(shift.id))
})

// In shift_for_dayoff mode only count shifts that are both selected and selectable.
const visibleSelectionCount = computed(() => {
  return paginatedResultShifts.value.filter((shift) => {
    if (!selectedResults.value.has(shift.id)) return false
    if (isShiftForDayoffMode.value) return getTargetWorkShift(Number(shift.users[0]?.id)) !== null
    return true
  }).length
})

const canGoPreviousPage = computed(() => currentPage.value > 1)
const canGoNextPage = computed(() => currentPage.value < totalPages.value)

const confirmSummaryLine = computed(() => {
  const count = selectedResults.value.size
  return currentLocale.value === 'pt'
    ? `Vais propor a troca do teu turno a ${count} colega(s). Cada um recebe um pedido individual — assim que um aceitar, os restantes são automaticamente cancelados. O turno mantém-se teu até lá.`
    : `You're proposing to swap your shift with ${count} colleague(s). Each one receives an individual request — once someone accepts, the rest are automatically cancelled. Your shift stays yours until then.`
})

const rightPanelTitle = computed(() => {
  return currentLocale.value === 'pt' ? 'Pessoas disponíveis' : 'Available people'
})

const requestedShiftType = computed(() => {
  if (requestedShiftTypeId.value === null) return null
  return shiftTypes.value.find((type) => Number(type.id) === Number(requestedShiftTypeId.value)) || null
})

const requestedShiftTypeLabel = computed(() => {
  if (!requestedShiftType.value) return '—'
  return getShiftName(requestedShiftType.value as ShiftOption['shift_type'])
})

const texts = computed(() => ({
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  title: currentLocale.value === 'pt' ? 'Novo Pedido de Troca' : 'New Swap Request',
  rightLoading: currentLocale.value === 'pt' ? 'A carregar...' : 'Loading...',
  rightEmpty: currentLocale.value === 'pt' ? 'Sem resultados disponíveis.' : 'No results available.',
  searchPlaceholder: currentLocale.value === 'pt' ? 'Procurar enfermeiro...' : 'Search nurse...',
  selectAllPeople: currentLocale.value === 'pt' ? 'Selecionar todas as pessoas' : 'Select all people',
  previousPage: currentLocale.value === 'pt' ? 'Anterior' : 'Previous',
  nextPage: currentLocale.value === 'pt' ? 'Seguinte' : 'Next',
  paginationPage: currentLocale.value === 'pt' ? 'Página' : 'Page',
  paginationOf: currentLocale.value === 'pt' ? 'de' : 'of',
  creating: currentLocale.value === 'pt' ? 'A criar...' : 'Creating...',
  actionNotePlaceholder: currentLocale.value === 'pt' ? 'Adicionar nota...' : 'Add note...',
  noteOptional: currentLocale.value === 'pt' ? 'Nota (opcional)' : 'Note (optional)',
  submit: currentLocale.value === 'pt' ? 'Criar pedido' : 'Create request',
  submitSuccess: currentLocale.value === 'pt' ? 'Pedido criado com sucesso!' : 'Request created successfully!',
  submitErrorFallback: currentLocale.value === 'pt' ? 'Não foi possível criar o pedido.' : 'Failed to create request.',
  invalidForm: currentLocale.value === 'pt' ? 'Seleciona os resultados para continuar.' : 'Please select results to continue.',
  calendarSelectDay: currentLocale.value === 'pt' ? 'Seleciona um dia' : 'Select a day',
  calendarAvailable: currentLocale.value === 'pt' ? 'Disponíveis' : 'Available',
  shiftForDayoffStep1Title: currentLocale.value === 'pt' ? 'Escolhe a tua folga a oferecer' : 'Choose your day off to offer',
  shiftForDayoffStep1Info: currentLocale.value === 'pt'
    ? 'Para receberes este turno, tens de oferecer uma das tuas folgas em troca. Seleciona a folga que pretendes dar.'
    : 'To receive this shift, you need to offer one of your day offs in return. Select the day off you want to give.',
  shiftForDayoffStep2Title: currentLocale.value === 'pt' ? 'Escolhe o dia' : 'Pick a day',
  shiftForDayoffStep3Title: currentLocale.value === 'pt' ? 'Pessoas disponíveis' : 'Available people',
  backToMyDayOffs: currentLocale.value === 'pt' ? '← Voltar às minhas folgas' : '← Back to my day offs',
  backToCalendarStep: currentLocale.value === 'pt' ? '← Voltar ao calendário' : '← Back to calendar',
  noOwnDayOffs: currentLocale.value === 'pt' ? 'Não tens folgas futuras disponíveis para oferecer.' : 'You have no future day offs available to offer.',
  confirmModal: {
    title: currentLocale.value === 'pt' ? 'Confirmar pedido de troca' : 'Confirm swap request',
    cancel: currentLocale.value === 'pt' ? 'Cancelar' : 'Cancel',
    confirm: currentLocale.value === 'pt' ? 'Confirmar' : 'Confirm',
  },
}))

// --- Calendar helpers ---


const calendarWeekDays = computed(() => {
  return currentLocale.value === 'pt'
    ? ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
    : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
})

const todayStr = new Date().toISOString().slice(0, 10)

// Block today and all past days — a nurse can't swap a day off for a day that's already here.
const isCellPast = (date: string) => date <= todayStr

// True when the cell date matches the shift the nurse is offering — can't swap to the same day.
const isOfferedShiftDate = (date: string) => date === normalizeDateKey(offeredShift.value?.date)

/** Fetch nurses with a day-off on `date` (the offered work shift's date) for step 2 candidates. */
const fetchDayShiftsForShiftSwap = async (date: string) => {
  loadingDayShifts.value = true
  availableShifts.value = []
  selectedResults.value = new Set()
  validationWarnings.value = {}

  try {
    const all = await fetchShifts({ exclude_mine: true, date })
    // Only show nurses with a true day-off (not holidays/sick leave/parental leave).
    availableShifts.value = all.filter(isSwappableDayOff)
    await runAllValidations()
  } catch (error) {
    console.error('Error fetching day shifts for shift swap:', error)
  } finally {
    loadingDayShifts.value = false
  }
}

// --- Shift-for-dayoff step functions ---

/** All calendar cells for step 1: own day-off selection. Available cells have a matching shift in ownDayOffShifts. */
const ownDayOffCalendarCells = computed(() => {
  const [year, month] = ownDayOffCalendarMonth.value.split('-').map(Number)
  const firstDay = new Date(year, month - 1, 1)
  const lastDay = new Date(year, month, 0)

  const startPad = (firstDay.getDay() + 6) % 7
  const cells: Array<{ date: string | null; day: number | null; available: boolean }> = []

  // Build a set of dates that have a matching own day-off for O(1) lookup.
  const ownDayOffDates = new Set(ownDayOffShifts.value.map((s) => s.date.slice(0, 10)))

  for (let i = 0; i < startPad; i++) cells.push({ date: null, day: null, available: false })

  for (let d = 1; d <= lastDay.getDate(); d++) {
    const dateStr = `${ownDayOffCalendarMonth.value}-${String(d).padStart(2, '0')}`
    cells.push({
      date: dateStr,
      day: d,
      available: ownDayOffDates.has(dateStr) && !isCellPast(dateStr),
    })
  }

  return cells
})

/** Load the authenticated user's own future day-off shifts for step 1 selection. */
const loadOwnDayOffShifts = async () => {
  loadingOwnDayOffs.value = true
  try {
    const all = await fetchShifts({ mine: true, future: true })
    ownDayOffShifts.value = all.filter(isSwappableDayOff)
    // Pin the step 1 calendar to the month of the earliest own day-off so it's immediately visible.
    // This only affects ownDayOffCalendarMonth — immediately visible when step 1 opens.
    if (ownDayOffShifts.value.length > 0) {
      ownDayOffCalendarMonth.value = ownDayOffShifts.value[0].date.slice(0, 7)
    }
  } catch (error) {
    console.error('Error loading own day-off shifts:', error)
  } finally {
    loadingOwnDayOffs.value = false
  }
}

/** For each own day-off date in step 1, count how many nurses:
 *  1. have a day-off on the offeredShift date (valid swap targets), AND
 *  2. have a work shift on that day-off date (the shift the user will receive),
 *  grouped by the work shift type. */
const loadCandidateCounts = async () => {
  loadingCandidateCounts.value = true
  const offeredDate = normalizeDateKey(offeredShift.value?.date)
  if (!offeredDate) { loadingCandidateCounts.value = false; return }

  const uniqueDates = [...new Set(ownDayOffShifts.value.map((s) => s.date.slice(0, 10)))]
  try {
    // Fetch once: all nurses with a day-off on the offered shift date.
    const dayOffCandidates = await fetchShifts({ exclude_mine: true, date: offeredDate }).catch(() => [] as ShiftOption[])
    const candidateUserIds = new Set(
      dayOffCandidates
        .filter(isSwappableDayOff)
        .flatMap((s) => s.users.map((u) => Number(u.id))),
    )

    const results = await Promise.all(
      uniqueDates.map(async (date) => {
        // For each own day-off date, fetch work shifts — but only count nurses who are valid candidates.
        const shifts = await fetchShifts({ exclude_mine: true, date }).catch(() => [] as ShiftOption[])
        const typeMap = new Map<number, number>()
        for (const s of shifts) {
          if (dayOffShiftNames.includes(s.shift_type.name.toLowerCase())) continue
          for (const u of s.users) {
            if (!candidateUserIds.has(Number(u.id))) continue
            const tid = Number(s.shift_type.id)
            typeMap.set(tid, (typeMap.get(tid) ?? 0) + 1)
          }
        }
        return { date, typeMap }
      }),
    )
    const countMap = new Map<string, Map<number, number>>()
    for (const { date, typeMap } of results) countMap.set(date, typeMap)
    dayOffCandidateCounts.value = countMap
  } finally {
    loadingCandidateCounts.value = false
  }
}

/** Own work shifts indexed by date for step 1 calendar (excludes day-off types). */
const myWorkShiftsByDate = computed(() => {
  const map = new Map<string, ShiftOption[]>()
  for (const shift of myShifts.value) {
    if (dayOffShiftNames.includes(shift.shift_type.name.toLowerCase())) continue
    const date = shift.date.slice(0, 10)
    if (!map.has(date)) map.set(date, [])
    map.get(date)!.push(shift)
  }
  return map
})

/** Find the own day-off shift matching `date` and advance to step 2. */
const selectOwnDayOffFromCalendar = (date: string) => {
  const shift = ownDayOffShifts.value.find((s) => s.date.slice(0, 10) === date)
  if (!shift) return
  selectOwnDayOff(shift)
}

/** Navigate the step 1 own-day-off calendar backwards by one month. */
const goToPreviousOwnMonth = () => {
  const [year, month] = ownDayOffCalendarMonth.value.split('-').map(Number)
  const prev = new Date(year, month - 2, 1)
  ownDayOffCalendarMonth.value = `${prev.getFullYear()}-${String(prev.getMonth() + 1).padStart(2, '0')}`
}

/** Navigate the step 1 own-day-off calendar forwards by one month. */
const goToNextOwnMonth = () => {
  const [year, month] = ownDayOffCalendarMonth.value.split('-').map(Number)
  const next = new Date(year, month, 1)
  ownDayOffCalendarMonth.value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`
}

/** Month label for the step 1 own-day-off calendar. */
const ownDayOffCalendarMonthLabel = computed(() => {
  const [year, month] = ownDayOffCalendarMonth.value.split('-').map(Number)
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1))
  return label.replace(/(\S+)/g, (word, _m, offset) =>
    offset === 0 ? word : (/^\d/.test(word) ? word : word.toLowerCase()),
  )
})

// Work shifts on the selectedOwnDayOff date: used to identify which shift the target nurse will give up.
const targetWorkShifts = ref<ShiftOption[]>([])
const loadingTargetShifts = ref(false)

/** Fetch all work shifts (non-dayoff) on the date of the selected own day-off. */
const fetchTargetWorkShifts = async () => {
  const date = normalizeDateKey(selectedOwnDayOff.value?.date)
  if (!date) return
  loadingTargetShifts.value = true
  try {
    const all = await fetchShifts({ date, exclude_mine: true })
    targetWorkShifts.value = all.filter((s) => !dayOffShiftNames.includes(s.shift_type.name.toLowerCase()))
  } catch (error) {
    console.error('Error fetching target work shifts:', error)
  } finally {
    loadingTargetShifts.value = false
  }
}

/** Returns the work shift for `userId` on the day-off date, or null if not found. */
const getTargetWorkShift = (userId: number): ShiftOption | null => {
  return targetWorkShifts.value.find((s) => s.users.some((u) => Number(u.id) === userId)) ?? null
}

/** Step 1 → Step 2: user picks a day-off to offer. Fetches people who have the same day-off type on
 *  the offered work shift date, and also fetches their work shifts on the day-off date. */
const selectOwnDayOff = async (shift: ShiftOption) => {
  selectedOwnDayOff.value = shift
  ownDayOffSelected.value = true
  // The target date is always the offered work shift's date — no calendar needed for step 2.
  const targetDate = normalizeDateKey(offeredShift.value?.date)
  await Promise.all([
    targetDate ? fetchDayShiftsForShiftSwap(targetDate) : Promise.resolve(),
    fetchTargetWorkShifts(),
  ])
}

/** Step 2 → Step 1: go back to own day-off calendar selection. */
const backToOwnDayOffSelection = () => {
  ownDayOffSelected.value = false
  selectedOwnDayOff.value = null
  availableShifts.value = []
  selectedResults.value = new Set()
  targetWorkShifts.value = []
}

// --- End calendar helpers ---

const toColorClass = (color: string) => {
  const normalized = (color || '').toLowerCase().replace(/[^a-z0-9]/g, '')
  return normalized ? `swc-shift-dot--${normalized}` : 'swc-shift-dot--fallback'
}

const allShiftColors = computed(() => {
  const unique = new Set<string>()
  for (const shift of myShifts.value) { if (shift.shift_type.color) unique.add(shift.shift_type.color) }
  for (const shift of availableShifts.value) { if (shift.shift_type.color) unique.add(shift.shift_type.color) }
  return Array.from(unique)
})

useHead(() => ({
  style: [{
    children: allShiftColors.value
      .filter((color) => /^[#(),.%\sa-zA-Z0-9-]+$/.test(color))
      .map((color) => `.${toColorClass(color)} { background: ${color}; }`)
      .join('\n'),
  }],
}))

const formatDate = (value: string) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  const formatted = new Intl.DateTimeFormat(locale, {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
  const normalized = formatted.replace('.', '')
  return normalized.charAt(0).toUpperCase() + normalized.slice(1)
}

const formatTime = (value: string) => {
  if (!value) return '-'
  return value.slice(0, 5)
}

const getShiftName = (shiftType: ShiftOption['shift_type'] | null | undefined) => {
  if (!shiftType) return ''
  const name = (shiftType.name || '').toLowerCase()
  const pt: Record<string, string> = { morning: 'Manhã', afternoon: 'Tarde', night: 'Noite', dayoff: 'Folga', 'day off': 'Folga', holidays: 'Férias', 'sick leave': 'Baixa Médica', 'parental leave': 'Licença Parental' }
  const en: Record<string, string> = { morning: 'Morning', afternoon: 'Afternoon', night: 'Night', dayoff: 'Day Off', 'day off': 'Day Off', holidays: 'Holidays', 'sick leave': 'Sick Leave', 'parental leave': 'Parental Leave' }
  return currentLocale.value === 'pt' ? (pt[name] || shiftType.name) : (en[name] || shiftType.name)
}

// Single-letter shift name, used on narrow screens only inside the "N available" badges (not
// the cell's own shift-type label) so cells with multiple badges stay compact instead of
// overlapping the row below.
const getShiftInitial = (shiftType: ShiftOption['shift_type'] | null | undefined) => {
  const name = getShiftName(shiftType)
  return name ? name.charAt(0).toUpperCase() : ''
}
const getShiftLabelForCalendar = (shiftType: ShiftOption['shift_type'] | null | undefined) =>
  isNarrowScreen.value ? getShiftInitial(shiftType) : getShiftName(shiftType)

const getDayOffCellStyle = (date: string) => {
  const color = myShifts.value.find((s) => s.date.slice(0, 10) === date)?.shift_type.color
  return color ? { backgroundColor: color + '66' } : {}
}

const isAllDayShift = (shiftType: ShiftOption['shift_type']) => {
  const name = (shiftType.name || '').toLowerCase()
  return dayOffShiftNames.includes(name)
    || (shiftType.start_time === '00:00:00' && shiftType.end_time === '00:00:00')
    || name === 'holidays'
    || name === 'sick leave'
    || name === 'parental leave'
}

const formatShiftLabel = (shift: ShiftOption) => {
  const timeLabel = isAllDayShift(shift.shift_type)
    ? (currentLocale.value === 'pt' ? 'Dia inteiro' : 'All day')
    : `${formatTime(shift.shift_type.start_time)} - ${formatTime(shift.shift_type.end_time)}`
  return { typeName: getShiftName(shift.shift_type), timeLabel }
}

const getShiftAvatarInitial = (shift: ShiftOption) => {
  return shift.users[0]?.name?.trim()?.[0]?.toUpperCase() || shift.shift_type.name.trim()[0]?.toUpperCase() || '?'
}

const isResultSelected = (shiftId: number) => selectedResults.value.has(shiftId)

const getWarningName = (warning: ValidationWarning) => warning?.nurse_name || warning?.nurse?.name || ''

const runAllValidations = async () => {
  if (!sourceShiftId.value) return
  const results = resultShifts.value
  if (results.length === 0) return

  await Promise.all(
    results.map(async (shift) => {
      try {
        const warnings = await validateShift(sourceShiftId.value as number, shift.id)
        validationWarnings.value = { ...validationWarnings.value, [shift.id]: warnings }
      } catch {
        validationWarnings.value = { ...validationWarnings.value, [shift.id]: [] }
      }
    }),
  )
}

const toggleResultSelection = async (shift: ShiftOption) => {
  const next = new Set(selectedResults.value)
  next.has(shift.id) ? next.delete(shift.id) : next.add(shift.id)
  selectedResults.value = next
  await runAllValidations()
}

// In shift_for_dayoff mode skip shifts with no target work shift — they cannot be selected.
const toggleSelectAllVisible = async () => {
  const next = new Set(selectedResults.value)
  const selectable = isShiftForDayoffMode.value
    ? paginatedResultShifts.value.filter((shift) => getTargetWorkShift(Number(shift.users[0]?.id)) !== null)
    : paginatedResultShifts.value
  if (allVisibleSelected.value) {
    for (const shift of selectable) next.delete(shift.id)
  } else {
    for (const shift of selectable) next.add(shift.id)
  }
  selectedResults.value = next
  await runAllValidations()
}

const loadAvailableShifts = async () => {
  if (loadingAvailable.value || availableShifts.value.length > 0) return
  loadingAvailable.value = true
  submitError.value = null

  try {
    const isWorkShift = (s: ShiftOption) => !dayOffShiftNames.includes(s.shift_type.name.toLowerCase())
    let all = (await fetchShifts({ exclude_mine: true })).filter(isWorkShift)
    if (all.length === 0) {
      const anyShifts = await fetchShifts({})
      const authUserId = Number(user.value?.id)
      all = anyShifts.filter((shift) => !shift.users.some((nurse) => Number(nurse.id) === authUserId) && isWorkShift(shift))
    }
    availableShifts.value = all
    await runAllValidations()
  } catch (error) {
    console.error('Error loading available shifts:', error)
    submitError.value = error instanceof Error ? error.message : 'Failed to load shifts.'
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  } finally {
    loadingAvailable.value = false
  }
}

const loadShiftTypes = async () => {
  try {
    await fetchShiftTypes()
  } catch (error) {
    console.warn('Error loading shift types:', error)
  }
}

const loadMyShifts = async () => {
  loadingMyShifts.value = true
  submitError.value = null
  try {
    myShifts.value = await fetchShifts({ mine: true, future: true })
  } catch (error) {
    console.error('Error loading my shifts:', error)
    submitError.value = error instanceof Error ? error.message : 'Failed to load shifts.'
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  } finally {
    loadingMyShifts.value = false
  }
}

const submitCreateSwap = async () => {
  submitError.value = null
  submitSuccess.value = false

  const selectedRequestIds = Array.from(selectedResults.value)
  if (selectedRequestIds.length === 0) {
    submitError.value = texts.value.invalidForm
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  if (swapMode.value === 'shift' && !sourceShiftId.value) {
    submitError.value = texts.value.invalidForm
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  // In shift_for_dayoff mode the selected own day-off is required as the second offered shift.
  if (swapMode.value === 'shift_for_dayoff' && !selectedOwnDayOff.value) {
    submitError.value = texts.value.shiftForDayoffStep1Title
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  const selectedRequestShifts = selectedRequestIds
    .map((id) => availableShifts.value.find((s) => s.id === id))
    .filter((s): s is ShiftOption => Boolean(s))

  if (selectedRequestShifts.length !== selectedRequestIds.length) {
    submitError.value = texts.value.invalidForm
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  if (selectedRequestShifts.some((s) => s.users.length === 0)) {
    submitError.value = 'Turno sem enfermeiro atribuído. Não é possível criar o pedido.'
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  submitting.value = true
  try {
    await Promise.all(
      selectedRequestShifts.map((requestedShift) => {
        // shift_for_dayoff: offer both the work shift and the own day-off in exchange
        // for the target's work shift on the selected date.
        const offeredShiftIds = swapMode.value === 'shift_for_dayoff'
          ? [sourceShiftId.value as number, selectedOwnDayOff.value!.id]
          : [sourceShiftId.value as number]

        // In shift_for_dayoff: also request the target's work shift on the day-off date if found.
        const targetUserId = requestedShift.users[0]?.id as number
        const targetWorkShift = swapMode.value === 'shift_for_dayoff'
          ? getTargetWorkShift(Number(targetUserId))
          : null
        const requestedShiftIds = targetWorkShift
          ? [requestedShift.id, targetWorkShift.id]
          : [requestedShift.id]

        return createSwap({
          offered_shift_ids: offeredShiftIds,
          requested_shift_ids: requestedShiftIds,
          target_user_id: targetUserId,
          notes: notes.value.trim() || undefined,
        })
      }),
    )
    showConfirmModal.value = false
    showToast('createSuccess', 'success')
    setTimeout(async () => { await navigateTo('/swaps') }, 1500)
  } catch (error) {
    const apiError = error as { data?: unknown; response?: { _data?: unknown; data?: unknown } }
    console.error('Create swap error:', { error, responseBody: apiError?.data ?? apiError?.response?._data ?? null })
    submitError.value = errorSwaps.value || texts.value.submitErrorFallback
    showToast('createError', 'error')
    await nextTick()
    document.querySelector('.swc-submit-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  } finally {
    submitting.value = false
  }
}

const goBack = async () => {
  if (isShiftForDayoffMode.value && ownDayOffSelected.value) {
    backToOwnDayOffSelection()
  } else {
    await navigateTo('/swap-select')
  }
}

const goToPreviousPage = () => { if (canGoPreviousPage.value) currentPage.value -= 1 }
const goToNextPage = () => { if (canGoNextPage.value) currentPage.value += 1 }
const setPage = (page: number) => { if (page >= 1 && page <= totalPages.value) currentPage.value = page }

const openConfirmModal = () => {
  submitError.value = null
  submitSuccess.value = false
  showConfirmModal.value = true
}

const closeConfirmModal = () => {
  if (submitting.value) return
  showConfirmModal.value = false
}

watch(filteredResultShifts, () => {
  if (totalPages.value > 0 && currentPage.value > totalPages.value) currentPage.value = totalPages.value
})

watch(searchQuery, () => { currentPage.value = 1 })
watch(resultShifts, () => {
  if (filterShiftTypeId.value !== null && !availableShiftTypeFilters.value.some((t) => Number(t.id) === filterShiftTypeId.value)) {
    filterShiftTypeId.value = null
  }
})

onMounted(async () => {
  updateScreenWidth()
  window.addEventListener('resize', updateScreenWidth)

  const modeParam = Array.isArray(route.query.mode) ? route.query.mode[0] : route.query.mode
  swapMode.value = modeParam === 'shift_for_dayoff' ? 'shift_for_dayoff' : 'shift'

  const shiftTypeParam = Array.isArray(route.query.shift_type_id) ? route.query.shift_type_id[0] : route.query.shift_type_id
  const parsedShiftTypeId = shiftTypeParam ? Number(shiftTypeParam) : NaN
  requestedShiftTypeId.value = Number.isNaN(parsedShiftTypeId) ? null : parsedShiftTypeId

  const shiftIdParam = Array.isArray(route.query.shift_id) ? route.query.shift_id[0] : route.query.shift_id
  const parsedShiftId = shiftIdParam ? Number(shiftIdParam) : NaN
  sourceShiftId.value = Number.isNaN(parsedShiftId) ? null : parsedShiftId

  if (swapMode.value === 'shift_for_dayoff') {
    // shift_for_dayoff resolves offeredShift manually below; load shift types and own day-offs.
    // myShifts is loaded here so myWorkShiftsByDate is populated for the step 1 calendar.
    const [allMineEarly] = await Promise.all([fetchShifts({ mine: true }), loadShiftTypes(), loadOwnDayOffShifts()])
    myShifts.value = allMineEarly
  } else {
    await Promise.all([loadMyShifts(), loadAvailableShifts(), loadShiftTypes()])
  }

  if (sourceShiftId.value) {
    // In dayoff and shift_for_dayoff modes, always fetch without future:true so
    // today's shifts are not excluded.
    if (swapMode.value === 'shift_for_dayoff') {
      // myShifts already loaded above — just resolve offeredShift from it.
      offeredShift.value = myShifts.value.find((s) => Number(s.id) === Number(sourceShiftId.value)) || null
    } else {
      offeredShift.value = myShifts.value.find((s) => Number(s.id) === Number(sourceShiftId.value)) || null
    }

    if (!offeredShift.value) {
      try {
        if (swapMode.value !== 'shift_for_dayoff') {
          // Already tried mine above in shift_for_dayoff; skip duplicate call.
          const allMine = await fetchShifts({ mine: true })
          offeredShift.value = allMine.find((s) => Number(s.id) === Number(sourceShiftId.value)) || null
          if (offeredShift.value) myShifts.value = allMine
        }

        if (!offeredShift.value && user.value?.id) {
          const byUser = await fetchShifts({ user_id: Number(user.value.id) })
          offeredShift.value = byUser.find((s) => Number(s.id) === Number(sourceShiftId.value)) || null
          if (offeredShift.value) myShifts.value = byUser
        }

        if (!offeredShift.value) {
          const allShifts = await fetchShifts({})
          offeredShift.value = allShifts.find((s) => Number(s.id) === Number(sourceShiftId.value)) || null
        }
      } catch (error) {
        console.warn('Fallback load for source shift failed:', error)
      }
    }

    if (offeredShift.value && !myShifts.value.some((s) => Number(s.id) === Number(sourceShiftId.value))) {
      const ownedVersion = myShifts.value.find(
        (s) => s.date === offeredShift.value?.date && Number(s.shift_type?.id) === Number(offeredShift.value?.shift_type?.id),
      )
      if (ownedVersion) {
        offeredShift.value = ownedVersion
        sourceShiftId.value = ownedVersion.id
      }
    }
  }

  // shift_for_dayoff: load candidate counts for step 1 calendar enrichment once offeredShift is known.
  if (swapMode.value === 'shift_for_dayoff') {
    await loadCandidateCounts()
  }

  // shift_for_dayoff: availability fetch starts only after the user picks a day-off (step 1→2).
  if (swapMode.value !== 'shift_for_dayoff') {
    await runAllValidations()
  }
})
</script>

<template>
  <main class="dashboard-layout swc-page">
    <AppNavbar />

    <transition name="slide-down">
      <div v-if="toast" :class="['global-toast', toast.type]">
        <div class="toast-content">
          <svg v-if="toast.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{{ toastTexts[toast.key as keyof typeof toastTexts] }}</span>
        </div>
      </div>
    </transition>

    <section class="dashboard-content swc-content">
      <header class="swc-header">
        <button class="swc-back-btn" @click="goBack">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          {{ texts.back }}
        </button>
        <h1 class="swc-title">{{ texts.title }}</h1>
      </header>

      <!-- Swap header: mirrors the swaps.vue history card layout. -->
      <section v-if="offeredShift" class="hr-card sw-swap-header" aria-label="Swap header">

        <!-- shift_for_dayoff: two offered shifts stacked (work + day-off). -->
        <template v-if="isShiftForDayoffMode">
          <div class="sw-swap-header__panel sw-swap-header__panel--multi">
            <div class="sw-swap-header__panel" :style="{ borderInlineStart: `3px solid ${offeredShift.shift_type.color || 'var(--line)'}` }">
              <span class="sw-swap-header__badge">{{ currentLocale === 'pt' ? 'Turno actual' : 'Current shift' }}</span>
              <p class="sw-swap-header__date">{{ formatDate(offeredShift.date) }}</p>
              <p class="sw-swap-header__type">{{ getShiftName(offeredShift.shift_type) }} · {{ formatTime(offeredShift.shift_type.start_time) }} - {{ formatTime(offeredShift.shift_type.end_time) }}</p>
            </div>
            <div class="sw-swap-header__panel" :style="{ borderInlineStart: `3px solid ${selectedOwnDayOff?.shift_type.color || 'var(--line)'}` }">
              <span class="sw-swap-header__badge">{{ currentLocale === 'pt' ? 'Folga a oferecer' : 'Day off to offer' }}</span>
              <p class="sw-swap-header__date">{{ selectedOwnDayOff ? formatDate(selectedOwnDayOff.date) : '—' }}</p>
              <p class="sw-swap-header__type">{{ selectedOwnDayOff ? `${getShiftName(selectedOwnDayOff.shift_type)} · ${currentLocale === 'pt' ? 'Dia inteiro' : 'All day'}` : '—' }}</p>
            </div>
          </div>

          <div class="sw-swap-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
              <path d="M20 7h-4V3"></path><path d="M16 3l5 5-5 5"></path><path d="M4 17h4v4"></path><path d="M8 21l-5-5 5-5"></path>
            </svg>
          </div>

          <div class="sw-swap-header__panel sw-swap-header__panel--multi">
            <div class="sw-swap-header__panel" :style="{ borderInlineStart: '3px solid var(--line)' }">
              <span class="sw-swap-header__badge">{{ currentLocale === 'pt' ? 'Folga pretendida' : 'Requested day off' }}</span>
              <p class="sw-swap-header__date">{{ formatDate(offeredShift.date) }}</p>
              <p class="sw-swap-header__type">{{ currentLocale === 'pt' ? 'Folga · Dia inteiro' : 'Day off · All day' }}</p>
            </div>
            <div class="sw-swap-header__panel" :style="{ borderInlineStart: '3px solid var(--line)' }">
              <span class="sw-swap-header__badge">{{ currentLocale === 'pt' ? 'Turno a receber' : 'Shift to receive' }}</span>
              <p class="sw-swap-header__date">—</p>
              <p class="sw-swap-header__type">—</p>
            </div>
          </div>
        </template>

        <!-- Shift mode: single offered shift. -->
        <template v-else>
          <div class="sw-swap-header__panel" :style="{ borderInlineStart: `3px solid ${offeredShift.shift_type.color || 'var(--line)'}` }">
            <span class="sw-swap-header__badge">{{ currentLocale === 'pt' ? 'Turno actual' : 'Current shift' }}</span>
            <p class="sw-swap-header__date">{{ formatDate(offeredShift.date) }}</p>
            <p class="sw-swap-header__type">{{ getShiftName(offeredShift.shift_type) }} · {{ formatTime(offeredShift.shift_type.start_time) }} - {{ formatTime(offeredShift.shift_type.end_time) }}</p>
          </div>

          <div class="sw-swap-header__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
              <path d="M20 7h-4V3"></path><path d="M16 3l5 5-5 5"></path><path d="M4 17h4v4"></path><path d="M8 21l-5-5 5-5"></path>
            </svg>
          </div>

          <div class="sw-swap-header__panel" :style="{ borderInlineStart: `3px solid ${requestedShiftType?.color || 'var(--line)'}` }">
            <span class="sw-swap-header__badge">{{ currentLocale === 'pt' ? 'Turno pretendido' : 'Requested shift' }}</span>
            <p class="sw-swap-header__date">{{ requestedShiftTypeLabel }}</p>
            <p class="sw-swap-header__type">{{ requestedShiftType ? `${formatTime(requestedShiftType.start_time)} - ${formatTime(requestedShiftType.end_time)}` : '—' }}</p>
          </div>
        </template>

      </section>

      <!-- Shift-for-dayoff two-step flow: pick own day-off → see people on the work shift date. -->
      <template v-if="isShiftForDayoffMode">

        <!-- STEP 1: Calendar of own future day-off shifts to offer. -->
        <section v-if="!ownDayOffSelected" class="hr-card swc-panel-right">
          <h2 class="swc-panel-title">{{ texts.shiftForDayoffStep1Title }}</h2>
          <p>{{ texts.shiftForDayoffStep1Info }}</p>

          <!-- ownDayOffCalendarMonth is independent from the dayoff mode calendar month. -->
          <div class="swc-cal-nav">
            <button class="swc-cal-nav-btn" @click="goToPreviousOwnMonth" :aria-label="currentLocale === 'pt' ? 'Mês anterior' : 'Previous month'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <span class="swc-cal-month-label">{{ ownDayOffCalendarMonthLabel }}</span>
            <button class="swc-cal-nav-btn" @click="goToNextOwnMonth" :aria-label="currentLocale === 'pt' ? 'Mês seguinte' : 'Next month'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
          </div>

          <div v-if="loadingOwnDayOffs" class="swc-state-msg">
            <div class="swc-spinner"></div>
            <p>{{ texts.rightLoading }}</p>
          </div>
          <div v-else-if="ownDayOffShifts.length === 0" class="swc-empty">{{ texts.noOwnDayOffs }}</div>
          <div v-else class="swc-cal-grid">
            <div v-for="wd in calendarWeekDays" :key="wd" class="swc-cal-weekday">{{ wd }}</div>
            <template v-for="(cell, i) in ownDayOffCalendarCells" :key="cell.date ?? `pad-${i}`">
              <div v-if="!cell.date" class="swc-cal-cell swc-cal-cell--empty"></div>
              <button
                v-else
                class="swc-cal-cell"
                :class="{
                  'swc-cal-cell--available': cell.available,
                  'swc-cal-cell--unavailable': !cell.available,
                  'swc-cal-cell--past': isCellPast(cell.date),
                }"
                :style="getDayOffCellStyle(cell.date)"
                :disabled="!cell.available"
                @click="selectOwnDayOffFromCalendar(cell.date)"
              >
                <span class="swc-cal-day">{{ cell.day }}</span>
                <span class="swc-cal-cell__type-label">{{ getShiftName(myShifts.find((s) => s.date.slice(0, 10) === cell.date)?.shift_type) }}</span>
                <template v-if="cell.available">
                  <!-- Candidate counts per shift type -->
                  <template v-if="loadingCandidateCounts">
                    <span class="swc-cal-cell__receive-label">…</span>
                  </template>
                  <template v-else>
                    <span
                      v-for="t in shiftTypes.filter(t => !dayOffShiftNames.includes(t.name.toLowerCase()) && (dayOffCandidateCounts.get(cell.date)?.get(Number(t.id)) ?? 0) > 0)"
                      :key="t.id"
                      class="swc-cal-cell__receive-label"
                      :style="t.color ? { borderLeft: `2px solid ${t.color}`, paddingLeft: '4px' } : {}"
                    >
                      {{ getShiftLabelForCalendar(t) }}
                      <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                      {{ dayOffCandidateCounts.get(cell.date)?.get(Number(t.id)) ?? 0 }}<template v-if="availableLabel">&nbsp;{{ availableLabel }}</template>
                    </span>
                  </template>
                </template>
              </button>
            </template>
          </div>
        </section>

        <!-- STEP 2: People who have the same day-off type on the offered work shift date. -->
        <section v-else class="hr-card swc-panel-right">
          <div class="swc-results-toolbar">
            <button class="swc-back-to-cal-btn" @click="backToOwnDayOffSelection">{{ texts.backToMyDayOffs }}</button>
            <input v-model="searchQuery" type="text" class="swc-search-input" :placeholder="texts.searchPlaceholder" />
            <div class="custom-select-wrapper">
              <div class="custom-select-trigger" @click.stop="isShiftTypeFilterOpen = !isShiftTypeFilterOpen">
                <span>{{ filterShiftTypeId !== null ? getShiftName(shiftTypes.find(t => Number(t.id) === filterShiftTypeId)) : (currentLocale === 'pt' ? 'Todos os Turnos' : 'All Shifts') }}</span>
                <svg :class="['chevron-icon', { rotate: isShiftTypeFilterOpen }]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </div>
              <transition name="fade-slide">
                <div v-if="isShiftTypeFilterOpen" class="custom-options">
                  <div class="option-item" @click="filterShiftTypeId = null; isShiftTypeFilterOpen = false">
                    {{ currentLocale === 'pt' ? 'Todos os Turnos' : 'All Shifts' }}
                  </div>
                  <div
                    v-for="t in availableShiftTypeFilters"
                    :key="t.id"
                    class="option-item"
                    @click="filterShiftTypeId = Number(t.id); isShiftTypeFilterOpen = false"
                  >{{ getShiftName(t) }}</div>
                </div>
              </transition>
            </div>
            <button class="swc-submit-btn swc-submit-btn--inline" :disabled="selectedResults.size === 0" @click="openConfirmModal">
              {{ texts.submit }}
            </button>
          </div>

          <div v-if="loadingDayShifts" class="swc-state-msg">
            <div class="swc-spinner"></div>
            <p>{{ texts.rightLoading }}</p>
          </div>
          <div v-else-if="resultShifts.length === 0" class="swc-empty">{{ texts.rightEmpty }}</div>
          <div v-else class="swc-results-list">
            <div v-if="filteredResultShifts.length === 0" class="swc-empty">{{ texts.rightEmpty }}</div>
            <template v-else>
              <label class="swc-select-all-row">
                <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible" />
                <span>{{ texts.selectAllPeople }} ({{ visibleSelectionCount }}/{{ paginatedResultShifts.length }})</span>
              </label>

              <article
                v-for="shift in paginatedResultShifts"
                :key="`result-${shift.id}`"
                class="swc-result-card"
                :class="{
                  'swc-result-card--selected': isResultSelected(shift.id),
                  'swc-result-card--disabled': !loadingTargetShifts && getTargetWorkShift(Number(shift.users[0]?.id)) === null,
                }"
              >
                <div class="swc-result-card__row" @click="!loadingTargetShifts && getTargetWorkShift(Number(shift.users[0]?.id)) === null ? undefined : toggleResultSelection(shift)">
                  <div class="swc-result-avatar">{{ getShiftAvatarInitial(shift) }}</div>
                  <div class="swc-result-content">
                    <div class="swc-result-topline">
                      <p class="swc-result-nurse">{{ shift.users[0]?.name || '-' }}</p>
                      <p class="swc-result-meta">
                        {{ formatDate(shift.date) }} · {{ formatShiftLabel(shift).typeName }} · {{ formatShiftLabel(shift).timeLabel }}
                      </p>
                      <template v-if="loadingTargetShifts">
                        <div class="swc-spinner"></div>
                      </template>
                      <template v-else-if="getTargetWorkShift(Number(shift.users[0]?.id))">
                        <span class="swc-swap-header__badge">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                          {{ currentLocale === 'pt' ? 'Turno a receber' : 'Shift to receive' }}:
                          {{ formatDate(getTargetWorkShift(Number(shift.users[0]?.id))!.date) }} ·
                          {{ getShiftName(getTargetWorkShift(Number(shift.users[0]?.id))!.shift_type) }} ·
                          {{ formatShiftLabel(getTargetWorkShift(Number(shift.users[0]?.id))!).timeLabel }}
                        </span>
                      </template>
                      <template v-else>
                        <span class="swc-swap-header__badge">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                          {{ currentLocale === 'pt' ? `Também está de folga ${formatDate(selectedOwnDayOff!.date)}` : `Also on day off ${formatDate(selectedOwnDayOff!.date)}` }}
                        </span>
                      </template>
                    </div>
                    <div class="swc-result-warning-stack">
                      <span
                        v-for="(warning, warningIndex) in validationWarnings[shift.id] || []"
                        :key="`warning-${shift.id}-${warningIndex}`"
                        class="swc-result-warning"
                      >
                        <svg class="swc-result-warning-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3l-8.47-14.14a2 2 0 0 0-3.42 0z"></path>
                          <line x1="12" y1="9" x2="12" y2="13"></line>
                          <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <strong class="swc-result-warning-name">{{ getWarningName(warning) || '-' }}</strong>
                        <span class="swc-result-warning-text">{{ warning.message || warning.text || '' }}</span>
                      </span>
                    </div>
                  </div>
                  <label class="swc-result-check">
                    <input
                      type="checkbox"
                      :checked="isResultSelected(shift.id)"
                      :disabled="!loadingTargetShifts && getTargetWorkShift(Number(shift.users[0]?.id)) === null"
                      @click.stop
                      @change.stop="!loadingTargetShifts && getTargetWorkShift(Number(shift.users[0]?.id)) === null ? undefined : toggleResultSelection(shift)"
                    />
                  </label>
                </div>
              </article>

              <div v-if="filteredResultShifts.length > 0" class="sw-pagination">
                <button class="pagination-btn" :disabled="!canGoPreviousPage" @click="goToPreviousPage">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <div class="pagination-numbers">
                  <button v-for="p in totalPages" :key="p" :class="['page-num', { active: p === currentPage }]" @click="setPage(p)">{{ p }}</button>
                </div>
                <button class="pagination-btn" :disabled="!canGoNextPage" @click="goToNextPage">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
              </div>
            </template>
          </div>
        </section>
      </template>

      <!-- Shift mode: flat list of candidates (unchanged flow). -->
      <template v-else>
        <section class="swc-panel-right hr-card">
          <h2 class="swc-panel-title">{{ rightPanelTitle }}</h2>

          <div class="swc-results-toolbar">
            <input
              v-model="searchQuery"
              type="text"
              class="swc-search-input"
              :placeholder="texts.searchPlaceholder"
            />
            <button
              class="swc-submit-btn swc-submit-btn--inline"
              :disabled="selectedResults.size === 0"
              @click="openConfirmModal"
            >
              {{ texts.submit }}
            </button>
          </div>

          <div v-if="loadingAvailable" class="swc-state-msg">
            <div class="swc-spinner"></div>
            <p>{{ texts.rightLoading }}</p>
          </div>
          <div v-else-if="resultShifts.length === 0" class="swc-empty">{{ texts.rightEmpty }}</div>
          <div v-else class="swc-results-list">
            <div v-if="filteredResultShifts.length === 0" class="swc-empty">{{ texts.rightEmpty }}</div>

            <template v-else>
              <label class="swc-select-all-row">
                <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible" />
                <span>{{ texts.selectAllPeople }} ({{ visibleSelectionCount }}/{{ paginatedResultShifts.length }})</span>
              </label>

              <article
                v-for="shift in paginatedResultShifts"
                :key="`result-${shift.id}`"
                class="swc-result-card"
                :class="{ 'swc-result-card--selected': isResultSelected(shift.id) }"
              >
                <div class="swc-result-card__row" @click="toggleResultSelection(shift)">
                  <div class="swc-result-avatar">{{ getShiftAvatarInitial(shift) }}</div>
                  <div class="swc-result-content">
                    <div class="swc-result-topline">
                      <p class="swc-result-nurse">{{ shift.users[0]?.name || '-' }}</p>
                      <p class="swc-result-meta">
                        {{ formatDate(shift.date) }} · {{ formatShiftLabel(shift).typeName }} · {{ formatShiftLabel(shift).timeLabel }}
                      </p>
                    </div>
                    <div class="swc-result-warning-stack">
                      <span
                        v-for="(warning, warningIndex) in validationWarnings[shift.id] || []"
                        :key="`warning-${shift.id}-${warningIndex}`"
                        class="swc-result-warning"
                      >
                        <svg class="swc-result-warning-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3l-8.47-14.14a2 2 0 0 0-3.42 0z"></path>
                          <line x1="12" y1="9" x2="12" y2="13"></line>
                          <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <strong class="swc-result-warning-name">{{ getWarningName(warning) || '-' }}</strong>
                        <span class="swc-result-warning-text">{{ warning.message || warning.text || '' }}</span>
                      </span>
                    </div>
                  </div>
                  <label class="swc-result-check">
                    <input type="checkbox" :checked="isResultSelected(shift.id)" @click.stop @change.stop="toggleResultSelection(shift)" />
                  </label>
                </div>
              </article>

              <div v-if="filteredResultShifts.length > 0" class="sw-pagination">
                <button class="pagination-btn" :disabled="!canGoPreviousPage" @click="goToPreviousPage">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <div class="pagination-numbers">
                  <button v-for="p in totalPages" :key="p" :class="['page-num', { active: p === currentPage }]" @click="setPage(p)">{{ p }}</button>
                </div>
                <button class="pagination-btn" :disabled="!canGoNextPage" @click="goToNextPage">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
              </div>
            </template>
          </div>
        </section>
      </template>

      <!-- Confirmation modal: final review before creating swap requests. -->
      <transition name="fade">
        <div v-if="showConfirmModal" class="sw-modal-overlay" @click.self="closeConfirmModal">
          <div class="sw-modal-card sw-create-modal">
            <h2>{{ texts.confirmModal.title }}</h2>
            <p>{{ confirmSummaryLine }}</p>
            <div class="sw-form">
              <div class="sw-field">
                <label>{{ texts.noteOptional }}</label>
                <textarea v-model="notes" rows="4" :placeholder="texts.actionNotePlaceholder"></textarea>
              </div>
              <p v-if="submitError" class="swc-submit-error">{{ submitError }}</p>
              <div class="sw-modal-actions">
                <button class="sw-modal-btn cancel" :disabled="submitting" @click="closeConfirmModal">{{ texts.confirmModal.cancel }}</button>
                <button class="sw-modal-btn confirm" :disabled="submitting" @click="submitCreateSwap">
                  {{ submitting ? texts.creating : texts.confirmModal.confirm }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </section>
  </main>
</template>

<style scoped src="~/assets/css/swap-create.css"></style>
<style src="~/assets/css/swaps.css"></style>
