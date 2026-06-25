<script setup lang="ts">
// Apenas utilizadores autenticados podem aceder a esta página
definePageMeta({
  middleware: 'auth',
})

const { token, user: currentUser, fetchMe } = useAuth()
const config = useRuntimeConfig()
const router = useRouter()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Estados de Dados
const vacations = ref<any[]>([])
const nurses = ref<any[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)

// Data ativa do Calendário Principal
const currentDate = ref(new Date())
const currentMonth = computed(() => currentDate.value.getMonth())
const currentYear = computed(() => currentDate.value.getFullYear())

// Data selecionada na interface para exibição detalhada
const selectedDateStr = ref(new Date().toISOString().substring(0, 10))

// Estados de Controlo da UI (Drawer Lateral e Modal de Confirmação)
const isDrawerOpen = ref(false)
const drawerMode = ref<'create' | 'edit'>('create')
const selectedVacationId = ref<number | null>(null)
const showDeleteConfirm = ref(false)

// Estados de formulário no Drawer
const form = ref({
  user_id: null as number | null,
  start_date: '',
  end_date: ''
})
const formErrors = ref<Record<string, string>>({})
const submitting = ref(false)

// Estados para os calendários customizados (date pickers) do formulário
const isStartCalendarOpen = ref(false)
const isEndCalendarOpen = ref(false)
const startCalendarView = ref({ month: new Date().getMonth() + 1, year: new Date().getFullYear() })
const endCalendarView = ref({ month: new Date().getMonth() + 1, year: new Date().getFullYear() })
const isNurseSelectOpen = ref(false)

// Filtros do painel principal
const selectedNurseFilter = ref<number | null>(null)
const selectedStatusFilter = ref<string>('')

// Constantes de data
const monthNamesPT = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
const monthNamesEN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const weekDaysPT = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
const weekDaysEN = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const getMonthName = (month: number) => {
  return currentLocale.value === 'pt' ? monthNamesPT[month - 1] : monthNamesEN[month - 1]
}

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

// ----------------------------------------------------
// NAVEGAÇÃO DO CALENDÁRIO MENSAL PRINCIPAL
// ----------------------------------------------------
const nextMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
}

const prevMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
}

// ----------------------------------------------------
// AUXILIAR DE GERAÇÃO DE DIAS DO CALENDÁRIO
// ----------------------------------------------------
const getCalendarDays = (year: number, month: number) => {
  const daysInMonth = new Date(year, month, 0).getDate()
  const startDay = new Date(year, month - 1, 1).getDay()
  
  const cells: Array<{ day: number | null, dateStr: string }> = []
  const startOffset = startDay === 0 ? 6 : startDay - 1
  
  for (let i = 0; i < startOffset; i++) {
    cells.push({ day: null, dateStr: '' })
  }
  
  for (let d = 1; d <= daysInMonth; d++) {
    const mm = String(month).padStart(2, '0')
    const dd = String(d).padStart(2, '0')
    cells.push({ day: d, dateStr: `${year}-${mm}-${dd}` })
  }
  
  return cells
}

const getCalendarDaysView = (year: number, month: number) => {
  return getCalendarDays(year, month)
}

// Grelha principal de 42 dias para o calendário visual
const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value
  
  const firstDay = new Date(year, month, 1)
  let startDayIndex = firstDay.getDay()
  startDayIndex = startDayIndex === 0 ? 6 : startDayIndex - 1
  
  const daysInMonth = new Date(year, month + 1, 0).getDate()
  const cells = []
  
  // Mês anterior
  const prevMonthEnd = new Date(year, month, 0).getDate()
  for (let i = startDayIndex - 1; i >= 0; i--) {
    const dayVal = prevMonthEnd - i
    const mm = month === 0 ? 12 : month
    const yy = month === 0 ? year - 1 : year
    cells.push({
      day: dayVal,
      dateStr: `${yy}-${String(mm).padStart(2, '0')}-${String(dayVal).padStart(2, '0')}`,
      isCurrentMonth: false
    })
  }
  
  // Mês atual
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push({
      day: d,
      dateStr: `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`,
      isCurrentMonth: true
    })
  }
  
  // Mês seguinte
  const remaining = 42 - cells.length
  for (let d = 1; d <= remaining; d++) {
    const mm = month === 11 ? 1 : month + 2
    const yy = month === 11 ? year + 1 : year
    cells.push({
      day: d,
      dateStr: `${yy}-${String(mm).padStart(2, '0')}-${String(d).padStart(2, '0')}`,
      isCurrentMonth: false
    })
  }
  
  return cells
})

// ----------------------------------------------------
// PROCESSAMENTO E ESTADOS DE APIS
// ----------------------------------------------------
const getVacationStatus = (startDateStr: string, endDateStr: string) => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  const s = new Date(startDateStr.substring(0, 10))
  const e = new Date(endDateStr.substring(0, 10))
  
  if (e.getTime() < today.getTime()) return 'past'
  if (s.getTime() <= today.getTime() && e.getTime() >= today.getTime()) return 'ongoing'
  return 'future'
}

const getFilteredVacationsForDay = (dateStr: string) => {
  return vacations.value.filter(v => {
    const start = v.start_date.substring(0, 10)
    const end = v.end_date.substring(0, 10)
    const matchesDay = dateStr >= start && dateStr <= end
    
    if (!matchesDay) return false
    if (selectedNurseFilter.value !== null && v.user_id !== selectedNurseFilter.value) return false
    
    if (selectedStatusFilter.value) {
      if (getVacationStatus(v.start_date, v.end_date) !== selectedStatusFilter.value) return false
    }
    
    return true
  })
}

// ----------------------------------------------------
// OPERAÇÕES DO BACKEND (CRUD)
// ----------------------------------------------------
const fetchVacations = async () => {
  try {
    const response = await $fetch<{ data: any[] }>(`${config.public.apiBase}/vacations?t=${Date.now()}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    vacations.value = response.data
  } catch (err) {
    console.error('Error fetching vacations:', err)
    error.value = 'fetchError'
  }
}

const fetchNurses = async () => {
  try {
    const response = await $fetch<{ data: any[] }>(`${config.public.apiBase}/users`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    nurses.value = response.data.filter(u => {
      const role = u.role.toLowerCase().trim().replace(' ', '_')
      return (role === 'nurse' || role === 'head_nurse') && u.active
    })
  } catch (err) {
    console.error('Error fetching nurses:', err)
  }
}

const loadData = async () => {
  loading.value = true
  error.value = null
  await Promise.all([fetchNurses(), fetchVacations()])
  loading.value = false
}

// ----------------------------------------------------
// CONTROLOS DE CALENDÁRIO DO FORMULÁRIO (DATE PICKERS)
// ----------------------------------------------------
const toggleStartCalendar = () => {
  isStartCalendarOpen.value = !isStartCalendarOpen.value
  isEndCalendarOpen.value = false
  isNurseSelectOpen.value = false
  
  if (form.value.start_date) {
    const parts = form.value.start_date.substring(0, 10).split('-').map(Number)
    if (parts[0] !== undefined && parts[1] !== undefined) {
      startCalendarView.value = { year: parts[0], month: parts[1] }
    }
  }
}

const toggleEndCalendar = () => {
  isEndCalendarOpen.value = !isEndCalendarOpen.value
  isStartCalendarOpen.value = false
  isNurseSelectOpen.value = false
  
  if (form.value.end_date) {
    const parts = form.value.end_date.substring(0, 10).split('-').map(Number)
    if (parts[0] !== undefined && parts[1] !== undefined) {
      endCalendarView.value = { year: parts[0], month: parts[1] }
    }
  }
}

// ----------------------------------------------------
// AÇÕES DO DRAWER LATERAL (CRIAR / EDITAR)
// ----------------------------------------------------
const openCreateDrawer = (dateStr: string | null = null) => {
  drawerMode.value = 'create'
  selectedVacationId.value = null
  formErrors.value = {}
  
  form.value = {
    user_id: selectedNurseFilter.value || null,
    start_date: dateStr || selectedDateStr.value || '',
    end_date: dateStr || selectedDateStr.value || ''
  }
  
  // Sincroniza calendários internos do formulário
  if (form.value.start_date) {
    const p = form.value.start_date.split('-').map(Number)
    if (p[0] !== undefined && p[1] !== undefined) {
      startCalendarView.value = { year: p[0], month: p[1] }
    }
  }
  if (form.value.end_date) {
    const p = form.value.end_date.split('-').map(Number)
    if (p[0] !== undefined && p[1] !== undefined) {
      endCalendarView.value = { year: p[0], month: p[1] }
    }
  }
  
  isDrawerOpen.value = true
}

const openEditDrawer = async (v: any) => {
  drawerMode.value = 'edit'
  selectedVacationId.value = v.id
  formErrors.value = {}
  
  // Preenche dados locais rapidamente
  form.value = {
    user_id: v.user_id,
    start_date: v.start_date.substring(0, 10),
    end_date: v.end_date.substring(0, 10)
  }
  
  isDrawerOpen.value = true

  // Procura os detalhes frescos e completos do servidor
  try {
    const response = await $fetch<{ data: any }>(`${config.public.apiBase}/vacations/${v.id}`, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    
    const details = response.data
    form.value = {
      user_id: details.user_id,
      start_date: details.start_date ? details.start_date.substring(0, 10) : '',
      end_date: details.end_date ? details.end_date.substring(0, 10) : ''
    }

    // Sincroniza calendários internos de forma segura
    const pS = form.value.start_date.split('-').map(Number)
    if (pS[0] !== undefined && pS[1] !== undefined) {
      startCalendarView.value = { year: pS[0], month: pS[1] }
    }
    
    const pE = form.value.end_date.split('-').map(Number)
    if (pE[0] !== undefined && pE[1] !== undefined) {
      endCalendarView.value = { year: pE[0], month: pE[1] }
    }
  } catch (err) {
    console.error('Error fetching vacation details:', err)
  }
}

const closeDrawer = () => {
  isDrawerOpen.value = false
  isNurseSelectOpen.value = false
  isStartCalendarOpen.value = false
  isEndCalendarOpen.value = false
}

const saveVacation = async () => {
  formErrors.value = {}
  
  if (!form.value.user_id) formErrors.value.user_id = 'nurseRequired'
  if (!form.value.start_date) formErrors.value.start_date = 'startDateRequired'
  if (!form.value.end_date) formErrors.value.end_date = 'endDateRequired'
  
  if (form.value.start_date && form.value.end_date && form.value.start_date > form.value.end_date) {
    formErrors.value.end_date = 'dateOrder'
  }
  
  if (Object.keys(formErrors.value).length > 0) return
  
  submitting.value = true
  try {
    const isEdit = drawerMode.value === 'edit'
    const url = isEdit 
      ? `${config.public.apiBase}/vacations/${selectedVacationId.value}`
      : `${config.public.apiBase}/vacations`
    
    await $fetch(url, {
      method: isEdit ? 'PATCH' : 'POST',
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json'
      },
      body: {
        user_id: form.value.user_id,
        start_date: form.value.start_date,
        end_date: form.value.end_date
      }
    })
    
    showNotification(isEdit ? 'updateSuccess' : 'createSuccess', 'success')
    closeDrawer()
    await fetchVacations()
  } catch (err: any) {
    console.error('Error saving vacation:', err)
    if (err.data?.message) {
      statusMessage.value = { key: err.data.message, type: 'error' }
      setTimeout(() => { statusMessage.value = null }, 5000)
    } else {
      showNotification('error', 'error')
    }
  } finally {
    submitting.value = false
  }
}

const requestDelete = () => {
  showDeleteConfirm.value = true
}

const confirmDelete = async () => {
  showDeleteConfirm.value = false
  if (!selectedVacationId.value) return
  
  submitting.value = true
  try {
    await $fetch(`${config.public.apiBase}/vacations/${selectedVacationId.value}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` }
    })
    
    showNotification('deleteSuccess', 'success')
    closeDrawer()
    await fetchVacations()
  } catch (err) {
    console.error('Error deleting vacation:', err)
    showNotification('error', 'error')
  } finally {
    submitting.value = false
  }
}

// ----------------------------------------------------
// NAVEGAÇÃO DOS MINI CALENDÁRIOS DO FORMULÁRIO
// ----------------------------------------------------
const prevMonthView = (type: 'start' | 'end') => {
  const view = type === 'start' ? startCalendarView.value : endCalendarView.value
  if (view.month === 1) {
    view.month = 12
    view.year--
  } else {
    view.month--
  }
}

const nextMonthView = (type: 'start' | 'end') => {
  const view = type === 'start' ? startCalendarView.value : endCalendarView.value
  if (view.month === 12) {
    view.month = 1
    view.year++
  } else {
    view.month++
  }
}

// ----------------------------------------------------
// UTILS
// ----------------------------------------------------
const getFirstName = (name: string) => {
  if (!name) return '?'
  return name.trim().split(/\s+/)[0]
}

const selectDate = (dateStr: string) => {
  selectedDateStr.value = dateStr
}

const isToday = (dateStr: string) => {
  const today = new Date()
  const y = today.getFullYear()
  const m = String(today.getMonth() + 1).padStart(2, '0')
  const d = String(today.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}` === dateStr
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return '-'
  const clean = dateStr.substring(0, 10)
  const parts = clean.split('-')
  if (parts.length < 3) return '-'
  return `${parts[2]}-${parts[1]}-${parts[0]}`
}

const selectedNurseName = computed(() => {
  const n = nurses.value.find(x => x.id === form.value.user_id)
  return n ? n.name : texts.value.drawer.selectNurse
})

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Gestão de Férias' : 'Vacations Management',
  subtitle: currentLocale.value === 'pt' ? 'Planeamento e acompanhamento visual das ausências por férias' : 'Visual planning and tracking of staff vacations',
  createVacation: currentLocale.value === 'pt' ? 'Atribuir Férias' : 'Assign Vacation',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  loading: currentLocale.value === 'pt' ? 'A carregar calendário...' : 'Loading calendar...',
  
  filters: {
    allNurses: currentLocale.value === 'pt' ? 'Todos os Profissionais' : 'All Professionals',
  },
  
  status: {
    all: currentLocale.value === 'pt' ? 'Todos os Estados' : 'All Status',
    past: currentLocale.value === 'pt' ? 'Já Passou' : 'Past',
    ongoing: currentLocale.value === 'pt' ? 'A Decorrer' : 'Ongoing',
    future: currentLocale.value === 'pt' ? 'Datas Futuras' : 'Future',
  },
  
  selectedDayTitle: currentLocale.value === 'pt' ? 'Lista de Ausências em' : 'Absences List on',
  noVacationsOnDay: currentLocale.value === 'pt' ? 'Nenhum profissional goza de férias nesta data.' : 'No professionals are on vacation on this date.',
  assignOnDate: currentLocale.value === 'pt' ? 'Atribuir Férias para este Dia' : 'Assign Vacation on this Date',
  
  drawer: {
    createTitle: currentLocale.value === 'pt' ? 'Atribuir Férias' : 'Assign Vacation',
    editTitle: currentLocale.value === 'pt' ? 'Detalhes das Férias' : 'Vacation Details',
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    startDate: currentLocale.value === 'pt' ? 'Data de Início' : 'Start Date',
    endDate: currentLocale.value === 'pt' ? 'Data de Fim' : 'End Date',
    selectNurse: currentLocale.value === 'pt' ? 'Selecione um profissional' : 'Select a professional',
    save: currentLocale.value === 'pt' ? 'Gravar Férias' : 'Save Vacation',
    saving: currentLocale.value === 'pt' ? 'A gravar...' : 'Saving...',
    delete: currentLocale.value === 'pt' ? 'Remover Período' : 'Remove Period',
    deleting: currentLocale.value === 'pt' ? 'A remover...' : 'Removing...',
  },
  
  notifications: {
    createSuccess: currentLocale.value === 'pt' ? 'Férias atribuídas com sucesso!' : 'Vacation assigned successfully!',
    updateSuccess: currentLocale.value === 'pt' ? 'Férias atualizadas com sucesso!' : 'Vacation updated successfully!',
    deleteSuccess: currentLocale.value === 'pt' ? 'Férias removidas com sucesso!' : 'Vacation removed successfully!',
    error: currentLocale.value === 'pt' ? 'Erro ao processar alteração de férias.' : 'Error processing vacation request.',
    formError: currentLocale.value === 'pt' ? 'Por favor, corrija os erros no formulário.' : 'Please correct form errors.',
  },
  
  validation: {
    nurseRequired: currentLocale.value === 'pt' ? 'A seleção do profissional é obrigatória' : 'Professional selection is required',
    startDateRequired: currentLocale.value === 'pt' ? 'Data de início obrigatória' : 'Start date required',
    endDateRequired: currentLocale.value === 'pt' ? 'Data de fim obrigatória' : 'End date required',
    dateOrder: currentLocale.value === 'pt' ? 'Data de fim não pode ser anterior à data de início' : 'End date cannot be before start date',
  }
}))

onMounted(async () => {
  if (!currentUser.value) {
    await fetchMe().catch(() => null)
  }
  
  const userRole = currentUser.value?.role?.toLowerCase().trim() || ''
  if (userRole !== 'admin') {
    await navigateTo('/dashboard')
    return
  }
  await loadData()
})
</script>

<template>
  <main class="dashboard-layout hr-page">
    <AppNavbar />

    <!-- Notificações Gerais -->
    <transition name="slide-down">
      <div v-if="statusMessage" :class="['global-toast', statusMessage.type]">
        <div class="toast-content">
          <svg v-if="statusMessage.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{{ (texts.notifications as any)[statusMessage.key] || statusMessage.key }}</span>
        </div>
      </div>
    </transition>

    <section class="hr-content" v-if="!loading">
      <!-- Topo da página -->
      <div class="uc-top-bar">
        <div class="uc-title-group">
          <NuxtLink to="/dashboard" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            {{ texts.back }}
          </NuxtLink>
          <h1>{{ texts.title }}</h1>
          <p class="uc-subtitle">{{ texts.subtitle }}</p>
        </div>
      </div>

      <!-- Barra de Filtros e Controlos de Navegação -->
      <div class="vacation-calendar-controls">
        <div class="month-navigator">
          <button class="nav-arrow-btn" @click="prevMonth">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="18" height="18">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>
          <h2>{{ getMonthName(currentMonth + 1) }} <span>{{ currentYear }}</span></h2>
          <button class="nav-arrow-btn" @click="nextMonth">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="18" height="18">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </div>

        <div class="filters-actions-row">
          <div class="filter-dropdown-wrapper">
            <select v-model="selectedNurseFilter" class="modern-filter-select">
              <option :value="null">{{ texts.filters.allNurses }}</option>
              <option v-for="n in nurses" :key="n.id" :value="n.id">{{ n.name }}</option>
            </select>
          </div>

          <div class="filter-dropdown-wrapper">
            <select v-model="selectedStatusFilter" class="modern-filter-select">
              <option value="">{{ texts.status.all }}</option>
              <option value="ongoing">{{ texts.status.ongoing }}</option>
              <option value="future">{{ texts.status.future }}</option>
              <option value="past">{{ texts.status.past }}</option>
            </select>
          </div>

          <button class="create-btn" @click="openCreateDrawer(null)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            {{ texts.createVacation }}
          </button>
        </div>
      </div>

      <!-- Calendário de Férias Interativo -->
      <div class="vacation-calendar-card">
        <div class="weekdays-header-grid">
          <div v-for="day in (currentLocale === 'pt' ? weekDaysPT : weekDaysEN)" :key="day" class="weekday-label">
            {{ day }}
          </div>
        </div>

        <div class="days-container-grid">
          <div 
            v-for="cell in calendarDays" 
            :key="cell.dateStr" 
            class="calendar-day-box"
            :class="{
              'non-current-month': !cell.isCurrentMonth,
              'today-box': isToday(cell.dateStr),
              'active-focus': selectedDateStr === cell.dateStr
            }"
            @click="selectDate(cell.dateStr)"
          >
            <div class="day-number-label">{{ cell.day }}</div>
            
            <div class="day-events-wrapper">
              <div 
                v-for="v in getFilteredVacationsForDay(cell.dateStr)" 
                :key="v.id" 
                class="vacation-chip"
                :class="getVacationStatus(v.start_date, v.end_date)"
                @click.stop="openEditDrawer(v)"
              >
                <span class="chip-name">{{ getFirstName(v.user?.name) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Detalhes do Dia Selecionado -->
      <div class="selected-day-details-panel">
        <div class="panel-header">
          <h3>{{ texts.selectedDayTitle }} <strong>{{ formatDate(selectedDateStr) }}</strong></h3>
          <button class="add-day-btn" @click="openCreateDrawer(selectedDateStr)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            {{ texts.assignOnDate }}
          </button>
        </div>

        <div class="panel-body">
          <div v-if="getFilteredVacationsForDay(selectedDateStr).length === 0" class="no-vacations-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            <p>{{ texts.noVacationsOnDay }}</p>
          </div>

          <div v-else class="vacations-detailed-list">
            <div 
              v-for="v in getFilteredVacationsForDay(selectedDateStr)" 
              :key="v.id" 
              class="detailed-vacation-card"
            >
              <div class="card-left">
                <div class="nurse-avatar-circle">
                  {{ v.user ? v.user.name.charAt(0).toUpperCase() : '?' }}
                </div>
                <div class="nurse-info">
                  <h4>{{ v.user ? v.user.name : 'Enfermeiro Removido' }}</h4>
                  <span class="dates-range">
                    {{ formatDate(v.start_date) }} <span class="arrow">&rarr;</span> {{ formatDate(v.end_date) }}
                  </span>
                </div>
              </div>

              <div class="card-right">
                <span class="status-indicator-badge" :class="getVacationStatus(v.start_date, v.end_date)">
                  {{ (texts.status as any)[getVacationStatus(v.start_date, v.end_date)] }}
                </span>
                <button class="action-btn-circle edit" @click="openEditDrawer(v)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Estado de Carregamento Geral -->
    <section class="loading-state" v-else>
      <div class="spinner"></div>
      <p>{{ texts.loading }}</p>
    </section>

    <!-- OVERLAY DO DRAWER -->
    <div class="drawer-overlay" :class="{ open: isDrawerOpen }" @click="closeDrawer"></div>

    <!-- DRAWER LATERAL DE FÉRIAS (SLIDE-IN) -->
    <div class="vacation-drawer" :class="{ open: isDrawerOpen }">
      <div class="drawer-header">
        <h2>{{ drawerMode === 'create' ? texts.drawer.createTitle : texts.drawer.editTitle }}</h2>
        <button class="close-drawer-btn" @click="closeDrawer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <div class="drawer-body">
        <form @submit.prevent="saveVacation" class="drawer-form" novalidate>
          
          <!-- Seleção de Enfermeiro (Disponível em Criar e Editar para poder alterar) -->
          <div class="form-group-item">
            <label>{{ texts.drawer.nurse }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ active: isNurseSelectOpen, 'select-error': formErrors.user_id }"
                @click.stop="isNurseSelectOpen = !isNurseSelectOpen; isStartCalendarOpen = false; isEndCalendarOpen = false"
              >
                <span>{{ selectedNurseName }}</span>
                <svg :class="{ rotate: isNurseSelectOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </div>
              <transition name="fade-slide">
                <div v-if="isNurseSelectOpen" class="uc-options nurse-list-scroll">
                  <div 
                    v-for="nurse in nurses" 
                    :key="nurse.id" 
                    class="uc-option" 
                    @click="form.user_id = nurse.id; isNurseSelectOpen = false"
                  >
                    {{ nurse.name }}
                  </div>
                </div>
              </transition>
            </div>
            <span v-if="formErrors.user_id" class="field-error-msg">{{ (texts.validation as any)[formErrors.user_id] }}</span>
          </div>

          <!-- Data de Início -->
          <div class="form-group-item">
            <label>{{ texts.drawer.startDate }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ active: isStartCalendarOpen, 'select-error': formErrors.start_date }"
                @click.stop="toggleStartCalendar"
              >
                <span>{{ form.start_date ? formatDate(form.start_date) : (currentLocale === 'pt' ? 'Selecione' : 'Select') }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16" class="calendar-input-icon">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </div>
              
              <transition name="fade-slide">
                <div v-if="isStartCalendarOpen" class="custom-calendar-popup-view" @click.stop>
                  <div class="calendar-header-view">
                    <button type="button" class="calendar-nav-btn" @click="prevMonthView('start')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
                        <polyline points="15 18 9 12 15 6"></polyline>
                      </svg>
                    </button>
                    <span class="calendar-month-title-view">
                      {{ getMonthName(startCalendarView.month) }} {{ startCalendarView.year }}
                    </span>
                    <button type="button" class="calendar-nav-btn" @click="nextMonthView('start')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                    </button>
                  </div>

                  <div class="calendar-weekdays-view">
                    <span v-for="day in (currentLocale === 'pt' ? weekDaysPT : weekDaysEN)" :key="day" class="weekday-cell-view">
                      {{ day.substring(0, 1) }}
                    </span>
                  </div>

                  <div class="calendar-days-grid-view">
                    <div 
                      v-for="(cell, index) in getCalendarDaysView(startCalendarView.year, startCalendarView.month)" 
                      :key="index"
                      :class="[
                        'day-cell-view', 
                        { 
                          'empty-view': !cell.day, 
                          'selected-view': cell.day && form.start_date === cell.dateStr,
                          'today-view': cell.day && isToday(cell.dateStr)
                        }
                      ]"
                      @click="cell.day && (form.start_date = cell.dateStr) && (isStartCalendarOpen = false)"
                    >
                      {{ cell.day }}
                    </div>
                  </div>
                </div>
              </transition>
            </div>
            <span v-if="formErrors.start_date" class="field-error-msg">
              {{ (texts.validation as any)[formErrors.start_date] || formErrors.start_date }}
            </span>
          </div>

          <!-- Data de Fim -->
          <div class="form-group-item">
            <label>{{ texts.drawer.endDate }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ active: isEndCalendarOpen, 'select-error': formErrors.end_date }"
                @click.stop="toggleEndCalendar"
              >
                <span>{{ form.end_date ? formatDate(form.end_date) : (currentLocale === 'pt' ? 'Selecione' : 'Select') }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16" class="calendar-input-icon">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </div>
              
              <transition name="fade-slide">
                <div v-if="isEndCalendarOpen" class="custom-calendar-popup-view" @click.stop>
                  <div class="calendar-header-view">
                    <button type="button" class="calendar-nav-btn" @click="prevMonthView('end')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
                        <polyline points="15 18 9 12 15 6"></polyline>
                      </svg>
                    </button>
                    <span class="calendar-month-title-view">
                      {{ getMonthName(endCalendarView.month) }} {{ endCalendarView.year }}
                    </span>
                    <button type="button" class="calendar-nav-btn" @click="nextMonthView('end')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14">
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                    </button>
                  </div>

                  <div class="calendar-weekdays-view">
                    <span v-for="day in (currentLocale === 'pt' ? weekDaysPT : weekDaysEN)" :key="day" class="weekday-cell-view">
                      {{ day.substring(0, 1) }}
                    </span>
                  </div>

                  <div class="calendar-days-grid-view">
                    <div 
                      v-for="(cell, index) in getCalendarDaysView(endCalendarView.year, endCalendarView.month)" 
                      :key="index"
                      :class="[
                        'day-cell-view', 
                        { 
                          'empty-view': !cell.day, 
                          'selected-view': cell.day && form.end_date === cell.dateStr,
                          'today-view': cell.day && isToday(cell.dateStr)
                        }
                      ]"
                      @click="cell.day && (form.end_date = cell.dateStr) && (isEndCalendarOpen = false)"
                    >
                      {{ cell.day }}
                    </div>
                  </div>
                </div>
              </transition>
            </div>
            <span v-if="formErrors.end_date" class="field-error-msg">
              {{ (texts.validation as any)[formErrors.end_date] || formErrors.end_date }}
            </span>
          </div>

          <!-- Botões de Ação do Drawer -->
          <div class="drawer-actions-row">
            <button 
              v-if="drawerMode === 'edit'" 
              type="button" 
              class="delete-drawer-btn" 
              :disabled="submitting"
              @click="requestDelete"
            >
              {{ texts.drawer.delete }}
            </button>

            <button 
              type="submit" 
              class="save-drawer-btn" 
              :class="{ loading: submitting }" 
              :disabled="submitting"
            >
              {{ submitting ? texts.drawer.saving : texts.drawer.save }}
            </button>
          </div>

        </form>
      </div>
    </div>

    <!-- MODAL DE CONFIRMAÇÃO DE APAGAR -->
    <transition name="fade">
      <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
        <div class="modal-card">
          <div class="modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
          </div>
          <h2>{{ currentLocale === 'pt' ? 'Remover Período de Férias' : 'Remove Vacation Period' }}</h2>
          <p>{{ currentLocale === 'pt' ? 'Tem a certeza que deseja remover estas férias? A escala voltará ao estado normal sem ausências.' : 'Are you sure you want to remove this vacation? The schedule will go back to its normal state.' }}</p>

          <div class="modal-actions">
            <button class="modal-btn cancel" @click="showDeleteConfirm = false">
              {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
            </button>
            <button class="modal-btn confirm" @click="confirmDelete" :disabled="submitting">
              {{ submitting ? texts.drawer.deleting : (currentLocale === 'pt' ? 'Sim, Remover' : 'Yes, Remove') }}
            </button>
          </div>
        </div>
      </div>
    </transition>

  </main>
</template>