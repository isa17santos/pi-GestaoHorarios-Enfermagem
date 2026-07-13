<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { user } = useAuth()
const { pendingReceivedCount, fetchPendingReceivedCount } = useSwap()
const { nurseData, headNurseData, fetchDashboard } = useDashboard()
const { texts: scheduleTexts } = useScheduleTexts()

onMounted(() => {
  fetchPendingReceivedCount()
  // Dashboard summary is fetched for nurse and head_nurse; admin has no summary card yet.
  const role = user.value?.role?.trim().toLowerCase()
  if (role === 'nurse' || role === 'head_nurse') {
    fetchDashboard()
  }
})

// Perfis de utilizador simplificados
const isHeadNurse = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

const isAdmin = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin'
})

const isNurse = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'nurse'
})

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const texts = computed(() => ({
  welcome: currentLocale.value === 'pt' ? 'Bem-vindo' : 'Welcome',

  // Textos Enfermeiro Chefe
  teamPendingSwaps: currentLocale.value === 'pt' ? 'Trocas pendentes da equipa' : 'Team pending swaps',
  teamSwapsThisMonth: currentLocale.value === 'pt' ? 'Trocas da equipa este mês' : 'Team swaps this month',
  createSchedule: currentLocale.value === 'pt' ? 'Criar horário' : 'Create schedule',
  editSchedule: currentLocale.value === 'pt' ? 'Editar horário' : 'Edit schedule',
  viewSchedule: currentLocale.value === 'pt' ? 'Consultar horário' : 'View schedule',
  preferences: currentLocale.value === 'pt' ? 'Perfis de preferências' : 'Preference profiles',

  // Textos Admin
  humanResources: currentLocale.value === 'pt' ? 'Gestão Recursos Humanos' : 'Human Resources Management',
  vacations: currentLocale.value === 'pt' ? 'Gestão férias' : 'Vacations',
  sickLeaves: currentLocale.value === 'pt' ? 'Gestão baixas' : 'Absences',
  shiftTypes: currentLocale.value === 'pt' ? 'Gestão tipos de turno' : 'Shift types',
  swapHistory: currentLocale.value === 'pt' ? 'Histórico de Trocas' : 'Swap History',

  // Textos Enfermeiro
  schedule: currentLocale.value === 'pt' ? 'Horário' : 'Schedule',
  swaps: currentLocale.value === 'pt' ? 'Trocas' : 'Shift Swaps',
  monthlyHours: currentLocale.value === 'pt' ? 'Horas este mês' : 'Hours this month',
  shiftBreakdown: currentLocale.value === 'pt' ? 'Turnos por tipo' : 'Shifts by type',

  // Secção de estatísticas
  monthlySummary: currentLocale.value === 'pt' ? 'Resumo do mês' : 'Monthly summary',
  hoursWorked: currentLocale.value === 'pt' ? 'Trabalhadas este mês' : 'Worked this month',
  shiftsUnit: currentLocale.value === 'pt' ? 'turnos' : 'shifts',
  swapsThisMonth: currentLocale.value === 'pt' ? 'Trocas realizadas este mês' : 'Swaps made this month',
  today: currentLocale.value === 'pt' ? 'Hoje' : 'Today',
  tomorrow: currentLocale.value === 'pt' ? 'Amanhã' : 'Tomorrow',
  noShift: currentLocale.value === 'pt' ? 'Sem turno' : 'No shift',
  allDay: currentLocale.value === 'pt' ? 'Dia Inteiro' : 'All Day',
  myShifts: currentLocale.value === 'pt' ? 'Os meus turnos' : 'My shifts',
  myTeam: currentLocale.value === 'pt' ? 'A minha equipa' : 'My team',

  // Partilhado
  statistics: currentLocale.value === 'pt' ? 'Estatísticas' : 'Statistics',
}))

// ---------------- Tradução de nomes de turno ----------------
const getShiftName = (shiftType: { name: string } | null | undefined) => {
  if (!shiftType) return ''
  const name = (shiftType.name || '').toLowerCase()
  const pt: Record<string, string> = { morning: 'Manhã', afternoon: 'Tarde', night: 'Noite', dayoff: 'Folga', 'day off': 'Folga', holidays: 'Férias', 'sick leave': 'Baixa Médica', 'parental leave': 'Licença Parental' }
  const en: Record<string, string> = { morning: 'Morning', afternoon: 'Afternoon', night: 'Night', dayoff: 'Day Off', 'day off': 'Day Off', holidays: 'Holidays', 'sick leave': 'Sick Leave', 'parental leave': 'Parental Leave' }
  return currentLocale.value === 'pt' ? (pt[name] || shiftType.name) : (en[name] || shiftType.name)
}

// Turnos não laborais (folga, férias, baixa, licença) têm start_time === end_time.
const isNonWorkingShift = (shiftType: { start_time: string, end_time: string } | null | undefined) => {
  return !!shiftType && shiftType.start_time === shiftType.end_time
}

// ---------------- Formatação de data (dia da semana + dia/mês) ----------------
const formatDayLabel = (date: Date) => {
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-GB'
  const weekday = date.toLocaleDateString(locale, { weekday: 'long' })
  const day = date.getDate()
  const month = date.toLocaleDateString(locale, { month: 'long' })
  const capitalizedWeekday = weekday.charAt(0).toUpperCase() + weekday.slice(1)
  return currentLocale.value === 'pt'
    ? `${capitalizedWeekday} – ${day} de ${month}`
    : `${capitalizedWeekday} – ${month} ${day}`
}

const todayLabel = computed(() => formatDayLabel(new Date()))
const tomorrowLabel = computed(() => {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  return formatDayLabel(tomorrow)
})

// Mês e ano atuais, capitalizados — mostrado ao lado do título "Resumo do mês"
const currentMonthLabel = computed(() => {
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-GB'
  const label = new Date().toLocaleDateString(locale, { month: 'long', year: 'numeric' })
  return label.charAt(0).toUpperCase() + label.slice(1)
})

// As 4 categorias fixas de turno — mostradas sempre, mesmo com valor 0
const shiftCategories = computed(() => [
  { key: 'morning',   icon: 'sunrise', label: scheduleTexts.value.edit.shiftNames.morning },
  { key: 'afternoon', icon: 'sun',     label: scheduleTexts.value.edit.shiftNames.afternoon },
  { key: 'night',     icon: 'moon',    label: scheduleTexts.value.edit.shiftNames.night },
  { key: 'dayoff',    icon: 'circle',  label: scheduleTexts.value.edit.shiftNames.dayoff },
])
</script>

<template>
  <main class="dashboard-layout">
    <AppNavbar />

    <section class="dashboard-content">
      <div class="dashboard-greeting" v-if="user">
        <h1>{{ texts.welcome }}, <strong>{{ user.name }}</strong>.</h1>
        <p class="greeting-sub">
          <!-- Mensagem dinâmica consoante o perfil -->
          <template v-if="isHeadNurse">
            {{ currentLocale === 'pt' ? 'Gestão de Equipas e Escalas' : 'Team and Schedule Management' }}
          </template>
          <template v-else-if="isNurse">
            {{ currentLocale === 'pt' ? 'O meu Painel de Enfermagem' : 'My Nursing Dashboard' }}
          </template>
          <template v-else>
            {{ currentLocale === 'pt' ? 'Gestão de Recursos Humanos e Operações' : 'Human Resources and Operations Management' }}
          </template>
        </p>
      </div>

      <!-- ========================================== -->
      <!-- BLOCO ENFERMEIRO CHEFE (HEAD NURSE)        -->
      <!-- ========================================== -->
      <template v-if="isHeadNurse">

        <!-- Cards de navegação clicáveis -->
        <div class="bento-grid">

          <NuxtLink to="/schedule-create" class="bento-card bento-card--hr bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <line x1="12" y1="13" x2="12" y2="19"></line>
                <line x1="9" y1="16" x2="15" y2="16"></line>
              </svg>
            </div>
            <h3>{{ texts.createSchedule }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Gerar e planear novos horários' : 'Generate and plan new schedules' }}</p>
            <!-- Chevron indicador de navegação -->
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/schedule-edit" class="bento-card bento-card--vacations bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </div>
            <h3>{{ texts.editSchedule }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Fazer alterações à escala atual' : 'Make changes to the current schedule' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/schedule-view" class="bento-card bento-card--sick bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <line x1="8" y1="14" x2="16" y2="14"></line>
                <line x1="8" y1="18" x2="12" y2="18"></line>
              </svg>
            </div>
            <h3>{{ texts.viewSchedule }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Visualizar o horário' : 'View schedule' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/swaps-history" class="bento-card bento-card--vacations bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <polyline points="17 1 21 5 17 9"></polyline>
                <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                <polyline points="7 23 3 19 7 15"></polyline>
                <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
              </svg>
            </div>
            <h3>{{ texts.swapHistory }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Pedidos de troca aceites' : 'Accepted swap requests' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/statistics" class="bento-card bento-card--stats bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
              </svg>
            </div>
            <h3>{{ texts.statistics }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Relatórios de cobertura da equipa' : 'Team coverage reports' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

        </div>

        <!-- Secção de resumo mensal — igual à do enfermeiro, pois o chefe também tem turnos próprios -->
        <div v-if="headNurseData" class="dashboard-stats-section">
          <h2 class="stats-section-title">{{ texts.monthlySummary }} – {{ currentMonthLabel }}</h2>
          <div class="stats-kpi-row">

            <!-- Coluna 1: turno de hoje e de amanhã, empilhados -->
            <div class="stats-column stats-column--narrow">
              <span class="stats-column-title">{{ texts.myShifts }}</span>

              <div class="today-tomorrow-column">
                <div class="today-tomorrow-card" :style="{ '--shift-accent': headNurseData.today_shift?.shift_type.color || 'var(--muted)' }">
                  <span class="today-tomorrow-label">{{ texts.today }} – {{ todayLabel }}</span>
                  <span class="today-tomorrow-value">
                    {{ headNurseData.today_shift ? getShiftName(headNurseData.today_shift.shift_type) : texts.noShift }}
                  </span>
                  <span v-if="headNurseData.today_shift" class="today-tomorrow-time">
                    {{ isNonWorkingShift(headNurseData.today_shift.shift_type) ? texts.allDay : `${headNurseData.today_shift.shift_type.start_time.slice(0, 5)}h – ${headNurseData.today_shift.shift_type.end_time.slice(0, 5)}h` }}
                  </span>
                </div>

                <div class="today-tomorrow-card" :style="{ '--shift-accent': headNurseData.tomorrow_shift?.shift_type.color || 'var(--muted)' }">
                  <span class="today-tomorrow-label">{{ texts.tomorrow }} – {{ tomorrowLabel }}</span>
                  <span class="today-tomorrow-value">
                    {{ headNurseData.tomorrow_shift ? getShiftName(headNurseData.tomorrow_shift.shift_type) : texts.noShift }}
                  </span>
                  <span v-if="headNurseData.tomorrow_shift" class="today-tomorrow-time">
                    {{ isNonWorkingShift(headNurseData.tomorrow_shift.shift_type) ? texts.allDay : `${headNurseData.tomorrow_shift.shift_type.start_time.slice(0, 5)}h – ${headNurseData.tomorrow_shift.shift_type.end_time.slice(0, 5)}h` }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Coluna 2: lista de turnos por tipo — as 4 categorias mostradas sempre, mesmo com 0 -->
            <div class="stats-column stats-column--wide">
              <span class="stats-column-title">{{ texts.shiftBreakdown }}</span>

              <div class="stats-shift-breakdown">
                <div
                  v-for="cat in shiftCategories"
                  :key="cat.key"
                  class="stats-shift-row"
                >
                  <span class="stats-shift-icon" :class="`stats-shift-icon--${cat.icon}`" aria-hidden="true">
                    <svg v-if="cat.icon === 'sunrise'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <path d="M17 18a5 5 0 0 0-10 0"></path>
                      <line x1="12" y1="2" x2="12" y2="9"></line>
                      <line x1="4.22" y1="10.22" x2="5.64" y2="11.64"></line>
                      <line x1="1" y1="18" x2="3" y2="18"></line>
                      <line x1="21" y1="18" x2="23" y2="18"></line>
                      <line x1="18.36" y1="11.64" x2="19.78" y2="10.22"></line>
                      <line x1="23" y1="22" x2="1" y2="22"></line>
                      <polyline points="8 6 12 2 16 6"></polyline>
                    </svg>
                    <svg v-else-if="cat.icon === 'sun'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <circle cx="12" cy="12" r="5"></circle>
                      <line x1="12" y1="1" x2="12" y2="3"></line>
                      <line x1="12" y1="21" x2="12" y2="23"></line>
                      <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                      <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                      <line x1="1" y1="12" x2="3" y2="12"></line>
                      <line x1="21" y1="12" x2="23" y2="12"></line>
                      <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                      <line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line>
                    </svg>
                    <svg v-else-if="cat.icon === 'moon'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <circle cx="12" cy="12" r="9"></circle>
                    </svg>
                  </span>
                  <span class="stats-shift-label">{{ cat.label }}</span>
                  <span class="stats-shift-count">
                    {{ headNurseData.shift_type_breakdown[cat.key] ?? 0 }} {{ texts.shiftsUnit }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Coluna 3: horas trabalhadas e trocas este mês, empilhados -->
            <div class="stats-column stats-column--narrow">
              <span class="stats-column-title">{{ currentMonthLabel }}</span>

              <div class="stats-kpi-column">
                <div class="stats-kpi-card">
                  <span class="stats-kpi-value">{{ headNurseData.monthly_hours }}h</span>
                  <span class="stats-kpi-label">{{ texts.hoursWorked }}</span>
                </div>

                <div class="stats-kpi-card">
                  <span class="stats-kpi-value">{{ headNurseData.swaps_this_month }}</span>
                  <span class="stats-kpi-label">{{ texts.swapsThisMonth }}</span>
                </div>
              </div>
            </div>

            <!-- Coluna 4: indicadores da equipa — exclusivo do head nurse -->
            <div class="stats-column stats-column--narrow">
              <span class="stats-column-title">{{ texts.myTeam }}</span>

              <div class="stats-kpi-column">
                <div class="stats-kpi-card">
                  <span class="stats-kpi-value">{{ headNurseData.team_pending_swaps_count }}</span>
                  <span class="stats-kpi-label">{{ texts.teamPendingSwaps }}</span>
                </div>

                <div class="stats-kpi-card">
                  <span class="stats-kpi-value">{{ headNurseData.team_swaps_this_month }}</span>
                  <span class="stats-kpi-label">{{ texts.teamSwapsThisMonth }}</span>
                </div>
              </div>
            </div>

          </div>
        </div>

      </template>


      <!-- ========================================== -->
      <!-- BLOCO ENFERMEIRO (NURSE)                   -->
      <!-- ========================================== -->
      <template v-if="isNurse">

        <!-- Cards de navegação clicáveis -->
        <div class="bento-grid">

          <NuxtLink to="/schedule-view" class="bento-card bento-card--hr bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </div>
            <h3>{{ texts.schedule }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Consultar o horário' : 'View schedule' }}</p>
            <!-- Chevron indicador de navegação -->
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/swaps" class="bento-card bento-card--vacations bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <polyline points="17 1 21 5 17 9"></polyline>
                <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                <polyline points="7 23 3 19 7 15"></polyline>
                <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
              </svg>
              <span v-if="pendingReceivedCount > 0" class="bento-badge">{{ pendingReceivedCount }}</span>
            </div>
            <h3>{{ texts.swaps }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Gerir pedidos de troca de turno' : 'Manage shift swap requests' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

        </div>

        <!-- Secção de resumo mensal (informação, não navegação) -->
        <div v-if="nurseData" class="dashboard-stats-section">
          <h2 class="stats-section-title">{{ texts.monthlySummary }} – {{ currentMonthLabel }}</h2>
          <div class="stats-kpi-row">

            <!-- Coluna 1: turno de hoje e de amanhã, empilhados -->
            <div class="stats-column stats-column--narrow">
              <span class="stats-column-title">{{ texts.myShifts }}</span>

              <div class="today-tomorrow-column">
                <div class="today-tomorrow-card" :style="{ '--shift-accent': nurseData.today_shift?.shift_type.color || 'var(--muted)' }">
                  <span class="today-tomorrow-label">{{ texts.today }} – {{ todayLabel }}</span>
                  <span class="today-tomorrow-value">
                    {{ nurseData.today_shift ? getShiftName(nurseData.today_shift.shift_type) : texts.noShift }}
                  </span>
                  <span v-if="nurseData.today_shift" class="today-tomorrow-time">
                    {{ isNonWorkingShift(nurseData.today_shift.shift_type) ? texts.allDay : `${nurseData.today_shift.shift_type.start_time.slice(0, 5)}h – ${nurseData.today_shift.shift_type.end_time.slice(0, 5)}h` }}
                  </span>
                </div>

                <div class="today-tomorrow-card" :style="{ '--shift-accent': nurseData.tomorrow_shift?.shift_type.color || 'var(--muted)' }">
                  <span class="today-tomorrow-label">{{ texts.tomorrow }} – {{ tomorrowLabel }}</span>
                  <span class="today-tomorrow-value">
                    {{ nurseData.tomorrow_shift ? getShiftName(nurseData.tomorrow_shift.shift_type) : texts.noShift }}
                  </span>
                  <span v-if="nurseData.tomorrow_shift" class="today-tomorrow-time">
                    {{ isNonWorkingShift(nurseData.tomorrow_shift.shift_type) ? texts.allDay : `${nurseData.tomorrow_shift.shift_type.start_time.slice(0, 5)}h – ${nurseData.tomorrow_shift.shift_type.end_time.slice(0, 5)}h` }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Coluna 2: lista de turnos por tipo — as 4 categorias mostradas sempre, mesmo com 0 -->
            <div class="stats-column stats-column--wide">
              <span class="stats-column-title">{{ texts.shiftBreakdown }}</span>

              <div class="stats-shift-breakdown">
                <div
                  v-for="cat in shiftCategories"
                  :key="cat.key"
                  class="stats-shift-row"
                >
                  <!-- Ícone pequeno por tipo de turno -->
                  <span class="stats-shift-icon" :class="`stats-shift-icon--${cat.icon}`" aria-hidden="true">
                    <!-- Manhã: sol nascente -->
                    <svg v-if="cat.icon === 'sunrise'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <path d="M17 18a5 5 0 0 0-10 0"></path>
                      <line x1="12" y1="2" x2="12" y2="9"></line>
                      <line x1="4.22" y1="10.22" x2="5.64" y2="11.64"></line>
                      <line x1="1" y1="18" x2="3" y2="18"></line>
                      <line x1="21" y1="18" x2="23" y2="18"></line>
                      <line x1="18.36" y1="11.64" x2="19.78" y2="10.22"></line>
                      <line x1="23" y1="22" x2="1" y2="22"></line>
                      <polyline points="8 6 12 2 16 6"></polyline>
                    </svg>
                    <!-- Tarde: sol -->
                    <svg v-else-if="cat.icon === 'sun'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <circle cx="12" cy="12" r="5"></circle>
                      <line x1="12" y1="1" x2="12" y2="3"></line>
                      <line x1="12" y1="21" x2="12" y2="23"></line>
                      <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                      <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                      <line x1="1" y1="12" x2="3" y2="12"></line>
                      <line x1="21" y1="12" x2="23" y2="12"></line>
                      <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                      <line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line>
                    </svg>
                    <!-- Noite: lua -->
                    <svg v-else-if="cat.icon === 'moon'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                    <!-- Folga: círculo vazio -->
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                      <circle cx="12" cy="12" r="9"></circle>
                    </svg>
                  </span>
                  <span class="stats-shift-label">{{ cat.label }}</span>
                  <span class="stats-shift-count">
                    {{ nurseData.shift_type_breakdown[cat.key] ?? 0 }} {{ texts.shiftsUnit }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Coluna 3: horas trabalhadas e trocas este mês, empilhados -->
            <div class="stats-column stats-column--narrow">
              <span class="stats-column-title">{{ currentMonthLabel }}</span>

              <div class="stats-kpi-column">
                <div class="stats-kpi-card">
                  <span class="stats-kpi-value">{{ nurseData.monthly_hours }}h</span>
                  <span class="stats-kpi-label">{{ texts.hoursWorked }}</span>
                </div>

                <div class="stats-kpi-card">
                  <span class="stats-kpi-value">{{ nurseData.swaps_this_month }}</span>
                  <span class="stats-kpi-label">{{ texts.swapsThisMonth }}</span>
                </div>
              </div>
            </div>

          </div>
        </div>

      </template>


      <!-- ========================================== -->
      <!-- CARDS DO ADMIN (RECURSOS HUMANOS)          -->
      <!-- ========================================== -->
      <template v-if="isAdmin">
        <div class="bento-grid">

          <NuxtLink to="/human-resources" class="bento-card bento-card--hr bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <h3>{{ texts.humanResources }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Gerir perfis e permissões' : 'Manage profiles and permissions' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/vacations" class="bento-card bento-card--vacations bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line>
              </svg>
            </div>
            <h3>{{ texts.vacations }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Aprovação e mapas de férias' : 'Leave approvals and tracking' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/sick-leaves" class="bento-card bento-card--sick bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
              </svg>
            </div>
            <h3>{{ texts.sickLeaves }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Registo e acompanhamento' : 'Recording and monitoring' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/shift-types" class="bento-card bento-card--shifts bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <h3>{{ texts.shiftTypes }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Configuração de tipos de turnos' : 'Configuration of shift patterns' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <NuxtLink to="/swaps-history" class="bento-card bento-card--vacations bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <polyline points="17 1 21 5 17 9"></polyline>
                <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                <polyline points="7 23 3 19 7 15"></polyline>
                <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
              </svg>
            </div>
            <h3>{{ texts.swapHistory }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Pedidos de troca aceites' : 'Accepted swap requests' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

          <!-- Link para estatísticas — admin e head_nurse -->
          <NuxtLink to="/statistics" class="bento-card bento-card--stats bento-card--nav">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
              </svg>
            </div>
            <h3>{{ texts.statistics }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Controlo de serviços e recursos' : 'Service and resource control' }}</p>
            <span class="bento-nav-chevron" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </span>
          </NuxtLink>

        </div>
      </template>

    </section>
  </main>
</template>
