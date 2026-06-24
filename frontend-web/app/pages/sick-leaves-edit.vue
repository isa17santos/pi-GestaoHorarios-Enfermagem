<script setup lang="ts">
// Apenas utilizadores autenticados podem aceder a esta página
definePageMeta({
  middleware: 'auth',
})

const config = useRuntimeConfig()
const router = useRouter()
const route = useRoute()
const { token, user: currentUser, fetchMe } = useAuth()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const leaveId = route.query.id
const loading = ref(false)
const fetching = ref(true)
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)
const errors = ref<Record<string, string>>({})

const form = ref({
  user_id: null as number | null,
  start_date: '',
  end_date: '',
  reason: ''
})

const nurses = ref<any[]>([])
const isNurseOpen = ref(false)

// Estados de Dropdowns e Calendários
const isStartCalendarOpen = ref(false)
const isEndCalendarOpen = ref(false)

// Data de visualização inicial no calendário
const startCalendarView = ref({
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear()
})

const endCalendarView = ref({
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear()
})

const monthNamesPT = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']
const monthNamesEN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const weekDaysPT = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S']
const weekDaysEN = ['S', 'M', 'T', 'W', 'T', 'F', 'S']

const getMonthName = (month: number) => {
  return currentLocale.value === 'pt' ? monthNamesPT[month - 1] : monthNamesEN[month - 1]
}

const getCalendarDays = (year: number, month: number) => {
  const daysInMonth = new Date(year, month, 0).getDate()
  const startDay = new Date(year, month - 1, 1).getDay()
  
  const cells = []
  for (let i = 0; i < startDay; i++) {
    cells.push({ day: null, dateStr: '' })
  }
  
  for (let d = 1; d <= daysInMonth; d++) {
    const mm = String(month).padStart(2, '0')
    const dd = String(d).padStart(2, '0')
    cells.push({ day: d, dateStr: `${year}-${mm}-${dd}` })
  }
  
  return cells
}

const prevMonth = (view: { month: number, year: number }) => {
  if (view.month === 1) {
    view.month = 12
    view.year--
  } else {
    view.month--
  }
}

const nextMonth = (view: { month: number, year: number }) => {
  if (view.month === 12) {
    view.month = 1
    view.year++
  } else {
    view.month++
  }
}

const isToday = (dateStr: string) => {
  if (!dateStr) return false
  const today = new Date()
  const y = today.getFullYear()
  const m = String(today.getMonth() + 1).padStart(2, '0')
  const d = String(today.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}` === dateStr
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return '-'
  const cleanDate = dateStr.substring(0, 10)
  const parts = cleanDate.split('-')
  const year = parts[0] || ''
  const month = parts[1] || ''
  const day = parts[2] || ''
  return `${day}-${month}-${year}`
}

const toggleStartCalendar = () => {
  isStartCalendarOpen.value = !isStartCalendarOpen.value
  isEndCalendarOpen.value = false
  isNurseOpen.value = false
  
  if (form.value.start_date) {
    const parts = form.value.start_date.substring(0, 10).split('-').map(Number)
    if (parts[0] && parts[1]) {
      startCalendarView.value = { year: parts[0], month: parts[1] }
    }
  }
}

const toggleEndCalendar = () => {
  isEndCalendarOpen.value = !isEndCalendarOpen.value
  isStartCalendarOpen.value = false
  isNurseOpen.value = false
  
  if (form.value.end_date) {
    const parts = form.value.end_date.substring(0, 10).split('-').map(Number)
    if (parts[0] && parts[1]) {
      endCalendarView.value = { year: parts[0], month: parts[1] }
    }
  }
}

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Editar Baixa Médica' : 'Edit Medical Leave',
  subtitle: currentLocale.value === 'pt' ? 'Atualize as informações do período de ausência médica' : 'Update medical absence period details',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  labels: {
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    startDate: currentLocale.value === 'pt' ? 'Data de Início' : 'Start Date',
    endDate: currentLocale.value === 'pt' ? 'Data de Fim' : 'End Date',
    reason: currentLocale.value === 'pt' ? 'Motivo (Opcional)' : 'Reason (Optional)',
  },
  placeholders: {
    selectNurse: currentLocale.value === 'pt' ? 'Selecione um enfermeiro' : 'Select a nurse',
    reason: currentLocale.value === 'pt' ? 'Ex: Recuperação de cirurgia, gripe, etc.' : 'e.g. Surgery recovery, flu, etc.',
  },
  save: currentLocale.value === 'pt' ? 'Atualizar Baixa Médica' : 'Update Medical Leave',
  saving: currentLocale.value === 'pt' ? 'A atualizar...' : 'Updating...',
  success: currentLocale.value === 'pt' ? 'Baixa médica atualizada com sucesso!' : 'Medical leave updated successfully!',
  error: currentLocale.value === 'pt' ? 'Erro ao atualizar baixa médica.' : 'Error updating medical leave.',
  fetchError: currentLocale.value === 'pt' ? 'Erro ao carregar dados da baixa médica.' : 'Error loading medical leave details.',
  validation: {
    nurseRequired: currentLocale.value === 'pt' ? 'A seleção do enfermeiro é obrigatória' : 'Nurse selection is required',
    startDateRequired: currentLocale.value === 'pt' ? 'A data de início é obrigatória' : 'Start date is required',
    endDateRequired: currentLocale.value === 'pt' ? 'A data de fim é obrigatória' : 'End date is required',
    dateOrder: currentLocale.value === 'pt' ? 'A data de fim deve ser posterior ou igual à data de início' : 'End date must be after or equal to start date',
    formError: currentLocale.value === 'pt' ? 'Por favor, corrija os erros no formulário.' : 'Please correct the errors in the form.',
  }
}))

const selectedNurseName = computed(() => {
  const nurse = nurses.value.find(n => n.id === form.value.user_id)
  return nurse ? nurse.name : texts.value.placeholders.selectNurse
})

// Procura os enfermeiros chefes e enfermeiros
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
    console.error('Fetch Nurses Error:', err)
  }
}

// Procura os dados originais da baixa e limpa a data extraindo apenas YYYY-MM-DD
const fetchLeave = async () => {
  if (!leaveId) {
    router.push('/sick-leaves')
    return
  }

  try {
    const response = await $fetch<{ data: any }>(`${config.public.apiBase}/medical-leaves/${leaveId}`, {
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json',
      }
    })
    
    const leave = response.data
    form.value = {
      user_id: leave.user_id,
      start_date: leave.start_date ? leave.start_date.substring(0, 10) : '',
      end_date: leave.end_date ? leave.end_date.substring(0, 10) : '',
      reason: leave.reason || ''
    }
  } catch (err) {
    console.error('Fetch Leave Error:', err)
    showNotification('fetchError', 'error')
  } finally {
    fetching.value = false
  }
}

const handleSubmit = async () => {
  errors.value = {}
  
  if (!form.value.user_id) errors.value.user_id = 'nurseRequired'
  if (!form.value.start_date) errors.value.start_date = 'startDateRequired'
  if (!form.value.end_date) errors.value.end_date = 'endDateRequired'
  
  if (form.value.start_date && form.value.end_date) {
    if (form.value.start_date > form.value.end_date) {
      errors.value.end_date = 'dateOrder'
    }
  }

  if (Object.keys(errors.value).length > 0) {
    showNotification('formError', 'error')
    return
  }

  loading.value = true
  try {
    await $fetch(`${config.public.apiBase}/medical-leaves/${leaveId}`, {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json',
      },
      body: {
        user_id: form.value.user_id,
        start_date: form.value.start_date,
        end_date: form.value.end_date,
        reason: form.value.reason || null,
      }
    })

    showNotification('success', 'success')
    
    setTimeout(() => {
      router.push('/sick-leaves')
    }, 1500)
    
  } catch (err: any) {
    console.error('Update Medical Leave Error:', err)
    
    if (err.statusCode === 422 && err.data?.errors) {
      const serverErrors = err.data.errors
      if (serverErrors.start_date) {
        errors.value.start_date = serverErrors.start_date[0]
      } else if (serverErrors.end_date) {
        errors.value.end_date = serverErrors.end_date[0]
      } else {
        showNotification('formError', 'error')
      }
    } else {
      showNotification('error', 'error')
    }
  } finally {
    loading.value = false
  }
}

// Fecha dropdowns e calendários ao clicar fora de qualquer wrapper
if (process.client) {
  window.addEventListener('click', (e) => {
    const target = e.target as HTMLElement
    if (!target.closest('.uc-select-wrapper')) {
      isNurseOpen.value = false
      isStartCalendarOpen.value = false
      isEndCalendarOpen.value = false
    }
  })
}

onMounted(async () => {
  if (!currentUser.value) {
    await fetchMe().catch(() => null)
  }
  
  if (currentUser.value?.role?.toLowerCase().trim() !== 'admin') {
    await navigateTo('/dashboard')
    return
  }
  
  await Promise.all([fetchNurses(), fetchLeave()])
})
</script>

<template>
  <main class="dashboard-layout user-create-page">
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
          <span>{{ (texts as any)[statusMessage.key] || (texts.validation as any)[statusMessage.key] || statusMessage.key }}</span>
        </div>
      </div>
    </transition>

    <!-- Barra Superior -->
    <div class="uc-top-bar">
      <div class="uc-title-group">
        <NuxtLink to="/sick-leaves" class="back-link">
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

    <!-- Formulário -->
    <section class="uc-card">
      <div v-if="fetching" class="loading-state">
        <div class="spinner"></div>
      </div>
      
      <form v-else @submit.prevent="handleSubmit" class="uc-form" novalidate>
        <div class="form-grid">
          <!-- Seleção de Enfermeiro -->
          <div class="form-group">
            <label>{{ texts.labels.nurse }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ active: isNurseOpen, 'select-error': errors.user_id }"
                @click.stop="isNurseOpen = !isNurseOpen; isStartCalendarOpen = false; isEndCalendarOpen = false"
              >
                <span>{{ selectedNurseName }}</span>
                <svg :class="{ rotate: isNurseOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </div>
              <transition name="fade-slide">
                <div v-if="isNurseOpen" class="uc-options nurse-list">
                  <div 
                    v-for="nurse in nurses" 
                    :key="nurse.id" 
                    class="uc-option" 
                    @click="form.user_id = nurse.id; isNurseOpen = false"
                  >
                    {{ nurse.name }}
                  </div>
                </div>
              </transition>
            </div>
            <transition name="fade">
              <span v-if="errors.user_id" class="field-error">{{ (texts.validation as any)[errors.user_id] }}</span>
            </transition>
          </div>

          <!-- Motivo -->
          <div class="form-group">
            <label>{{ texts.labels.reason }}</label>
            <input 
              v-model="form.reason" 
              type="text" 
              :placeholder="texts.placeholders.reason"
              class="uc-input"
            />
          </div>

          <!-- Data de Início Customizada -->
          <div class="form-group">
            <label>{{ texts.labels.startDate }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ active: isStartCalendarOpen, 'select-error': errors.start_date }"
                @click.stop="toggleStartCalendar"
              >
                <span>{{ form.start_date ? formatDate(form.start_date) : (currentLocale === 'pt' ? 'Selecione uma data' : 'Select a date') }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18" class="calendar-input-icon">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </div>
              
              <transition name="fade-slide">
                <div v-if="isStartCalendarOpen" class="custom-calendar-dropdown" @click.stop>
                  <div class="calendar-header">
                    <button type="button" class="calendar-nav-btn" @click="prevMonth(startCalendarView)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                        <polyline points="15 18 9 12 15 6"></polyline>
                      </svg>
                    </button>
                    <span class="calendar-month-title">
                      {{ getMonthName(startCalendarView.month) }} {{ startCalendarView.year }}
                    </span>
                    <button type="button" class="calendar-nav-btn" @click="nextMonth(startCalendarView)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                    </button>
                  </div>

                  <div class="calendar-weekdays">
                    <span v-for="day in (currentLocale === 'pt' ? weekDaysPT : weekDaysEN)" :key="day" class="weekday-cell">
                      {{ day }}
                    </span>
                  </div>

                  <div class="calendar-days-grid">
                    <div 
                      v-for="(cell, index) in getCalendarDays(startCalendarView.year, startCalendarView.month)" 
                      :key="index"
                      :class="[
                        'day-cell', 
                        { 
                          'empty': !cell.day, 
                          'selected': cell.day && form.start_date === cell.dateStr,
                          'today': cell.day && isToday(cell.dateStr)
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
            <transition name="fade">
              <span v-if="errors.start_date" class="field-error">
                {{ (texts.validation as any)[errors.start_date] || errors.start_date }}
              </span>
            </transition>
          </div>

          <!-- Data de Fim Customizada -->
          <div class="form-group">
            <label>{{ texts.labels.endDate }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ active: isEndCalendarOpen, 'select-error': errors.end_date }"
                @click.stop="toggleEndCalendar"
              >
                <span>{{ form.end_date ? formatDate(form.end_date) : (currentLocale === 'pt' ? 'Selecione uma data' : 'Select a date') }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18" class="calendar-input-icon">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </div>
              
              <transition name="fade-slide">
                <div v-if="isEndCalendarOpen" class="custom-calendar-dropdown" @click.stop>
                  <div class="calendar-header">
                    <button type="button" class="calendar-nav-btn" @click="prevMonth(endCalendarView)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                        <polyline points="15 18 9 12 15 6"></polyline>
                      </svg>
                    </button>
                    <span class="calendar-month-title">
                      {{ getMonthName(endCalendarView.month) }} {{ endCalendarView.year }}
                    </span>
                    <button type="button" class="calendar-nav-btn" @click="nextMonth(endCalendarView)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                    </button>
                  </div>

                  <div class="calendar-weekdays">
                    <span v-for="day in (currentLocale === 'pt' ? weekDaysPT : weekDaysEN)" :key="day" class="weekday-cell">
                      {{ day }}
                    </span>
                  </div>

                  <div class="calendar-days-grid">
                    <div 
                      v-for="(cell, index) in getCalendarDays(endCalendarView.year, endCalendarView.month)" 
                      :key="index"
                      :class="[
                        'day-cell', 
                        { 
                          'empty': !cell.day, 
                          'selected': cell.day && form.end_date === cell.dateStr,
                          'today': cell.day && isToday(cell.dateStr)
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
            <transition name="fade">
              <span v-if="errors.end_date" class="field-error">
                {{ (texts.validation as any)[errors.end_date] || errors.end_date }}
              </span>
            </transition>
          </div>
        </div>

        <div class="uc-actions">
          <button type="submit" class="submit-btn" :class="{ loading }" :disabled="loading">
            {{ loading ? texts.saving : texts.save }}
          </button>
        </div>
      </form>
    </section>
  </main>
</template>