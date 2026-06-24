<script setup lang="ts">
// Apenas utilizadores autenticados podem aceder a esta página
definePageMeta({
  middleware: 'auth',
})

const { token, user: currentUser, fetchMe } = useAuth()
const config = useRuntimeConfig()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const leaves = ref<any[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

// Sistema de notificações local
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)

// Estados para o Modal de confirmação de exclusão
const showDeleteModal = ref(false)
const leaveIdToDelete = ref<number | null>(null)

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Gestão de Baixas Médicas' : 'Medical Leaves Management',
  subtitle: currentLocale.value === 'pt' ? 'Administração de ausências médicas dos profissionais' : 'Administration of professional medical absences',
  createLeave: currentLocale.value === 'pt' ? 'Atribuir Baixa Médica' : 'Assign Medical Leave',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  tableHeader: {
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    startDate: currentLocale.value === 'pt' ? 'Data de Início' : 'Start Date',
    endDate: currentLocale.value === 'pt' ? 'Data de Fim' : 'End Date',
    reason: currentLocale.value === 'pt' ? 'Motivo' : 'Reason',
    status: currentLocale.value === 'pt' ? 'Estado' : 'Status',
    actions: currentLocale.value === 'pt' ? 'Ações' : 'Actions',
  },
  status: {
    all: currentLocale.value === 'pt' ? 'Todos os Estados' : 'All Status',
    past: currentLocale.value === 'pt' ? 'Já Passou' : 'Past',
    ongoing: currentLocale.value === 'pt' ? 'A Decorrer' : 'Ongoing',
    future: currentLocale.value === 'pt' ? 'Datas Futuras' : 'Future',
  },
  noLeaves: currentLocale.value === 'pt' ? 'Nenhuma baixa médica encontrada.' : 'No medical leaves found.',
  loading: currentLocale.value === 'pt' ? 'A carregar base de dados...' : 'Loading database...',
  deleteSuccess: currentLocale.value === 'pt' ? 'Baixa médica removida com sucesso!' : 'Medical leave removed successfully!',
  deleteError: currentLocale.value === 'pt' ? 'Erro ao remover baixa médica.' : 'Error removing medical leave.',
}))

// Calcula o estado dinamicamente comparando datas sem sofrer com desvios de fuso horário ou carateres ISO extras
const getLeaveStatus = (startDateStr: string, endDateStr: string) => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  // Limpa sufixos ISO temporais das datas retornadas pelo banco
  const cleanStart = startDateStr.substring(0, 10)
  const cleanEnd = endDateStr.substring(0, 10)
  
  const sParts = cleanStart.split('-').map(Number)
  const eParts = cleanEnd.split('-').map(Number)
  
  const sYear = sParts[0] ?? 0
  const sMonth = sParts[1] ?? 1
  const sDay = sParts[2] ?? 1
  
  const eYear = eParts[0] ?? 0
  const eMonth = eParts[1] ?? 1
  const eDay = eParts[2] ?? 1
  
  const start = new Date(sYear, sMonth - 1, sDay)
  const end = new Date(eYear, eMonth - 1, eDay)
  
  if (end.getTime() < today.getTime()) {
    return 'past'
  } else if (start.getTime() <= today.getTime() && end.getTime() >= today.getTime()) {
    return 'ongoing'
  } else {
    return 'future'
  }
}

// Formata a data de forma segura no formato d-m-Y solicitado
const formatDate = (dateStr: string) => {
  if (!dateStr) return '-'
  
  // Extrai apenas 'YYYY-MM-DD'
  const cleanDate = dateStr.substring(0, 10)
  const parts = cleanDate.split('-')
  const year = parts[0] || ''
  const month = parts[1] || ''
  const day = parts[2] || ''
  
  return `${day}-${month}-${year}`
}

// Procura todas as baixas médicas
const fetchLeaves = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await $fetch<{ data: any[] }>(`${config.public.apiBase}/medical-leaves?t=${Date.now()}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    leaves.value = response.data
  } catch (e) {
    console.error('Error loading leaves:', e)
    error.value = currentLocale.value === 'pt' ? 'Erro ao carregar a lista.' : 'Error loading list.'
  } finally {
    loading.value = false
  }
}

// Remove uma baixa
const deleteLeave = async (id: number) => {
  try {
    await $fetch(`${config.public.apiBase}/medical-leaves/${id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json',
      },
    })

    // Sucesso: Atualiza o estado local e exibe a notificação
    leaves.value = leaves.value.filter(l => l.id !== id)
    showNotification('deleteSuccess', 'success')
  } catch (err: any) {
    console.error('Delete Error:', err)
    showNotification('deleteError', 'error')
  }
}

const confirmDelete = (id: number) => {
  leaveIdToDelete.value = id
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (leaveIdToDelete.value) {
    await deleteLeave(leaveIdToDelete.value)
    showDeleteModal.value = false
    leaveIdToDelete.value = null
  }
}

// Paginação e filtros
const currentPage = ref(1)
const itemsPerPage = 6
const searchQuery = ref('')
const selectedStatus = ref('')
const isStatusOpen = ref(false)

// Fecha o dropdown de status ao clicar fora
if (process.client) {
  window.addEventListener('click', (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.custom-select-wrapper')) {
      isStatusOpen.value = false
    }
  })
}

const filteredLeaves = computed(() => {
  return leaves.value.filter(l => {
    // Filtro por nome do enfermeiro
    const nurseName = l.user?.name || ''
    const matchesName = nurseName.toLowerCase().includes(searchQuery.value.toLowerCase())

    // Filtro por estado (past, ongoing, future)
    const status = getLeaveStatus(l.start_date, l.end_date)
    const matchesStatus = !selectedStatus.value || status === selectedStatus.value

    return matchesName && matchesStatus
  })
})

const totalPages = computed(() => Math.ceil(filteredLeaves.value.length / itemsPerPage))

const paginatedLeaves = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredLeaves.value.slice(start, end)
})

const setPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Reinicia a paginação quando qualquer filtro muda
watch([searchQuery, selectedStatus], () => {
  currentPage.value = 1
})

onMounted(async () => {
  if (!currentUser.value) {
    await fetchMe().catch(() => null)
  }
  // Apenas utilizadores admin têm acesso
  const userRole = currentUser.value?.role?.toLowerCase().trim() || ''
  if (userRole !== 'admin') {
    await navigateTo('/dashboard')
    return
  }
  await fetchLeaves()
})
</script>

<template>
  <main class="dashboard-layout hr-page">
    <AppNavbar />

    <!-- Notificações Gerais (Centrado no topo) -->
    <transition name="slide-down">
      <div v-if="statusMessage" :class="['global-toast', statusMessage.type]">
        <div class="toast-content">
          <svg v-if="statusMessage.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="3" width="22">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{{ (texts as any)[statusMessage.key] || statusMessage.key }}</span>
        </div>
      </div>
    </transition>

    <section class="hr-content">
      <!-- Barra superior idêntica ao Recursos Humanos -->
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

      <!-- Toolbar Principal com filtros -->
      <div class="hr-toolbar">
        <div class="filters-group">
          <!-- 1. Barra de pesquisa -->
          <div class="hr-search-wrapper">
            <div class="search-input-container">
              <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input v-model="searchQuery" type="text"
                :placeholder="currentLocale === 'pt' ? 'Pesquisar por enfermeiro...' : 'Search by nurse...'"
                class="search-input" />
            </div>
          </div>

          <!-- 2. Filtro personalizado de Estado da Baixa -->
          <div class="custom-select-wrapper">
            <div class="custom-select-trigger" @click.stop="isStatusOpen = !isStatusOpen">
              <span>{{ selectedStatus ? (texts.status as any)[selectedStatus] : texts.status.all }}</span>
              <svg :class="['chevron-icon', { rotate: isStatusOpen }]" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>
            <transition name="fade-slide">
              <div v-if="isStatusOpen" class="custom-options">
                <div class="option-item" @click="selectedStatus = ''; isStatusOpen = false">
                  {{ texts.status.all }}
                </div>
                <div class="option-item" @click="selectedStatus = 'ongoing'; isStatusOpen = false">
                  {{ texts.status.ongoing }}
                </div>
                <div class="option-item" @click="selectedStatus = 'future'; isStatusOpen = false">
                  {{ texts.status.future }}
                </div>
                <div class="option-item" @click="selectedStatus = 'past'; isStatusOpen = false">
                  {{ texts.status.past }}
                </div>
              </div>
            </transition>
          </div>
        </div>

        <!-- Botão para Atribuir Baixa -->
        <NuxtLink to="/sick-leaves-create" class="create-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          {{ texts.createLeave }}
        </NuxtLink>
      </div>

      <!-- Tabela com listagem das baixas -->
      <div class="hr-card">
        <div v-if="loading" class="hr-state-msg">
          <div class="spinner"></div>
          <p>{{ texts.loading }}</p>
        </div>

        <div v-else-if="error" class="hr-state-msg error">
          <p>{{ error }}</p>
        </div>

        <div v-else-if="leaves.length === 0" class="hr-state-msg">
          <p>{{ texts.noLeaves }}</p>
        </div>

        <div v-else class="table-responsive">
          <table class="hr-table">
            <thead>
              <tr>
                <th class="col-left">{{ texts.tableHeader.nurse }}</th>
                <th class="col-center">{{ texts.tableHeader.startDate }}</th>
                <th class="col-center">{{ texts.tableHeader.endDate }}</th>
                <th class="col-center">{{ texts.tableHeader.reason }}</th>
                <th class="col-center">{{ texts.tableHeader.status }}</th>
                <th class="col-right">{{ texts.tableHeader.actions }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="l in paginatedLeaves" :key="l.id">
                <td class="col-left">
                  <div class="user-cell">
                    <div class="user-avatar">
                      {{ l.user ? l.user.name.charAt(0).toUpperCase() : '?' }}
                    </div>
                    <span class="user-name-text">{{ l.user ? l.user.name : 'Utilizador Removido' }}</span>
                  </div>
                </td>
                <td class="col-center">{{ formatDate(l.start_date) }}</td>
                <td class="col-center">{{ formatDate(l.end_date) }}</td>
                <td class="col-center">
                  <span class="reason-text" :title="l.reason || '-'">{{ l.reason || '-' }}</span>
                </td>
                <td class="col-center">
                  <span :class="['status-indicator', getLeaveStatus(l.start_date, l.end_date)]">
                    {{ (texts.status as any)[getLeaveStatus(l.start_date, l.end_date)] }}
                  </span>
                </td>
                <td class="actions-cell col-right">
                  <NuxtLink :to="`/sick-leaves-edit?id=${l.id}`" class="action-btn edit"
                    :title="currentLocale === 'pt' ? 'Editar' : 'Edit'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </NuxtLink>
                  <button @click="confirmDelete(l.id)" class="action-btn delete"
                    :title="currentLocale === 'pt' ? 'Remover' : 'Remove'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      <line x1="10" y1="11" x2="10" y2="17"></line>
                      <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Paginação idêntica ao Recursos Humanos -->
      <div v-if="totalPages > 1" class="hr-pagination">
        <button class="pagination-btn" :disabled="currentPage === 1" @click="setPage(currentPage - 1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </button>
        <div class="pagination-numbers">
          <button v-for="p in totalPages" :key="p" :class="['page-num', { active: p === currentPage }]"
            @click="setPage(p)">
            {{ p }}
          </button>
        </div>
        <button class="pagination-btn" :disabled="currentPage === totalPages" @click="setPage(currentPage + 1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </button>
      </div>
    </section>

    <!-- Modal de Remoção de Baixa Médica -->
    <transition name="fade">
      <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
        <div class="modal-card">
          <div class="modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
              </path>
            </svg>
          </div>
          <h2>{{ currentLocale === 'pt' ? 'Confirmar Remoção' : 'Confirm Removal' }}</h2>
          <p>{{ currentLocale === 'pt' ? 'Tem a certeza que deseja remover esta baixa médica? Esta ação não pode ser revertida e os turnos originais do profissional serão repostos.' : 'Are you sure you want to remove this medical leave? This action cannot be undone and original shifts of the professional will be restored.' }}</p>

          <div class="modal-actions">
            <button class="modal-btn cancel" @click="showDeleteModal = false">
              {{ currentLocale === 'pt' ? 'Cancelar' : 'Cancel' }}
            </button>
            <button class="modal-btn confirm" @click="executeDelete">
              {{ currentLocale === 'pt' ? 'Sim, Remover' : 'Yes, Remove' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </main>
</template>