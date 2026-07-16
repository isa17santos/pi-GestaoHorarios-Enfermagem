<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'

const isGeneratingPdf = ref(false)
const monthlyWeeks = ref<any[]>([])

definePageMeta({
  middleware: 'auth',
})

// -------------- Dependencies -----------
const { token } = useAuth()
const { shiftTypes, fetchShiftTypes } = useSchedule()
const { currentLocale, texts } = useScheduleTexts()


// ------- Scale Constants --------
const PX_PER_HOUR = 26
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

// Polling silencioso para detetar atualizações de horário em tempo real
const hasScheduleChanges = ref(false)
const lastShiftsHash = ref<string | null>(null)
let checkIntervalId: any = null
const refreshPage = () => {
  if (process.client) {
    window.location.reload()
  }
}
const pollWeeklySchedule = async () => {
  // Verifies only if it is not loading, if there are no errors and if no changes have been detected
  if (loading.value || hasScheduleChanges.value || error.value) return
  try {
    const config   = useRuntimeConfig()
    const response = await $fetch<{ data: { shifts: any[] } }>(`${config.public.apiBase}/schedules/weekly`, {
      headers: { Authorization: `Bearer ${token.value}` },
      query: { date: getLocalDateStr(startOfWeek.value), view: currentView.value },
    })
    
    const newShifts = response.data.shifts || []
    const newHash = JSON.stringify(newShifts)
    
    // If we already have data loaded in the session and the new hash is different, alert the user
    if (lastShiftsHash.value !== null && newHash !== lastShiftsHash.value) {
      hasScheduleChanges.value = true
    }
  } catch (err) {
    console.warn('Erro silencioso ao verificar atualização do horário:', err)
  }
}

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
  hasScheduleChanges.value = false 
  try {
    const config   = useRuntimeConfig()
    const response = await $fetch<{ data: { shifts: any[] } }>(`${config.public.apiBase}/schedules/weekly`, {
      headers: { Authorization: `Bearer ${token.value}` },
      query: { date: getLocalDateStr(startOfWeek.value), view: currentView.value },
    })
    weeklyShifts.value = response.data.shifts || []
    lastShiftsHash.value = JSON.stringify(response.data.shifts || [])
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

// ------------------ iCal , PDF & Print ------------------
const mockIcalLink = computed(() => {
  if (!process.client) return ''
  
  // hold the current IP address 
  const host = window.location.hostname
  
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

const goToPrintMonth = () => {
  window.open(`/print-month?date=${getLocalDateStr(startOfWeek.value)}&view=${currentView.value}`, '_blank')
}


const getAllNursesList = (users: any[], fallback = 'Sem enfermeiros'): string => {
  if (!users || users.length === 0) return fallback
  return users.map(u => u.name).join(', ')
}

const printSchedule = async () => {
  if (isGeneratingPdf.value) return
  isGeneratingPdf.value = true // Reuses the loading state to avoid double clicks
  
  const queryDate = new Date(startOfWeek.value)
  const view = currentView.value
  const year = queryDate.getFullYear()
  const month = queryDate.getMonth()
  
  const weeks = []
  let d = new Date(year, month, 1)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  d.setDate(diff)
  
  while (d.getMonth() === month || d < new Date(year, month + 1, 0)) {
    weeks.push(new Date(d))
    d.setDate(d.getDate() + 7)
  }

  const config = useRuntimeConfig()
  try {
    const promises = weeks.map(wDate => 
      $fetch<{ data: { shifts: any[] } }>(`${config.public.apiBase}/schedules/weekly`, {
        headers: { Authorization: `Bearer ${token.value}` },
        query: { date: getLocalDateStr(wDate), view },
      })
    )
    const responses = await Promise.all(promises)
    
    monthlyWeeks.value = weeks.map((weekStartDate, i) => {
      return {
        startDate: weekStartDate,
        weekDays: Array.from({ length: 7 }, (_, idx) => {
          const wd = new Date(weekStartDate)
          wd.setDate(weekStartDate.getDate() + idx)
          return wd
        }),
        shifts: responses[i]?.data?.shifts || []
      }
    })

    await nextTick()
    
    setTimeout(() => {
      if (process.client) {
        window.print()
      }
      isGeneratingPdf.value = false
      monthlyWeeks.value = [] // Cleans the memory after closing the print dialog
    }, 500)

  } catch (err) {
    console.error("Erro ao preparar impressão mensal:", err)
    isGeneratingPdf.value = false
    monthlyWeeks.value = []
  }
}


// ------------------- Lifecycle -------------------
watch([currentView, startOfWeek], fetchWeeklySchedule)

onMounted(async () => {
  updateTimeIndicator()
  timeIntervalId = setInterval(updateTimeIndicator, 60000)

  // Iniciates the periodic verification every 15 seconds
  checkIntervalId = setInterval(pollWeeklySchedule, 15000)

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
  if (checkIntervalId) clearInterval(checkIntervalId) 
})

const getPrintDayAllDayGroups = (dateStr: string, shifts: any[]) => {
  const dayShifts = shifts.filter(s => s.date === dateStr && isAllDay(s.shift_type))
  const groups: Record<number, any> = {}
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

const getPrintDayTimedSegments = (dateStr: string, shifts: any[]) => {
  const segments: any[] = []
  const dayShifts = shifts.filter(s => s.date === dateStr && !isAllDay(s.shift_type))
  
  dayShifts.forEach(shift => {
    const st = shift.shift_type
    const startTime:string = st.start_time || '08:00:00'
    const endTime:string = st.end_time   || '16:00:00'
    const startH = parseInt(startTime.split(':')[0] ?? '0')
    const startM = parseInt(startTime.split(':')[1] ?? '0')
    const startMins = startH * 60 + startM

    if (isMidnightCrossing(startTime, endTime)) {
      segments.push({ id: `${shift.id}_seg1`, shift, startMins, endMins: 1440, isSeg2: false })
    } else {
      let endH = parseInt(endTime.split(':')[0] ?? '0')
      const endM = parseInt(endTime.split(':')[1] ?? '0')
      if (endTime === '00:00:00') endH = 24
      segments.push({ id: `${shift.id}_normal`, shift, startMins, endMins: endH * 60 + endM, isSeg2: false })
    }
  })

  const [y = 0, mo = 0, d = 0] = dateStr.split('-').map(Number)
  const prevDateStr = getLocalDateStr(new Date(y, mo - 1, d - 1))
  const prevDayShifts = shifts.filter(s => s.date === prevDateStr && !isAllDay(s.shift_type))
  
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

  const sorted = [...segments].sort((a, b) => a.startMins - b.startMins || a.id.localeCompare(b.id))
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

const getWeekRangeLabel = (start: Date) => {
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
}

const generateMonthlyPdf = async () => {
  if (isGeneratingPdf.value) return
  isGeneratingPdf.value = true
  
  const queryDate = new Date(startOfWeek.value)
  const view = currentView.value
  const year = queryDate.getFullYear()
  const month = queryDate.getMonth()
  
  const weeks = []
  let d = new Date(year, month, 1)
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  d.setDate(diff)
  
  while (d.getMonth() === month || d < new Date(year, month + 1, 0)) {
    weeks.push(new Date(d))
    d.setDate(d.getDate() + 7)
  }

  const config = useRuntimeConfig()
  try {
    const promises = weeks.map(wDate => 
      $fetch<{ data: { shifts: any[] } }>(`${config.public.apiBase}/schedules/weekly`, {
        headers: { Authorization: `Bearer ${token.value}` },
        query: { date: getLocalDateStr(wDate), view },
      })
    )
    const responses = await Promise.all(promises)
    
    monthlyWeeks.value = weeks.map((weekStartDate, i) => {
      return {
        startDate: weekStartDate,
        weekDays: Array.from({ length: 7 }, (_, idx) => {
          const wd = new Date(weekStartDate)
          wd.setDate(weekStartDate.getDate() + idx)
          return wd
        }),
        shifts: responses[i]?.data?.shifts || []
      }
    })

    await nextTick()
    
    const html2canvasModule = (await import('html2canvas')) as any
    const html2canvas = html2canvasModule.default || html2canvasModule
    const jspdfModule = (await import('jspdf')) as any
    const jsPDFConstructor = jspdfModule.jsPDF || jspdfModule.default?.jsPDF || jspdfModule.default || jspdfModule
    
    setTimeout(async () => {
      try {
        const pages = document.querySelectorAll('.pdf-page')
        if (pages.length === 0) {
          throw new Error("Nenhuma página encontrada para gerar.")
        }

        const pdf = new jsPDFConstructor({
          orientation: 'portrait',
          unit: 'mm',
          format: 'a4'
        })

        for (let i = 0; i < pages.length; i++) {
          const pageElement = pages[i] as HTMLElement

          const canvas = await html2canvas(pageElement, {
            scale: 2,
            useCORS: true,
            logging: false,
            windowWidth: 1200,
            width: 1200,
            height: 1600
          })

          const imgData = canvas.toDataURL('image/jpeg', 0.95)

          if (i > 0) {
            pdf.addPage()
          }

          pdf.addImage(imgData, 'JPEG', 0, 0, 210, 297)
        }

        pdf.save(`horario_mensal_${month + 1}_${year}.pdf`)

        isGeneratingPdf.value = false
        monthlyWeeks.value = [] 
      } catch (err) {
        console.error("Erro interno ao gerar o PDF:", err)
        isGeneratingPdf.value = false
      }
    }, 1500)

  } catch (err) {
    console.error("Erro ao buscar dados mensais:", err)
    isGeneratingPdf.value = false
  }
}
</script>

<template>
    <main class="dashboard-layout schedule-view-page" :class="{ 'is-personal-view': currentView === 'personal', 'is-global-view': currentView === 'global' }">
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
        <!-- Banner Warning of changes in real time -->
        <transition name="fade">
          <div v-if="hasScheduleChanges" class="schedule-alert-banner">
            <div class="alert-content">
              <div class="alert-icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20" class="pulse-icon">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                  <line x1="12" y1="9" x2="12" y2="13"></line>
                  <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
              </div>
              <p class="alert-message">
                <span v-if="currentLocale === 'pt'">
                  Este horário sofreu alterações recentes. Por favor, atualize a página para ver a versão mais recente.
                </span>
                <span v-else>
                  This schedule has been recently updated. Please refresh the page to view the latest version.
                </span>
              </p>
            </div>
            <button class="alert-btn" @click="refreshPage">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
              </svg>
              {{ currentLocale === 'pt' ? 'Atualizar' : 'Refresh' }}
            </button>
          </div>
        </transition>

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

            <button class="nav-btn ical-info-btn" style="margin-left: 8px;" :title="currentLocale === 'pt' ? 'Exportar Mês PDF' : 'Export Month PDF'" @click="generateMonthlyPdf" :disabled="isGeneratingPdf">
              <svg v-if="!isGeneratingPdf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="16" height="16">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
              </svg>
              <span>{{ isGeneratingPdf ? (currentLocale === 'pt' ? 'A gerar...' : 'Generating...') : 'PDF' }}</span>
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
              <span v-if="type.start_time && type.start_time !== '00:00:00'" class="legend-time"></span>
              <span v-if="type.start_time && !isAllDay(type)" class="legend-time">
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

        <!-- Print -->
        <div class="print-summary-section" :class="{ 'print-summary--global': currentView === 'global', 'print-summary--personal': currentView === 'personal' }">
          <h3 class="print-summary-title">
            {{ currentLocale === 'pt' ? 'Distribuição de Turnos por Dia' : 'Daily Shift Distribution' }}
          </h3>
          <div class="print-summary-grid">
            <div v-for="day in weekDays" :key="'print-legend-' + getLocalDateStr(day)" class="print-summary-day-box">
              <strong class="print-summary-day-title">
                {{ getDayOfWeekName(day) }}, {{ getDayOfMonth(day) }}
              </strong>
              
              <div class="print-summary-shifts-list">
                <div v-for="(group, idx) in getPrintDayAllDayGroups(getLocalDateStr(day), weeklyShifts)" :key="'print-leg-all-' + idx" class="print-summary-shift-item">
                  <span class="print-summary-shift-bullet" :style="{ backgroundColor: group.shift_type.color || '#cbd5e1' }"></span>
                  <div>
                    <span class="print-summary-shift-name">{{ getShiftName(group.shift_type) }}:</span>
                    <span class="print-summary-shift-users">{{ group.users.length > 0 ? getAllNursesList(group.users, '-') : '-' }}</span>
                  </div>
                </div>
                
                <div v-for="seg in getPrintDayTimedSegments(getLocalDateStr(day), weeklyShifts)" :key="'print-leg-timed-' + seg.id" class="print-summary-shift-item">
                  <span class="print-summary-shift-bullet" :style="{ backgroundColor: seg.shift.shift_type.color || '#cbd5e1' }"></span>
                  <div>
                    <span class="print-summary-shift-name">
                      {{ getShiftName(seg.shift.shift_type) }}
                      <span v-if="currentView === 'global'" class="print-summary-shift-hours">
                        ({{ formatShiftTime(seg.shift.shift_type) }})
                      </span>:
                    </span>
                    <span class="print-summary-shift-users">{{ seg.shift.users.length > 0 ? getAllNursesList(seg.shift.users, 'Sem enfermeiros') : 'Sem enfermeiros' }}</span>
                  </div>
                </div>
                
                <div v-if="getPrintDayAllDayGroups(getLocalDateStr(day), weeklyShifts).length === 0 && getPrintDayTimedSegments(getLocalDateStr(day), weeklyShifts).length === 0" class="print-summary-no-shifts">
                  {{ currentLocale === 'pt' ? 'Sem turnos registados' : 'No shifts scheduled' }}
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
        <!-- Modal Instructions -->
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
              <li><span v-html="currentLocale === 'pt' ? 'Cole o link na barra de pesquisa e prima <strong>Ir</strong>.' : 'Paste the link in the search bar and press <strong>Enter</strong>.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Será imediatamente aberto o horário no calendário. Basta salvar.' : 'The schedule will be immediately opened in the calendar. Just save it.'"></span></li>
            </ol>

            <h3 style="margin: 10px 0 5px; font-size: 1rem; color: var(--text);">
              {{ currentLocale === 'pt' ? 'No Android' : 'On Android' }}
            </h3>
            <ol class="ical-steps">
              <li><span v-html="currentLocale === 'pt' ? 'Clique no botão <strong>iCal</strong> nesta página e copie o link gerado.' : 'Click the <strong>iCal</strong> button on this page and copy the generated link.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Cole o link na barra de pesquisa e prima <strong>Ir</strong>. Será descarregado um ficheiro .ics.' : 'Paste the link in the search bar and press <strong>Enter</strong>. It will download a .ics file.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Abra o fichero transferido com a sua aplicação do calendário e carregue em <strong>Salvar</strong>.' : 'Open the transfered file with your calendar app and click <strong>Save</strong>.'"></span></li>
            </ol>

            <h3 style="margin: 10px 0 5px; font-size: 1rem; color: var(--text);">
              {{ currentLocale === 'pt' ? 'No Google Calendar' : 'On Google Calendar' }}
            </h3>
            <ol class="ical-steps">
              <li><span v-html="currentLocale === 'pt' ? 'Clique no botão <strong>iCal</strong> nesta página e copie o link gerado.' : 'Click the <strong>iCal</strong> button on this page and copy the generated link.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Cole o link na barra de pesquisa e prima <strong>Ir</strong>. Será descarregado um ficheiro .ics.' : 'Paste the link in the search bar and press <strong>Enter</strong>. It will download a .ics file.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Abra o  <strong>Google Calendar</strong>.' : 'Open the <strong>Google Calendar</strong>.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'No menu do lado esquerdo, ao lado de Outros calendários, clique no sinal <strong>+</strong> e escolha <strong>Importar</strong>.' : 'In the left menu, next to Other calendars, click the <strong>+</strong> sign and choose <strong>Import</strong>.'"></span></li>
              <li><span v-html="currentLocale === 'pt' ? 'Importe o ficheiro .ics descarregado e clique em <strong>Adicionar agenda</strong>.' : 'Import the downloaded .ics file and click <strong>Add calendar</strong>.'"></span></li>
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

        <!-- PDF  -->
    <div v-if="monthlyWeeks.length > 0" class="monthly-pdf-container" style="position: absolute; left: -9999px; top: 0; width: 1200px; z-index: -1;">
      <div id="pdf-content" style="background: white; padding: 0; margin: 0; width: 1200px; box-sizing: border-box;">
        <div v-for="(weekData, index) in monthlyWeeks" :key="index">
          
          <!-- Personal view -->
          <div v-if="currentView === 'personal'" class="pdf-page" style="width: 1200px; height: 1600px; box-sizing: border-box; padding: 45px 50px; background: white; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-start; margin: 0;">
            <!-- Header -->
            <div class="pdf-page-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 25px;">
              <img src="~/assets/images/logotipo.png" alt="ShiftCare" style="height: 42px;" />
              <div style="text-align: right;">
                <h2 style="margin: 0; font-size: 1.4rem; font-weight: 800; color: #0f172a; font-family: inherit;">
                  {{ currentLocale === 'pt' ? 'Escala Mensal de Turnos' : 'Monthly Shift Schedule' }}
                </h2>
                <span style="font-size: 0.95rem; font-weight: 600; color: #64748b; display: block; margin-top: 2px; font-family: inherit;">
                  {{ getWeekRangeLabel(weekData.startDate) }}
                </span>
              </div>
            </div>

            <!-- Grid -->
            <div class="weekly-grid-container" style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;">
              
              <!-- Days Header -->
              <div class="weekly-grid-header" style="background: #f8fafc; border-bottom: 1px solid #cbd5e1;">
                <div class="time-header-spacer" style="border-right: 1px solid #cbd5e1;"></div>
                <div class="days-header-grid">
                  <div v-for="day in weekData.weekDays" :key="getLocalDateStr(day)" class="day-header" :class="{ 'is-weekend': isWeekend(day) }">
                    <span class="day-name" style="font-weight: 700; color: #64748b;">{{ getDayOfWeekName(day) }}</span>
                    <span class="day-date" style="font-weight: 800; color: #0f172a; margin-top: 4px;">{{ getDayOfMonth(day) }}</span>
                  </div>
                </div>
              </div>

              <!-- All day shifts line -->
              <div class="weekly-grid-allday" style="background: #ffffff; border-bottom: 1px solid #cbd5e1;">
                <div class="allday-label-spacer" style="border-right: 1px solid #cbd5e1;">
                  <span style="font-weight: 700; color: #64748b;">{{ currentLocale === 'pt' ? 'Dia inteiro' : 'All day' }}</span>
                </div>
                <div class="allday-columns-grid">
                  <div v-for="day in weekData.weekDays" :key="'allday-' + getLocalDateStr(day)" class="allday-column" :class="{ 'is-weekend': isWeekend(day) }">
                    <div v-for="(group, idx) in getPrintDayAllDayGroups(getLocalDateStr(day), weekData.shifts)" :key="idx" class="allday-badge"
                      :style="{
                        backgroundColor: group.shift_type.color || '#f8fafc',
                        borderLeftColor: adjustColorBrightness(group.shift_type.color || '#cbd5e1', -30),
                        borderLeftWidth: '5px',
                        borderLeftStyle: 'solid',
                        boxShadow: '0 1px 2px rgba(0,0,0,0.05)'
                      }">
                      <span class="allday-badge-name" style="font-weight: 700; color: #0f172a;">{{ getShiftName(group.shift_type) }}</span>
                      <div class="allday-badge-count" style="color: #0f172a; font-weight: 600;">
                        {{ group.users.length }}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10">
                          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                          <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- grids main body -->
              <div class="weekly-grid-body" style="background: #ffffff; border: none; height: 624px; max-height: 624px; overflow: hidden;">
                <!-- hour column -->
                <div class="time-column" style="border-right: 1px solid #cbd5e1; background: #f8fafc; height: 624px;">
                  <div v-for="hour in hoursList" :key="hour" class="time-slot-label" style="border-bottom: none; display: flex; align-items: flex-start; justify-content: center; height: 26px; font-weight: 700; color: #64748b;">{{ hour }}</div>
                </div>
                <!-- hours -->
                <div class="days-grid" style="height: 624px;">
                  <div class="grid-bg-lines" style="height: 624px;">
                    <div v-for="hour in hoursList" :key="hour" class="grid-bg-line" style="border-bottom: 1px dashed #e2e8f0; height: 26px;"></div>
                  </div>
                  
                  <div v-for="day in weekData.weekDays" :key="'col-' + getLocalDateStr(day)" class="day-column" :class="{ 'is-weekend': isWeekend(day) }">
                    <div v-for="seg in getPrintDayTimedSegments(getLocalDateStr(day), weekData.shifts)" :key="seg.id" class="shift-card"
                      :style="{
                        position: 'absolute',
                        backgroundColor: seg.shift.shift_type?.color || '#f8fafc',
                        borderLeftColor: adjustColorBrightness(seg.shift.shift_type?.color || '#cbd5e1', -30),
                        borderLeftWidth: '5px',
                        borderLeftStyle: 'solid',
                        top: `${seg.startMins * PX_PER_MIN}px`,
                        height: `${(seg.endMins - seg.startMins) * PX_PER_MIN}px`,
                        left: `${(seg.lane / seg.totalLanes) * 100}%`,
                        width: `${(1 / seg.totalLanes) * 100}%`,
                        zIndex: 10 + seg.lane,
                        padding: '6px',
                        borderRadius: '6px',
                        boxShadow: '0 2px 4px rgba(0,0,0,0.04)',
                        boxSizing: 'border-box'
                      }">
                      <div class="shift-card-header">
                        <span class="shift-name" style="font-weight: 800; display: block; margin-bottom: 2px; color: #0f172a; font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ getShiftName(seg.shift.shift_type) }}</span>
                        <span class="shift-time" style="display: block; font-size: 0.72rem; color: #475569; font-weight: 600;">{{ formatSegmentTime(seg) }}</span>
                      </div>
                      <div class="shift-card-body">
                        <div class="shift-nurses-count" style="font-weight: 800; color: #0f172a; margin-top: 4px; font-size: 0.8rem;">
                          {{ seg.shift.users.length }}
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12" style="vertical-align: middle; margin-left: 2px;">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                          </svg>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- personal view - nurses per shift -->
            <div class="pdf-personal-summary" style="margin-top: 35px; border-top: 2px solid #f1f5f9; padding-top: 25px;">
              <h3 style="margin: 0 0 15px 0; font-size: 1.2rem; color: #1e293b; font-weight: 700; border-left: 4px solid #7c3aed; padding-left: 12px; line-height: 1.2; font-family: inherit;">
                {{ currentLocale === 'pt' ? 'Distribuição de Turnos por Dia' : 'Daily Shift Distribution' }}
              </h3>
              <div style="column-count: 2; column-gap: 30px; font-size: 0.85rem; color: #334155;">
                <div v-for="day in weekData.weekDays" :key="'legend-personal-' + getLocalDateStr(day)" 
                  style="break-inside: avoid; margin-bottom: 15px; background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.01);">
                  <strong style="color: #0f172a; display: block; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 8px; font-size: 0.92rem; font-weight: 800;">
                    {{ getDayOfWeekName(day) }}, {{ getDayOfMonth(day) }}
                  </strong>
                  
                  <div style="display: flex; flex-direction: column; gap: 6px;">
                    <div v-for="(group, idx) in getPrintDayAllDayGroups(getLocalDateStr(day), weekData.shifts)" :key="'leg-all-pers-' + idx" 
                      style="display: flex; align-items: flex-start; line-height: 1.35;">
                      <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; margin-right: 8px; flex-shrink: 0;" 
                        :style="{ backgroundColor: group.shift_type.color || '#cbd5e1' }"></span>
                      <div>
                        <span style="font-weight: 700; color: #1e293b; margin-right: 6px;">{{ getShiftName(group.shift_type) }}:</span>
                        <span style="color: #475569;">{{ group.users.length > 0 ? getAllNursesList(group.users, '-') : '-' }}</span>
                      </div>
                    </div>
                    <div v-for="seg in getPrintDayTimedSegments(getLocalDateStr(day), weekData.shifts)" :key="'leg-pers-' + seg.id" 
                      style="display: flex; align-items: flex-start; line-height: 1.35;">
                      <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; margin-right: 8px; flex-shrink: 0;" 
                        :style="{ backgroundColor: seg.shift.shift_type.color || '#cbd5e1' }"></span>
                      <div>
                        <span style="font-weight: 700; color: #1e293b; margin-right: 6px;">{{ getShiftName(seg.shift.shift_type) }}:</span>
                        <span style="color: #475569;">{{ seg.shift.users.length > 0 ? getAllNursesList(seg.shift.users, 'Sem enfermeiros') : 'Sem enfermeiros' }}</span>
                      </div>
                    </div>
                    
                    <div v-if="getPrintDayAllDayGroups(getLocalDateStr(day), weekData.shifts).length === 0 && getPrintDayTimedSegments(getLocalDateStr(day), weekData.shifts).length === 0" 
                      style="color: #94a3b8; font-style: italic; padding: 2px 0;">
                      {{ currentLocale === 'pt' ? 'Sem turnos registados' : 'No shifts scheduled' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- global view -->
          <div v-if="currentView === 'global'" class="pdf-page" style="width: 1200px; height: 1600px; box-sizing: border-box; padding: 45px 50px; background: white; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-start; margin: 0;">
            <!-- Header -->
            <div class="pdf-page-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
              <img src="~/assets/images/logotipo.png" alt="ShiftCare" style="height: 42px;" />
              <div style="text-align: right;">
                <h2 style="margin: 0; font-size: 1.4rem; font-weight: 800; color: #0f172a; font-family: inherit;">
                  {{ currentLocale === 'pt' ? 'Resumo de Turnos da Equipa' : 'Weekly Team Shifts Summary' }}
                </h2>
                <span style="font-size: 0.95rem; font-weight: 600; color: #64748b; display: block; margin-top: 2px; font-family: inherit;">
                  {{ getWeekRangeLabel(weekData.startDate) }}
                </span>
              </div>
            </div>
            <div style="flex-grow: 1; display: flex; flex-direction: column; margin-top: 0px;">
              <div style="column-count: 2; column-gap: 30px; font-size: 0.9rem; color: #334155;">
                <div v-for="day in weekData.weekDays" :key="'legend-global-' + getLocalDateStr(day)" 
                  style="break-inside: avoid; margin-bottom: 20px; background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                  
                  <strong style="color: #0f172a; display: block; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 10px; font-size: 1rem; font-weight: 800;">
                    {{ getDayOfWeekName(day) }}, {{ getDayOfMonth(day) }}
                  </strong>
                  
                  <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div v-for="(group, idx) in getPrintDayAllDayGroups(getLocalDateStr(day), weekData.shifts)" :key="'leg-all-glob-' + idx" 
                      style="display: flex; align-items: flex-start; line-height: 1.4;">
                      <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-top: 5px; margin-right: 8px; flex-shrink: 0;" 
                        :style="{ backgroundColor: group.shift_type.color || '#cbd5e1' }"></span>
                      <div>
                        <span style="font-weight: 700; color: #1e293b; margin-right: 6px;">{{ getShiftName(group.shift_type) }}:</span>
                        <span style="color: #475569;">{{ group.users.length > 0 ? getAllNursesList(group.users, '-') : '-' }}</span>
                      </div>
                    </div>
                    <div v-for="seg in getPrintDayTimedSegments(getLocalDateStr(day), weekData.shifts)" :key="'leg-glob-' + seg.id" 
                      style="display: flex; align-items: flex-start; line-height: 1.4;">
                      <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-top: 5px; margin-right: 8px; flex-shrink: 0;" 
                        :style="{ backgroundColor: seg.shift.shift_type.color || '#cbd5e1' }"></span>
                      <div>
                        <span style="font-weight: 700; color: #1e293b; margin-right: 6px;">
                          {{ getShiftName(seg.shift.shift_type) }}
                          <span style="font-weight: 600; color: #64748b; font-size: 0.8rem; margin-left: 2px;">
                            ({{ formatShiftTime(seg.shift.shift_type) }})
                          </span>:
                        </span>
                        <span style="color: #475569;">{{ seg.shift.users.length > 0 ? getAllNursesList(seg.shift.users, 'Sem enfermeiros') : 'Sem enfermeiros' }}</span>
                      </div>
                    </div>
                    
                    <div v-if="getPrintDayAllDayGroups(getLocalDateStr(day), weekData.shifts).length === 0 && getPrintDayTimedSegments(getLocalDateStr(day), weekData.shifts).length === 0" 
                      style="color: #94a3b8; font-style: italic; padding: 4px 0;">
                      {{ currentLocale === 'pt' ? 'Sem turnos registados' : 'No shifts scheduled' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped src="~/assets/css/schedule-view.css"></style>