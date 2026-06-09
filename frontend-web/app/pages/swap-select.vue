<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'

definePageMeta({
  middleware: 'auth',
})

// -------------- Dependencies -----------
const { token, user } = useAuth()
const { shiftTypes, fetchShiftTypes } = useSchedule()
const { fetchShifts } = useSwap()
const { currentLocale, texts } = useScheduleTexts()

// ------- Scale Constants --------
const PX_PER_HOUR = 26
const PX_PER_MIN = PX_PER_HOUR / 60

// --------------- State ----------------
const loading = ref(false)
const error = ref<string | null>(null)
const weeklyShifts = ref<any[]>([])
const currentDate = ref(new Date())
const gridBodyRef = ref<HTMLElement | null>(null)
const tooltipActive = ref(false)
const tooltipShift = ref<any>(null)
const tooltipUsers = ref<any[]>([])
const tooltipX = ref(0)
const tooltipY = ref(0)
const pendingShift = ref<any | null>(null)
const swapIntentModalOpen = ref(false)
const selectedSwapIntent = ref<'shift' | 'dayoff' | null>(null)
const selectedSwapShiftTypeId = ref<number | null>(null)

// Real time indicator
const timeIndicatorTop = ref('0px')
let timeIntervalId: any = null

const hoursList = [
  '00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00',
  '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00',
  '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00',
]

// ---------------- Texts ----------------
const viewTexts = computed(() => {
  const isPt = currentLocale.value === 'pt'
  return {
    pageTitle: isPt ? 'Selecionar Turno' : 'Select Shift',
    pageSubtitle: isPt ? 'Escolha o turno que pretende trocar' : 'Choose the shift you want to swap',
    today: isPt ? 'Hoje' : 'Today',
    time: isPt ? 'Horário' : 'Time',
    duration: isPt ? 'Duração' : 'Duration',
    workShift: isPt ? 'Turno de Trabalho' : 'Work Shift',
    absenceShift: isPt ? 'Folga / Ausência' : 'Day Off / Absence',
    assignedNurses: isPt ? 'Enfermeiros Escalados' : 'Assigned Nurses',
    legendTitle: isPt ? 'Turnos:' : 'Shifts:',
    exchangeForShift: isPt ? 'Trocar por turno' : 'Exchange for a shift',
    exchangeForDayOff: isPt ? 'Trocar por folga' : 'Exchange for a day off',
    swapIntentTitle: isPt ? 'Como quer trocar este turno?' : 'How do you want to swap this shift?',
    shiftIntentDescription: isPt ? 'Seleciona um tipo de turno para pedido.' : 'Pick a shift type to request.',
    dayOffIntentDescription: isPt ? 'Escolher a folga a oferecer em troca' : 'Choose the day off to offer in return',
    cancel: isPt ? 'Cancelar' : 'Cancel',
    confirm: isPt ? 'Confirmar' : 'Confirm',
    chooseIntent: isPt ? 'Escolhe o tipo de troca' : 'Choose the swap type',
    chooseShiftType: isPt ? 'Escolhe o tipo de turno' : 'Choose the shift type',
    noAssignedNurses: isPt ? 'Nenhum enfermeiro escalado.' : 'No nurses assigned.',
    errorLoading: isPt ? 'Erro ao carregar horário. Tente novamente.' : 'Error loading schedule. Please try again.',
    legendClickShift: isPt ? 'Clica num turno de trabalho para iniciar um pedido de troca.' : 'Click a work shift to start a swap request.',
    legendClickDayOff: isPt ? 'Clica numa folga para trocar por outra folga.' : 'Click a day off to swap for another day off.',
  }
})

const selectableShiftTypes = computed(() => {
  const dayOffNames = ['dayoff', 'day off', 'folga', 'holidays', 'sick leave', 'parental leave']
  const pendingShiftTypeId = pendingShift.value?.shift_type?.id ?? null

  return shiftTypes.value.filter((type) => {
    const name = (type.name || '').toLowerCase()
    const isDayOffType = dayOffNames.includes(name)
    const isSameAsPendingShift = pendingShiftTypeId !== null && type.id === pendingShiftTypeId
    return !isDayOffType && !isSameAsPendingShift
  })
})

// -------------- Date Calculations --------------
const startOfWeek = computed(() => {
  const d = new Date(currentDate.value)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  return new Date(d.setDate(diff))
})

const weekDays = computed(() => {
  const start = new Date(startOfWeek.value)
  return Array.from({ length: 7 }, (_, i) => {
    const day = new Date(start)
    day.setDate(start.getDate() + i)
    return day
  })
})

const getLocalDateStr = (date: Date): string => {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const getDayOfWeekName = (date: Date) => {
  const ptNames = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo']
  const enNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
  return currentLocale.value === 'pt' ? ptNames[(date.getDay() + 6) % 7] : enNames[(date.getDay() + 6) % 7]
}

const getDayOfMonth = (date: Date) => date.getDate()

const isToday = (date: Date) => {
  const today = new Date()
  return date.getDate() === today.getDate()
    && date.getMonth() === today.getMonth()
    && date.getFullYear() === today.getFullYear()
}

const isWeekend = (date: Date) => date.getDay() === 0 || date.getDay() === 6

const weekRangeLabel = computed(() => {
  const start = startOfWeek.value
  const end = new Date(start)
  end.setDate(start.getDate() + 6)
  const monthsPt = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']
  const monthsEn = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const sd = start.getDate()
  const ed = end.getDate()
  const sm = currentLocale.value === 'pt' ? monthsPt[start.getMonth()] : monthsEn[start.getMonth()]
  const em = currentLocale.value === 'pt' ? monthsPt[end.getMonth()] : monthsEn[end.getMonth()]
  const sy = start.getFullYear()
  const ey = end.getFullYear()
  if (sy !== ey) return `${sd} ${sm} ${sy} – ${ed} ${em} ${ey}`
  if (start.getMonth() !== end.getMonth()) {
    return currentLocale.value === 'pt'
      ? `${sd} ${sm} – ${ed} ${em} de ${sy}`
      : `${sm} ${sd} – ${em} ${ed}, ${sy}`
  }
  return currentLocale.value === 'pt'
    ? `${sd} – ${ed} de ${sm}. de ${sy}`
    : `${sm} ${sd} – ${ed}, ${sy}`
})

// -------------------- Navegation -------------------------
const goToToday = () => { currentDate.value = new Date() }
const goToPreviousWeek = () => {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() - 7)
  currentDate.value = d
}
const goToNextWeek = () => {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() + 7)
  currentDate.value = d
}

// ---------------- Translation ----------------------
const getShiftName = (shiftType: any) => {
  if (!shiftType) return ''
  const name = (shiftType.name || '').toLowerCase()
  const pt: Record<string, string> = { morning: 'Manhã', afternoon: 'Tarde', night: 'Noite', dayoff: 'Folga', 'day off': 'Folga', holidays: 'Férias', 'sick leave': 'Baixa Médica', 'parental leave': 'Licença Parental' }
  const en: Record<string, string> = { morning: 'Morning', afternoon: 'Afternoon', night: 'Night', dayoff: 'Day Off', 'day off': 'Day Off', holidays: 'Holidays', 'sick leave': 'Sick Leave', 'parental leave': 'Parental Leave' }
  return currentLocale.value === 'pt' ? (pt[name] || shiftType.name) : (en[name] || shiftType.name)
}

const formatTime = (value: string) => {
  if (!value) return '-'
  return value.slice(0, 5)
}

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

// ---------------------- Shifts logic -----------------------
const isAllDay = (shiftType: any) => {
  if (!shiftType) return true
  const name = (shiftType.name || '').toLowerCase()
  return ['dayoff', 'day off', 'folga', 'holidays', 'férias', 'sick leave', 'baixa médica', 'parental leave', 'licença parental'].includes(name)
    || (shiftType.start_time === '00:00:00' && shiftType.end_time === '00:00:00')
}

const isDayOffType = (shiftType: any) => {
  if (!shiftType?.name) return false
  const name = String(shiftType.name).toLowerCase()
  return ['dayoff', 'day off', 'folga'].includes(name)
}

const getDayAllDayGroups = (dateStr: string) => {
  const dayShifts = weeklyShifts.value.filter((s) => s.date === dateStr && isAllDay(s.shift_type))
  const groups: Record<number, { shift_type: any; users: any[]; shift: any }> = {}
  dayShifts.forEach((shift) => {
    const typeId = shift.shift_type?.id || 0
    if (!groups[typeId]) {
      groups[typeId] = { shift_type: shift.shift_type, users: [...shift.users], shift }
    } else {
      groups[typeId].users.push(...shift.users)
    }
  })
  return Object.values(groups)
}

// Sees if the shift crosses midnight
const isMidnightCrossing = (startTime: string, endTime: string): boolean => {
  if (!startTime || !endTime || endTime === '00:00:00') return false
  const startH = parseInt(startTime.split(':')[0] ?? '0')
  const startM = parseInt(startTime.split(':')[1] ?? '0')
  const endH = parseInt(endTime.split(':')[0] ?? '0')
  const endM = parseInt(endTime.split(':')[1] ?? '0')
  return endH < startH || (endH === startH && endM < startM)
}

const getDayTimedSegments = (dateStr: string) => {
  const segments: any[] = []

  // Shifts that start today
  const dayShifts = weeklyShifts.value.filter((s) => s.date === dateStr && !isAllDay(s.shift_type))
  dayShifts.forEach((shift) => {
    const st = shift.shift_type
    const startTime: string = st.start_time || '08:00:00'
    const endTime: string = st.end_time || '16:00:00'
    const startH = parseInt(startTime.split(':')[0] ?? '0')
    const startM = parseInt(startTime.split(':')[1] ?? '0')
    const startMins = startH * 60 + startM

    if (isMidnightCrossing(startTime, endTime)) {
      // Crosses midnight -> Seg1: from start to 00:00
      segments.push({ id: `${shift.id}_seg1`, shift, startMins, endMins: 1440, isSeg2: false })
    } else {
      // Normal or shift that ends at midnight (00:00:00 -> endH = 24)
      let endH = parseInt(endTime.split(':')[0] ?? '0')
      const endM = parseInt(endTime.split(':')[1] ?? '0')
      if (endTime === '00:00:00') endH = 24
      segments.push({ id: `${shift.id}_normal`, shift, startMins, endMins: endH * 60 + endM, isSeg2: false })
    }
  })

  // Seg2: rest of the shift from the last day that started before midnight and continues today
  const [y = 0, mo = 0, d = 0] = dateStr.split('-').map(Number)
  const prevDateStr = getLocalDateStr(new Date(y, mo - 1, d - 1))
  const prevDayShifts = weeklyShifts.value.filter((s) => s.date === prevDateStr && !isAllDay(s.shift_type))
  prevDayShifts.forEach((shift) => {
    const st = shift.shift_type
    const startTime: string = st.start_time || '08:00:00'
    const endTime: string = st.end_time || '16:00:00'
    if (isMidnightCrossing(startTime, endTime)) {
      const endH = parseInt(endTime.split(':')[0] ?? '0')
      const endM = parseInt(endTime.split(':')[1] ?? '0')
      segments.push({ id: `${shift.id}_seg2`, shift, startMins: 0, endMins: endH * 60 + endM, isSeg2: true })
    }
  })

  // Greedy Interval Scheduling
  const sorted = [...segments].sort((a, b) =>
    a.startMins - b.startMins || a.id.localeCompare(b.id),
  )
  const laneEndTimes: number[] = []
  for (const seg of sorted) {
    let lane = laneEndTimes.findIndex((et) => et <= seg.startMins)
    if (lane === -1) {
      lane = laneEndTimes.length
      laneEndTimes.push(seg.endMins)
    } else {
      laneEndTimes[lane] = seg.endMins
    }
    seg.lane = lane
  }
  const totalLanes = laneEndTimes.length || 1
  segments.forEach((seg) => { seg.totalLanes = totalLanes })

  return segments
}

const isShiftSelectable = (dateStr: string) => {
  const today = getLocalDateStr(new Date())
  return dateStr >= today
}

const isFutureDateOnly = (dateStr: string) => {
  const today = getLocalDateStr(new Date())
  return dateStr > today
}

const resolveOwnShiftId = async (date: string, shiftTypeId: number): Promise<number | null> => {
  // Fetch shifts owned by the authenticated user and find the one
  // matching the given date and shift type.
  const myShifts = await fetchShifts({ mine: true })
  const match = myShifts.find((s: any) =>
    s.date === date && s.shift_type.id === shiftTypeId,
  )
  return match?.id ?? null
}

const handleShiftCardClick = async (segment: any) => {
  if (!isShiftSelectable(segment.shift.date)) return

  const authId = Number(user.value?.id)
  const shiftBelongsToUser = segment.shift.users?.some((u: any) => Number(u.id) === authId)

  if (!shiftBelongsToUser) return

  pendingShift.value = segment.shift
  selectedSwapIntent.value = null
  selectedSwapShiftTypeId.value = null
  swapIntentModalOpen.value = true
}

const handleAllDayBadgeClick = async (group: any) => {
  if (!group?.shift?.date || !isFutureDateOnly(group.shift.date)) return
  if (!isDayOffType(group.shift_type)) return

  const authId = Number(user.value?.id)
  const shiftBelongsToUser = group.users?.some((u: any) => Number(u.id) === authId)
  if (!shiftBelongsToUser) return

  // Resolve the authenticated user's own shift id for this date and type,
  // since the weekly view groups multiple nurses under one shift object.
  const ownShiftId = await resolveOwnShiftId(group.shift.date, group.shift_type.id)
  if (!ownShiftId) return

  await navigateTo(`/swap-create?shift_id=${ownShiftId}&mode=dayoff`)
}

const closeSwapIntentModal = () => {
  swapIntentModalOpen.value = false
  pendingShift.value = null
  selectedSwapIntent.value = null
  selectedSwapShiftTypeId.value = null
}

const chooseSwapIntent = async (intent: 'shift' | 'dayoff') => {
  if (!pendingShift.value) return

  selectedSwapIntent.value = intent

  if (intent === 'dayoff') {
    const ownShiftId = await resolveOwnShiftId(pendingShift.value.date, pendingShift.value.shift_type.id)
    if (!ownShiftId) return
    // dayoff mode: offering a folga in exchange for another folga.
    // shift_for_dayoff mode: offering a work shift in exchange for a folga.
    const mode = isDayOffType(pendingShift.value.shift_type) ? 'dayoff' : 'shift_for_dayoff'
    await navigateTo(`/swap-create?shift_id=${ownShiftId}&mode=${mode}`)
    return
  }

  selectedSwapShiftTypeId.value = null
}

const confirmSwapIntent = async () => {
  if (!pendingShift.value || selectedSwapIntent.value !== 'shift' || !selectedSwapShiftTypeId.value) return

  const ownShiftId = await resolveOwnShiftId(pendingShift.value.date, pendingShift.value.shift_type.id)
  if (!ownShiftId) return
  await navigateTo(`/swap-create?shift_id=${ownShiftId}&mode=shift&shift_type_id=${selectedSwapShiftTypeId.value}`)
}

// ---------------- Formating ---------------
const formatShiftTime = (shiftType: any) => {
  if (!shiftType?.start_time || !shiftType?.end_time) return ''
  return `${shiftType.start_time.slice(0, 5)} - ${shiftType.end_time.slice(0, 5)}`
}

const formatSegmentTime = (segment: any) => {
  const st = segment.shift.shift_type
  if (!st?.start_time || !st?.end_time) return ''
  const start = st.start_time.slice(0, 5)
  const end = st.end_time.slice(0, 5)
  if (segment.isSeg2) return `00:00 - ${end}`
  if (segment.id.includes('_seg1')) return `${start} - 00:00`
  return `${start} - ${end}`
}

const getShiftDurationLabel = (shiftType: any) => {
  if (!shiftType?.start_time || !shiftType?.end_time) return ''
  let endH = parseInt(shiftType.end_time.split(':')[0])
  const startH = parseInt(shiftType.start_time.split(':')[0])
  if (endH === 0) endH = 24
  let duration = endH - startH
  if (duration < 0) duration += 24
  return `${duration}h`
}

const fetchWeeklySchedule = async () => {
  loading.value = true
  error.value = null
  try {
    const config = useRuntimeConfig()
    const response = await $fetch<{ data: { shifts: any[] } }>(`${config.public.apiBase}/schedules/weekly`, {
      headers: { Authorization: `Bearer ${token.value}` },
      query: { date: getLocalDateStr(startOfWeek.value), view: 'personal' },
    })
    weeklyShifts.value = response.data.shifts || []
  } catch (err) {
    console.error(err)
    error.value = viewTexts.value.errorLoading
  } finally {
    loading.value = false
  }
}

// ------------------------ Colors ------------------------
const adjustColorBrightness = (hex: string, percent: number): string => {
  if (!hex || hex.length < 7) return '#cbd5e1'
  let R = parseInt(hex.substring(1, 3), 16)
  let G = parseInt(hex.substring(3, 5), 16)
  let B = parseInt(hex.substring(5, 7), 16)
  R = Math.max(0, Math.min(255, Math.round((R * (100 + percent)) / 100)))
  G = Math.max(0, Math.min(255, Math.round((G * (100 + percent)) / 100)))
  B = Math.max(0, Math.min(255, Math.round((B * (100 + percent)) / 100)))
  return `#${R.toString(16).padStart(2, '0')}${G.toString(16).padStart(2, '0')}${B.toString(16).padStart(2, '0')}`
}

const getColorVars = (color: string | null | undefined) => {
  const bg = color || '#e2e8f0'
  return { '--item-bg': bg, '--item-border': adjustColorBrightness(bg, -25) }
}

const getShiftCSSVars = (segment: any) => {
  const shiftType = segment.shift.shift_type
  if (!shiftType) return {}

  const topPx = segment.startMins * PX_PER_MIN
  const heightPx = Math.max(12, (segment.endMins - segment.startMins) * PX_PER_MIN)

  const lane = segment.lane ?? 0
  const totalLanes = segment.totalLanes ?? 1
  const cardWidth = totalLanes > 1 ? `calc(${100 / totalLanes}% - 4px)` : 'calc(100% - 4px)'
  const cardLeft = totalLanes > 1 ? `calc(${(lane * 100) / totalLanes}% + 2px)` : '2px'

  const bg = shiftType.color || '#e2e8f0'
  const border = adjustColorBrightness(bg, -25)

  return {
    '--card-top': `${topPx.toFixed(1)}px`,
    '--card-height': `${heightPx.toFixed(1)}px`,
    '--card-left': cardLeft,
    '--card-width': cardWidth,
    '--card-bg': bg,
    '--card-border': border,
  }
}

// --------- Real time grid -------------
const isTimeWithinGrid = computed(() => true)

const updateTimeIndicator = () => {
  const now = new Date()
  const mins = now.getHours() * 60 + now.getMinutes()
  timeIndicatorTop.value = `${(mins * PX_PER_MIN).toFixed(1)}px`
}

// ---------------- Tooltip --------------
const showTooltip = (event: MouseEvent, shift: any, users: any[]) => {
  tooltipShift.value = shift
  tooltipUsers.value = users || []
  tooltipActive.value = true
  updateTooltipPosition(event)
}

const updateTooltipPosition = (event: MouseEvent) => {
  if (!tooltipActive.value) return
  let x = event.clientX + 15
  let y = event.clientY + 15
  if (x + 290 > window.innerWidth) x = event.clientX - 290 - 15
  if (y + 240 > window.innerHeight) y = event.clientY - 240 - 15
  tooltipX.value = x
  tooltipY.value = y
}

const hideTooltip = () => {
  tooltipActive.value = false
  tooltipShift.value = null
  tooltipUsers.value = []
}

// ------------------- Lifecycle -------------------
watch(startOfWeek, fetchWeeklySchedule)

onMounted(async () => {
  updateTimeIndicator()
  timeIntervalId = setInterval(updateTimeIndicator, 60000)

  await fetchWeeklySchedule()
  try { await fetchShiftTypes() } catch (err) { console.warn(err) }

  await nextTick()
  if (gridBodyRef.value) {
    gridBodyRef.value.scrollTop = gridBodyRef.value.scrollHeight * (7 / 24)

    const scrollbarWidth = gridBodyRef.value.offsetWidth - gridBodyRef.value.clientWidth
    if (scrollbarWidth > 0) {
      const container = gridBodyRef.value.closest('.weekly-grid-container')
      if (container) {
        ;(container as HTMLElement).style.setProperty('--scrollbar-width', `${scrollbarWidth}px`)
      }
    }
  }
})

onBeforeUnmount(() => {
  if (timeIntervalId) clearInterval(timeIntervalId)
})
</script>

<template>
  <main class="dashboard-layout schedule-view-page is-personal-view">
    <AppNavbar />

    <section class="dashboard-content">
      <div class="uc-top-bar">
        <div class="uc-title-group">
          <NuxtLink to="/swaps" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            {{ texts.backButton }}
          </NuxtLink>
          <div class="title-with-badge">
            <h1 class="page-title">{{ viewTexts.pageTitle }}</h1>
          </div>
          <p class="uc-subtitle">{{ viewTexts.pageSubtitle }}</p>
        </div>
      </div>

      <!-- Contextual hint bar: explains which elements are clickable and what they trigger. -->
      <div class="schedule-hint-bar">
        <span class="schedule-hint-bar__item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M5 3l14 9-14 9V3z"></path></svg>
          {{ viewTexts.legendClickShift }}
        </span>
        <span class="schedule-hint-bar__item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M5 3l14 9-14 9V3z"></path></svg>
          {{ viewTexts.legendClickDayOff }}
        </span>
      </div>

      <div v-if="loading && weeklyShifts.length === 0" class="schedule-view-state hr-card">
        <div class="spinner"></div>
        <p>{{ texts.loading }}</p>
      </div>

      <div v-else-if="error" class="schedule-view-state error hr-card">
        <svg class="state-error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <p>{{ error }}</p>
        <button class="nav-btn retry-btn" @click="fetchWeeklySchedule">
          {{ currentLocale === 'pt' ? 'Tentar novamente' : 'Try again' }}
        </button>
      </div>

      <template v-else>
        <div class="schedule-controls-row">
          <div class="nav-controls">
            <button class="nav-btn today-btn" @click="goToToday">{{ viewTexts.today }}</button>
            <div class="nav-arrows">
              <button class="nav-btn arrow-btn" :title="currentLocale === 'pt' ? 'Semana anterior' : 'Previous week'" @click="goToPreviousWeek">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
              </button>
              <button class="nav-btn arrow-btn" :title="currentLocale === 'pt' ? 'Semana seguinte' : 'Next week'" @click="goToNextWeek">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </button>
            </div>
            <span class="week-range-label">{{ weekRangeLabel }}</span>
          </div>
        </div>

        <div v-if="shiftTypes && shiftTypes.length > 0" class="schedule-legend">
          <span class="schedule-legend__title">{{ viewTexts.legendTitle }}</span>
          <div class="schedule-legend__items">
            <div
              v-for="type in shiftTypes"
              :key="type.id"
              class="schedule-legend__item"
              :style="getColorVars(type.color)"
            >
              {{ getShiftName(type) }}
              <span v-if="type.start_time && type.start_time !== '00:00:00'" class="legend-time"></span>
              <span v-if="type.start_time && !isAllDay(type)" class="legend-time">
                ({{ type.start_time.slice(0, 5) }} - {{ type.end_time.slice(0, 5) }})
              </span>
            </div>
          </div>
        </div>

        <div class="weekly-grid-container hr-card">
          <div class="weekly-grid-header">
            <div class="time-header-spacer"></div>
            <div class="days-header-grid">
              <div
                v-for="day in weekDays"
                :key="getLocalDateStr(day)"
                class="day-header"
                :class="{ 'is-today': isToday(day), 'is-weekend': isWeekend(day) }"
              >
                <span class="day-name">{{ getDayOfWeekName(day) }}</span>
                <span class="day-date">{{ getDayOfMonth(day) }}</span>
              </div>
            </div>
          </div>

          <div class="weekly-grid-allday">
            <div class="allday-label-spacer">
              <span>{{ currentLocale === 'pt' ? 'Dia inteiro' : 'All day' }}</span>
            </div>
            <div class="allday-columns-grid">
              <div
                v-for="day in weekDays"
                :key="getLocalDateStr(day)"
                class="allday-column"
                :class="{ 'is-today': isToday(day), 'is-weekend': isWeekend(day) }"
              >
                <div
                  v-for="group in getDayAllDayGroups(getLocalDateStr(day))"
                  :key="group.shift_type.id"
                  class="allday-badge"
                  :class="{ 'allday-badge--selectable': isDayOffType(group.shift_type) && isFutureDateOnly(group.shift.date) }"
                  :style="getColorVars(group.shift_type.color)"
                  @mouseenter="showTooltip($event, group.shift, group.users)"
                  @mousemove="updateTooltipPosition"
                  @mouseleave="hideTooltip"
                  @click="handleAllDayBadgeClick(group)"
                >
                  <span class="allday-badge-name">{{ getShiftName(group.shift_type) }}</span>
                  <span class="allday-badge-count">
                    {{ group.users.length }}
                    <svg class="nurse-count-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                    </svg>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div ref="gridBodyRef" class="weekly-grid-body">
            <div class="time-column">
              <div v-for="hour in hoursList" :key="hour" class="time-slot-label">
                <span>{{ hour }}</span>
              </div>
            </div>

            <div class="days-grid">
              <div class="grid-bg-lines">
                <div v-for="hour in hoursList" :key="hour" class="grid-bg-line"></div>
              </div>

              <div
                v-for="day in weekDays"
                :key="getLocalDateStr(day)"
                class="day-column"
                :class="{ 'is-today': isToday(day), 'is-weekend': isWeekend(day) }"
              >
                <div v-if="isToday(day) && isTimeWithinGrid" class="time-indicator" :style="{ top: timeIndicatorTop }">
                  <div class="time-indicator-circle"></div>
                  <div class="time-indicator-line"></div>
                </div>

                <div
                  v-for="segment in getDayTimedSegments(getLocalDateStr(day))"
                  :key="segment.id"
                  class="shift-card"
                  :class="{
                    'shift-card--seg1': segment.id.includes('_seg1'),
                    'shift-card--seg2': segment.isSeg2,
                    'shift-card--past': !isShiftSelectable(segment.shift.date),
                    'shift-card--selectable': isShiftSelectable(segment.shift.date),
                    'shift-card--tarde': !segment.isSeg2 && (
                      segment.shift.shift_type?.end_time?.startsWith('00:00')
                      || segment.shift.shift_type?.end_time?.startsWith('24:00')
                      || segment.shift.shift_type?.name?.toLowerCase()?.includes('tarde')
                      || segment.shift.shift_type?.name?.toLowerCase()?.includes('afternoon')
                    )
                  }"
                  :style="getShiftCSSVars(segment)"
                  @mouseenter="showTooltip($event, segment.shift, segment.shift.users)"
                  @mousemove="updateTooltipPosition"
                  @mouseleave="hideTooltip"
                  @click="handleShiftCardClick(segment)"
                >
                  <div class="shift-card-content">
                    <span class="shift-name">{{ getShiftName(segment.shift.shift_type) }}</span>
                    <span class="shift-time">{{ formatSegmentTime(segment) }}</span>
                    <div class="shift-nurses-list">
                      <span class="nurse-count">
                        {{ segment.shift.users.length }}
                        <svg class="nurse-count-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10">
                          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                          <circle cx="9" cy="7" r="4" />
                        </svg>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </section>

    <div
      v-if="tooltipActive && tooltipShift"
      class="schedule-view-tooltip"
      :style="{ left: `${tooltipX}px`, top: `${tooltipY}px` }"
    >
      <div class="tooltip-header" :style="{ '--tooltip-accent': tooltipShift.shift_type?.color || '#cbd5e1' }">
        <h3 class="tooltip-title">{{ getShiftName(tooltipShift.shift_type) }}</h3>
        <span class="tooltip-type-badge" :class="{ 'is-all-day': isAllDay(tooltipShift.shift_type) }">
          {{ isAllDay(tooltipShift.shift_type) ? viewTexts.absenceShift : viewTexts.workShift }}
        </span>
      </div>
      <div class="tooltip-body">
        <div class="tooltip-row">
          <span class="tooltip-label">{{ viewTexts.time }}:</span>
          <span class="tooltip-val">
            {{ isAllDay(tooltipShift.shift_type)
              ? (currentLocale === 'pt' ? 'Dia Inteiro' : 'All Day')
              : formatShiftTime(tooltipShift.shift_type) }}
          </span>
        </div>
        <div v-if="!isAllDay(tooltipShift.shift_type)" class="tooltip-row">
          <span class="tooltip-label">{{ viewTexts.duration }}:</span>
          <span class="tooltip-val">{{ getShiftDurationLabel(tooltipShift.shift_type) }}</span>
        </div>
        <div class="tooltip-nurses-section">
          <span class="tooltip-label-block">{{ viewTexts.assignedNurses }} ({{ tooltipUsers.length }})</span>
          <div v-if="tooltipUsers.length > 0" class="tooltip-nurses-grid">
            <div v-for="user in tooltipUsers" :key="user.id" class="tooltip-nurse-item">
              <div class="nurse-avatar-mini">{{ user.name[0] }}</div>
              <div class="nurse-info-mini">
                <span class="nurse-name-mini">{{ user.name }}</span>
                <span class="nurse-role-mini">{{ user.role }}</span>
              </div>
            </div>
          </div>
          <div v-else class="no-nurses-lbl">{{ viewTexts.noAssignedNurses }}</div>
        </div>
      </div>
    </div>

    <transition name="fade">
      <div v-if="swapIntentModalOpen && pendingShift" class="modal-overlay" @click.self="closeSwapIntentModal">
        <div class="modal-card swap-intent-modal">
          <h2 class="swap-intent-modal__title">
            {{ viewTexts.swapIntentTitle }}
          </h2>

          <div class="swap-intent-modal__shift-info">
            <span class="swap-intent-modal__shift-date">{{ formatDate(pendingShift.date) }}</span>
            <strong>{{ getShiftName(pendingShift.shift_type) }}</strong>
            <span>{{ formatShiftTime(pendingShift.shift_type) }}</span>
          </div>

          <div v-if="!selectedSwapIntent" class="swap-intent-choice-grid">
            <button class="swap-intent-choice-btn" type="button" @click="chooseSwapIntent('shift')">
              <span>{{ viewTexts.exchangeForShift }}</span>
              <small>{{ viewTexts.shiftIntentDescription }}</small>
            </button>

            <button class="swap-intent-choice-btn" type="button" @click="chooseSwapIntent('dayoff')">
              <span>{{ viewTexts.exchangeForDayOff }}</span>
              <small>{{ viewTexts.dayOffIntentDescription }}</small>
            </button>
          </div>

          <div v-else-if="selectedSwapIntent === 'shift'" class="swap-intent-step">
            <p class="swap-intent-step__label">{{ viewTexts.chooseShiftType }}</p>
            <div class="swap-intent-type-grid">
              <button
                v-for="type in selectableShiftTypes"
                :key="type.id"
                type="button"
                class="swap-intent-type-btn"
                :class="{ 'is-selected': selectedSwapShiftTypeId === type.id }"
                @click="selectedSwapShiftTypeId = type.id"
              >
                <span>{{ getShiftName(type) }}</span>
                <small v-if="type.start_time && type.end_time">{{ type.start_time.slice(0, 5) }} - {{ type.end_time.slice(0, 5) }}</small>
              </button>
            </div>

            <div class="modal-actions swap-intent-modal__actions">
              <button class="modal-btn cancel" type="button" @click="closeSwapIntentModal">
                {{ viewTexts.cancel }}
              </button>
              <button class="modal-btn confirm" type="button" :disabled="!selectedSwapShiftTypeId" @click="confirmSwapIntent">
                {{ viewTexts.confirm }}
              </button>
            </div>
          </div>

          <div v-else class="modal-actions swap-intent-modal__actions">
            <button class="modal-btn cancel" type="button" @click="closeSwapIntentModal">
              {{ viewTexts.cancel }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </main>
</template>

<style scoped src="~/assets/css/schedule-view.css"></style>
<style scoped src="~/assets/css/swap-select.css"></style>
