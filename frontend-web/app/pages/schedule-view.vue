<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'

definePageMeta({
  middleware: 'auth',
})

// -------------- Dependencies -----------
const { token } = useAuth()
const { shiftTypes, fetchShiftTypes } = useSchedule()
const { currentLocale, texts } = useScheduleTexts()


// ------- Scale Constants --------
const PX_PER_HOUR = 38
const PX_PER_MIN  = PX_PER_HOUR / 60


// --------------- State ----------------
const loading          = ref(false)
const error            = ref<string | null>(null)
const weeklyShifts     = ref<any[]>([])
const currentView      = ref<'personal' | 'global'>('personal')
const currentDate      = ref(new Date())
const gridBodyRef      = ref<HTMLElement | null>(null)
const isBannerVisible  = ref(true)
const isExportModalOpen = ref(false)
const isInstructionsModalOpen = ref(false)
const isCopied         = ref(false)
const icalInputRef     = ref<HTMLInputElement | null>(null)
const tooltipActive    = ref(false)
const tooltipShift     = ref<any>(null)
const tooltipUsers     = ref<any[]>([])
const tooltipX         = ref(0)
const tooltipY         = ref(0)

// Real time indicator
const timeIndicatorTop = ref('0px')
let timeIntervalId: any = null

const hoursList = [
  '00:00','01:00','02:00','03:00','04:00','05:00','06:00','07:00',
  '08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00',
  '16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00',
]

// ---------------- Texts ----------------
const viewTexts = computed(() => {
  const isPt = currentLocale.value === 'pt'
  return {
    pageTitle:        isPt ? 'Horário'       : 'Schedule',
    pageSubtitle:     isPt ? 'Consulte a sua escala de turnos ou a visão global da equipa' : 'Check your shift schedule or the global team view',
    personalView:     isPt ? 'Vista Pessoal'               : 'Personal View',
    globalView:       isPt ? 'Vista Global'                : 'Global View',
    today:            isPt ? 'Hoje'                        : 'Today',
    print:            isPt ? 'Imprimir'                    : 'Print',
    time:             isPt ? 'Horário'                     : 'Time',
    duration:         isPt ? 'Duração'                     : 'Duration',
    workShift:        isPt ? 'Turno de Trabalho'           : 'Work Shift',
    absenceShift:     isPt ? 'Folga / Ausência'            : 'Day Off / Absence',
    assignedNurses:   isPt ? 'Enfermeiros Escalados'       : 'Assigned Nurses',
    legendTitle:      isPt ? 'Turnos:'          : 'Shifts:',
    noAssignedNurses: isPt ? 'Nenhum enfermeiro escalado.' : 'No nurses assigned.',
    errorLoading:     isPt ? 'Erro ao carregar horário. Tente novamente.' : 'Error loading schedule. Please try again.',
  }
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
  const ptNames = ['Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo']
  const enNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']
  return currentLocale.value === 'pt' ? ptNames[(date.getDay() + 6) % 7] : enNames[(date.getDay() + 6) % 7]
}

const getDayOfMonth = (date: Date) => date.getDate()

const isToday = (date: Date) => {
  const today = new Date()
  return date.getDate() === today.getDate() &&
         date.getMonth() === today.getMonth() &&
         date.getFullYear() === today.getFullYear()
}

const isWeekend = (date: Date) => date.getDay() === 0 || date.getDay() === 6

const weekRangeLabel = computed(() => {
  const start = startOfWeek.value
  const end   = new Date(start)
  end.setDate(start.getDate() + 6)
  const monthsPt = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']
  const monthsEn = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  const sd = start.getDate(), ed = end.getDate()
  const sm = currentLocale.value === 'pt' ? monthsPt[start.getMonth()] : monthsEn[start.getMonth()]
  const em = currentLocale.value === 'pt' ? monthsPt[end.getMonth()]   : monthsEn[end.getMonth()]
  const sy = start.getFullYear(), ey = end.getFullYear()
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
const goToToday       = () => { currentDate.value = new Date() }
const goToPreviousWeek = () => { const d = new Date(currentDate.value); d.setDate(d.getDate() - 7); currentDate.value = d }
const goToNextWeek     = () => { const d = new Date(currentDate.value); d.setDate(d.getDate() + 7); currentDate.value = d }

// ---------------- Translation ----------------------
const getShiftName = (shiftType: any) => {
  if (!shiftType) return ''
  const name = (shiftType.name || '').toLowerCase()
  const pt: Record<string,string> = { morning:'Manhã',afternoon:'Tarde',night:'Noite',dayoff:'Folga','day off':'Folga',holidays:'Férias','sick leave':'Baixa Médica','parental leave':'Licença Parental' }
  const en: Record<string,string> = { morning:'Morning',afternoon:'Afternoon',night:'Night',dayoff:'Day Off','day off':'Day Off',holidays:'Holidays','sick leave':'Sick Leave','parental leave':'Parental Leave' }
  return currentLocale.value === 'pt' ? (pt[name] || shiftType.name) : (en[name] || shiftType.name)
}

// ---------------------- Shifts logic -----------------------
const isAllDay = (shiftType: any) => {
  if (!shiftType) return true
  const name = (shiftType.name || '').toLowerCase()
  return ['dayoff','day off','folga','holidays','férias','sick leave','baixa médica','parental leave','licença parental'].includes(name)
    || (shiftType.start_time === '00:00:00' && shiftType.end_time === '00:00:00')
}

const getDayAllDayGroups = (dateStr: string) => {
  const dayShifts = weeklyShifts.value.filter(s => s.date === dateStr && isAllDay(s.shift_type))
  const groups: Record<number, { shift_type: any; users: any[]; shift: any }> = {}
  dayShifts.forEach(shift => {
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
  const endH   = parseInt(endTime.split(':')[0] ?? '0')
  const endM   = parseInt(endTime.split(':')[1] ?? '0')
  return endH < startH || (endH === startH && endM < startM)
}


const getDayTimedSegments = (dateStr: string) => {
  const segments: any[] = []

  // Shifts that start today
  const dayShifts = weeklyShifts.value.filter(s => s.date === dateStr && !isAllDay(s.shift_type))
  dayShifts.forEach(shift => {
    const st = shift.shift_type
    const startTime:string = st.start_time || '08:00:00'
    const endTime:string = st.end_time   || '16:00:00'
    const startH = parseInt(startTime.split(':')[0] ?? '0')
    const startM = parseInt(startTime.split(':')[1] ?? '0')
    const startMins = startH * 60 + startM

    if (isMidnightCrossing(startTime, endTime)) {
      // Crosses midnight → Seg1: from start to 00:00
      segments.push({ id: `${shift.id}_seg1`, shift, startMins, endMins: 1440, isSeg2: false })
    } else {
      // Normal or shift that ends at midnight (00:00:00 → endH = 24)
      let endH = parseInt(endTime.split(':')[0] ?? '0')
      const endM = parseInt(endTime.split(':')[1] ?? '0')
      if (endTime === '00:00:00') endH = 24
      segments.push({ id: `${shift.id}_normal`, shift, startMins, endMins: endH * 60 + endM, isSeg2: false })
    }
  })

  // Seg2: rest of the shift from the last day that started before midght and continues today
  const [y = 0, mo = 0, d = 0] = dateStr.split('-').map(Number)
  const prevDateStr = getLocalDateStr(new Date(y, mo - 1, d - 1))
  const prevDayShifts = weeklyShifts.value.filter(s => s.date === prevDateStr && !isAllDay(s.shift_type))
  prevDayShifts.forEach(shift => {
    const st = shift.shift_type
    const startTime: string = st.start_time || '08:00:00'
    const endTime:   string = st.end_time   || '16:00:00'
    if (isMidnightCrossing(startTime, endTime)) {
      const endH  = parseInt(endTime.split(':')[0] ?? '0')
      const endM  = parseInt(endTime.split(':')[1] ?? '0')
      segments.push({ id: `${shift.id}_seg2`, shift, startMins: 0, endMins: endH * 60 + endM, isSeg2: true })
    }
  })

  // Greedy Interval Scheduling
  const sorted = [...segments].sort((a, b) =>
    a.startMins - b.startMins || a.id.localeCompare(b.id)
  )
  const laneEndTimes: number[] = []
  for (const seg of sorted) {
    let lane = laneEndTimes.findIndex(et => et <= seg.startMins)
    if (lane === -1) {
      lane = laneEndTimes.length
      laneEndTimes.push(seg.endMins)
    } else {
      laneEndTimes[lane] = seg.endMins
    }
    seg.lane = lane
  }
  const totalLanes = laneEndTimes.length || 1
  segments.forEach(seg => { seg.totalLanes = totalLanes })

  return segments
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
  const end   = st.end_time.slice(0, 5)
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

const getShortNursesList = (users: any[]): string => {
  if (!users?.length) return ''
  const names = users.map(u => u.name.split(' ')[0])
  if (names.length <= 2) return names.join(', ')
  return `${names[0]}, ${names[1]} +${names.length - 2}`
}


const fetchWeeklySchedule = async () => {
  loading.value = true
  error.value   = null
  try {
    const config   = useRuntimeConfig()
    const response = await $fetch<{ data: { shifts: any[] } }>(`${config.public.apiBase}/schedules/weekly`, {
      headers: { Authorization: `Bearer ${token.value}` },
      query: { date: getLocalDateStr(startOfWeek.value), view: currentView.value },
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
  return `#${R.toString(16).padStart(2,'0')}${G.toString(16).padStart(2,'0')}${B.toString(16).padStart(2,'0')}`
}

const getColorVars = (color: string | null | undefined) => {
  const bg = color || '#e2e8f0'
  return { '--item-bg': bg, '--item-border': adjustColorBrightness(bg, -25) }
}

const getShiftCSSVars = (segment: any) => {
  const shiftType = segment.shift.shift_type
  if (!shiftType) return {}

  const topPx    = segment.startMins * PX_PER_MIN
  const heightPx = Math.max(12, (segment.endMins - segment.startMins) * PX_PER_MIN)

  const lane       = segment.lane       ?? 0
  const totalLanes = segment.totalLanes ?? 1
  const cardWidth  = totalLanes > 1 ? `calc(${100 / totalLanes}% - 4px)` : 'calc(100% - 4px)'
  const cardLeft   = totalLanes > 1 ? `calc(${(lane * 100) / totalLanes}% + 2px)` : '2px'

  const bg     = shiftType.color || '#e2e8f0'
  const border = adjustColorBrightness(bg, -25)

  return {
    '--card-top':    `${topPx.toFixed(1)}px`,
    '--card-height': `${heightPx.toFixed(1)}px`,
    '--card-left':   cardLeft,
    '--card-width':  cardWidth,
    '--card-bg':     bg,
    '--card-border': border,
  }
}

// --------- Real time grid -------------
const isTimeWithinGrid = computed(() => true)

const updateTimeIndicator = () => {
  const now  = new Date()
  const mins = now.getHours() * 60 + now.getMinutes()
  timeIndicatorTop.value = `${(mins * PX_PER_MIN).toFixed(1)}px`
}

// ---------------- Tooltip --------------
const showTooltip = (event: MouseEvent, shift: any, users: any[]) => {
  tooltipShift.value  = shift
  tooltipUsers.value  = users || []
  tooltipActive.value = true
  updateTooltipPosition(event)
}

const updateTooltipPosition = (event: MouseEvent) => {
  if (!tooltipActive.value) return
  let x = event.clientX + 15, y = event.clientY + 15
  if (x + 290 > window.innerWidth)  x = event.clientX - 290 - 15
  if (y + 240 > window.innerHeight) y = event.clientY - 240 - 15
  tooltipX.value = x
  tooltipY.value = y
}

const hideTooltip = () => {
  tooltipActive.value = false
  tooltipShift.value  = null
  tooltipUsers.value  = []
}

// ------------------ iCal & Print ------------------
const mockIcalLink = computed(() => {
  if (!process.client) return ''
  
  // hold the current IP address 
  const host = window.location.hostname
  
  // Força a usar a porta 8000 que é onde está o teu Laravel (Backend)
  // NOTA: Quando o site for para produção num domínio real, tirar o ":8000"
  const backendUrl = `http://${host}:8000/api`
  
  return `${backendUrl}/schedules/ical?token=${token.value}`
})

const copyIcalLink = async () => {
  if (!icalInputRef.value) return
  try {
    // Tries to use the modern API (only works on HTTPS or localhost)
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(icalInputRef.value.value)
    } else {
      // Fallback for older browsers (e.g., 190.108.x.x)
      icalInputRef.value.select()
      document.execCommand('copy')
      window.getSelection()?.removeAllRanges()
    }
    
    isCopied.value = true
    setTimeout(() => { isCopied.value = false }, 2000)
  } catch (err) { 
    console.error('Falha ao copiar o link:', err) 
  }
}

const printSchedule = () => { if (process.client) window.print() }

// ------------------- Lifecycle -------------------
watch([currentView, startOfWeek], fetchWeeklySchedule)

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

onBeforeUnmount(() => { if (timeIntervalId) clearInterval(timeIntervalId) })
</script>

<template>
  <main class="dashboard-layout schedule-view-page">
    <AppNavbar />

    <section class="dashboard-content">
      <div class="uc-top-bar">
        <div class="uc-title-group">
          <NuxtLink to="/dashboard" class="back-link">
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

      <!-- Loader -->
      <div v-if="loading && weeklyShifts.length === 0" class="schedule-view-state hr-card">
        <div class="spinner"></div>
        <p>{{ texts.loading }}</p>
      </div>

      <!-- Error -->
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
        <!-- iCal Banner -->
        <transition name="fade">
          <div v-if="isBannerVisible" class="ical-export-banner">
            <div class="banner-content">
              <div class="banner-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="16" x2="12" y2="12"></line>
                  <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
              </div>
              <p>
                <span v-if="currentLocale === 'pt'">
                  Pode exportar o seu horário para aplicações externas com suporte para iCalendar, tais como Google Calendar, iCal, etc.
                  <a href="#" @click.prevent="isInstructionsModalOpen = true" class="banner-link">Saiba como fazer carregando aqui.</a>
                </span>
                <span v-else>
                  You can export your schedule to external applications with iCalendar support, such as Google Calendar, iCal, etc.
                  <a href="#" @click.prevent="isInstructionsModalOpen = true" class="banner-link">Learn how by clicking here.</a>
                </span>
              </p>
            </div>
            <button class="banner-close" @click="isBannerVisible = false" :aria-label="currentLocale === 'pt' ? 'Fechar' : 'Close'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
        </transition>

        <!-- Controls -->
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

          <div class="view-actions">
            <button class="nav-btn ical-info-btn" :title="currentLocale === 'pt' ? 'Sincronizar Calendário' : 'Sync Calendar'" @click="isExportModalOpen = true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="16" height="16">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              <span>iCal</span>
            </button>

            <div class="view-toggle">
              <button class="toggle-btn" :class="{ active: currentView === 'personal' }" @click="currentView = 'personal'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="14" height="14">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>{{ viewTexts.personalView }}</span>
              </button>
              <button class="toggle-btn" :class="{ active: currentView === 'global' }" @click="currentView = 'global'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="14" height="14">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>{{ viewTexts.globalView }}</span>
              </button>
            </div>

            <button class="nav-btn print-btn" @click="printSchedule">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="16" height="16">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
              </svg>
              <span>{{ viewTexts.print }}</span>
            </button>
          </div>
        </div>

        <!-- Legend -->
        <div class="schedule-legend" v-if="shiftTypes && shiftTypes.length > 0">
          <span class="schedule-legend__title">{{ viewTexts.legendTitle }}</span>
          <div class="schedule-legend__items">
            <div
              v-for="type in shiftTypes"
              :key="type.id"
              class="schedule-legend__item"
              :style="getColorVars(type.color)"
            >
              {{ getShiftName(type) }}
              <span v-if="type.start_time && type.start_time !== '00:00:00'" class="legend-time">
                ({{ type.start_time.slice(0, 5) }} - {{ type.end_time.slice(0, 5) }})
              </span>
            </div>
          </div>
        </div>

        <!-- Weekly grid -->
        <div class="weekly-grid-container hr-card">

          <!-- Header -->
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

          <!-- Full day line -->
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
                  :style="getColorVars(group.shift_type.color)"
                  @mouseenter="showTooltip($event, group.shift, group.users)"
                  @mousemove="updateTooltipPosition"
                  @mouseleave="hideTooltip"
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

          <!-- Body with Scroll -->
          <div class="weekly-grid-body" ref="gridBodyRef">

            <!-- Hour column -->
            <div class="time-column">
              <div v-for="hour in hoursList" :key="hour" class="time-slot-label">
                <span>{{ hour }}</span>
              </div>
            </div>

            <!-- Weekly grid -->
            <div class="days-grid">
              <!-- Background lines -->
              <div class="grid-bg-lines">
                <div v-for="hour in hoursList" :key="hour" class="grid-bg-line"></div>
              </div>

              <!-- Columns by day -->
              <div
                v-for="day in weekDays"
                :key="getLocalDateStr(day)"
                class="day-column"
                :class="{ 'is-today': isToday(day), 'is-weekend': isWeekend(day) }"
              >
                <!-- Real time indicator -->
                <div v-if="isToday(day) && isTimeWithinGrid" class="time-indicator" :style="{ top: timeIndicatorTop }">
                  <div class="time-indicator-circle"></div>
                  <div class="time-indicator-line"></div>
                </div>

                <!-- Shift Cards -->
                <div
                  v-for="segment in getDayTimedSegments(getLocalDateStr(day))"
                  :key="segment.id"
                  class="shift-card"
                  :class="{
                    'shift-card--seg1': segment.id.includes('_seg1'),
                    'shift-card--seg2': segment.isSeg2,
                    'shift-card--tarde': !segment.isSeg2 && (
                      segment.shift.shift_type?.end_time?.startsWith('00:00') || 
                      segment.shift.shift_type?.end_time?.startsWith('24:00') || 
                      segment.shift.shift_type?.name?.toLowerCase()?.includes('tarde') || 
                      segment.shift.shift_type?.name?.toLowerCase()?.includes('afternoon')
                    )
                  }"
                  :style="getShiftCSSVars(segment)"
                  @mouseenter="showTooltip($event, segment.shift, segment.shift.users)"
                  @mousemove="updateTooltipPosition"
                  @mouseleave="hideTooltip"
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

    <!-- Flutuant Tooltip -->
    <div
      v-if="tooltipActive && tooltipShift"
      class="schedule-view-tooltip"
      :style="{ left: tooltipX + 'px', top: tooltipY + 'px' }"
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

    <!-- Modal iCal -->
    <transition name="fade">
      <div v-if="isExportModalOpen" class="modal-overlay" @click.self="isExportModalOpen = false">
        <div class="modal-card">
          <div class="modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
          </div>
          <h2>{{ currentLocale === 'pt' ? 'Sincronizar com Calendário Externo' : 'Sync with External Calendar' }}</h2>
          <div class="modal-info-content">
            <p class="modal-intro">
              {{ currentLocale === 'pt'
                  ? 'Copie o link abaixo e cole num novo separador do browser'
                  : 'Copy the link below and paste it in a new tab of your browser' }}
            </p>
            <ol class="ical-steps">
                <div class="copy-link-container">
                  <input type="text" readonly :value="mockIcalLink" class="ical-link-input" ref="icalInputRef" />
                  <button class="copy-btn" @click="copyIcalLink" :class="{ copied: isCopied }">
                    {{ isCopied
                        ? (currentLocale === 'pt' ? 'Copiado!' : 'Copied!')
                        : (currentLocale === 'pt' ? 'Copiar' : 'Copy') }}
                  </button>
                </div>
            </ol>
          </div>
          <div class="modal-actions">
            <button class="modal-btn confirm" @click="isExportModalOpen = false">
              {{ currentLocale === 'pt' ? 'Fechar' : 'Close' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
        <!-- Modal Instruções Passo a Passo -->
    <transition name="fade">
      <div v-if="isInstructionsModalOpen" class="modal-overlay" @click.self="isInstructionsModalOpen = false">
        <div class="modal-card">
          <div class="modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
          </div>
          <h2>{{ currentLocale === 'pt' ? 'Como sincronizar o horário?' : 'How to sync the schedule?' }}</h2>
          <div class="modal-info-content" style="max-height: 400px; overflow-y: auto; text-align: left;">
            <p class="modal-intro">
              {{ currentLocale === 'pt' 
                 ? 'Siga estes passos simples para ter o seu horário de trabalho sempre atualizado nas suas aplicações pessoais.'
                 : 'Follow these simple steps to keep your work schedule always up to date in your personal apps.' }}
            </p>
            
            <h3 style="margin: 10px 0 5px; font-size: 1rem; color: var(--text);">
              {{ currentLocale === 'pt' ? 'No iPhone / iPad (Apple Calendar)' : 'On iPhone / iPad (Apple Calendar)' }}
            </h3>
            <ol class="ical-steps" style="margin-bottom: 15px;">
              <li><span v-html="currentLocale === 'pt' ? 'Clique no botão <strong>iCal</strong> nesta página e copie o link gerado.' : 'Click the <strong>iCal</strong> button on this page and copy the generated link.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'No seu iPhone, abra o browser.' : 'On your iPhone, open the browser.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Cole o link na barra de pesquisa e prima <strong>Ir</strong>.' : 'Paste the link in the search bar and press <strong>Enter</strong>.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'O telemóvel vai perguntar se pretende subscrever o calendário. Carregue em <strong>Subscrever</strong>.' : 'The phone will ask if you want to subscribe to the calendar. Tap <strong>Subscribe</strong>.'"></span></li>
            </ol>

            <h3 style="margin: 10px 0 5px; font-size: 1rem; color: var(--text);">
              {{ currentLocale === 'pt' ? 'No Google Calendar (Android / PC)' : 'On Google Calendar (Android / PC)' }}
            </h3>
            <ol class="ical-steps">
              <li><span v-html="currentLocale === 'pt' ? 'Clique no botão <strong>iCal</strong> nesta página e copie o link gerado.' : 'Click the <strong>iCal</strong> button on this page and copy the generated link.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Cole o link na barra de pesquisa e prima <strong>Ir</strong>. Será descarregado um ficheiro .ics.' : 'Paste the link in the search bar and press <strong>Enter</strong>. It will download a .ics file.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Abra o  <strong>Google Calendar</strong>.' : 'Open the <strong>Google Calendar</strong>.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'No menu do lado esquerdo, ao lado de Outros calendários, clique no sinal <strong>+</strong> e escolha <strong>Importar</strong>.' : 'In the left menu, next to Other calendars, click the <strong>+</strong> sign and choose <strong>Import</strong>.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Importe o ficheiro .ics descarregado e clique em <strong>Adicionar agenda</strong>.' : 'Import the downloaded .ics file and click <strong>Add calendar</strong>.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'No seu telemóvel Android, abra a app Calendário, vá às definições e sincronize para ver as alterações.' : 'On your Android phone, open the Calendar app, go to settings and sync to see the changes.'"></span></li>
            </ol>
          </div>
          <div class="modal-actions">
            <button class="modal-btn confirm" @click="isInstructionsModalOpen = false">
              {{ currentLocale === 'pt' ? 'Entendido' : 'Got it' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </main>
</template>

<style scoped src="~/assets/css/schedule-view.css"></style>