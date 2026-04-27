<script setup lang="ts">
// Only a authenticated user can access this page
definePageMeta({
  middleware: 'auth',
})

const { token, user: currentUser, fetchMe } = useAuth()
const config = useRuntimeConfig()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const users = ref<any[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

// Notification system state
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)

// States for the confirmation Modal
const showDeleteModal = ref(false)
const userIdToDelete = ref<number | null>(null)

// Function to show custom notifications
const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Gestão de Recursos Humanos' : 'Human Resources Management',
  subtitle: currentLocale.value === 'pt' ? 'Administração de perfis de enfermagem' : 'Nursing profiles administration',
  createUser: currentLocale.value === 'pt' ? 'Criar novo utilizador' : 'Create new user',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  tableHeader: {
    name: currentLocale.value === 'pt' ? 'Nome' : 'Name',
    role: currentLocale.value === 'pt' ? 'Cargo' : 'Role',
    status: currentLocale.value === 'pt' ? 'Estado' : 'Status',
    actions: currentLocale.value === 'pt' ? 'Ações' : 'Actions',
  },
  roles: {
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    head_nurse: currentLocale.value === 'pt' ? 'Enfermeiro Chefe' : 'Head Nurse',
  },
  status: {
    active: currentLocale.value === 'pt' ? 'Ativo' : 'Active',
    inactive: currentLocale.value === 'pt' ? 'Inativo' : 'Inactive',
  },
  noUsers: currentLocale.value === 'pt' ? 'Nenhum utilizador encontrado.' : 'No users found.',
  loading: currentLocale.value === 'pt' ? 'A carregar base de dados...' : 'Loading database...',
  deleteSuccess: currentLocale.value === 'pt' ? 'Utilizador removido com sucesso!' : 'User removed successfully!',
  deleteError: currentLocale.value === 'pt' ? 'Erro ao remover utilizador.' : 'Error removing user.',
}))

// formats the role to be more readable
const formatRole = (role: string) => {
  const r = role.toLowerCase().trim().replace(' ', '_')
  if (r === 'nurse') return texts.value.roles.nurse
  if (r === 'head_nurse') return texts.value.roles.head_nurse
  return role
}

// finds all users with role nurse or head_nurse 
const fetchUsers = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await $fetch<{ data: any[] }>(`${config.public.apiBase}/users?t=${Date.now()}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    users.value = response.data.filter(u => {
      const role = u.role.toLowerCase().trim().replace(' ', '_')
      return role === 'nurse' || role === 'head_nurse'
    })
  } catch (e) {
    console.error('Error loading users:', e)
    error.value = currentLocale.value === 'pt' ? 'Erro ao carregar a lista.' : 'Error loading list.'
  } finally {
    loading.value = false
  }
}

// Deletes a user and shows a custom notification
const deleteUser = async (id: number) => {
  try {
    await $fetch(`${config.public.apiBase}/users/${id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json',
      },
    })

    // Success: Update local state and show notification
    users.value = users.value.filter(u => u.id !== id)
    showNotification('deleteSuccess', 'success')
  } catch (err: any) {
    console.error('Delete Error:', err)
    // Error: Show error in UI notification instead of system alert
    showNotification('deleteError', 'error')
  }
}

const confirmDelete = (id: number) => {
  userIdToDelete.value = id
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (userIdToDelete.value) {
    await deleteUser(userIdToDelete.value)
    showDeleteModal.value = false
    userIdToDelete.value = null
  }
}

// Pagination and filters
const currentPage = ref(1)
const itemsPerPage = 6
const searchQuery = ref('')
const selectedRole = ref('')
const selectedStatus = ref('')

// States for custom dropdown menus
const isRoleOpen = ref(false)
const isStatusOpen = ref(false)

// Close menus when clicking outside
if (process.client) {
  window.addEventListener('click', (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.custom-select-wrapper')) {
      isRoleOpen.value = false
      isStatusOpen.value = false
    }
  })
}

const filteredUsers = computed(() => {
  return users.value.filter(u => {
    // Filter by name
    const matchesName = u.name.toLowerCase().includes(searchQuery.value.toLowerCase())

    // Filter by role
    const matchesRole = !selectedRole.value || u.role.toLowerCase().trim().replace(' ', '_') === selectedRole.value

    // Filter by status
    const matchesStatus = !selectedStatus.value || (selectedStatus.value === 'active' ? u.active : !u.active)

    return matchesName && matchesRole && matchesStatus
  })
})

// Calculates the number of pages
const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage))

// Filters the users to show only those on the current page
const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredUsers.value.slice(start, end)
})

// Function to change page
const setPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Reset page when any filter changes
watch([searchQuery, selectedRole, selectedStatus], () => {
  currentPage.value = 1
})


onMounted(async () => {
  if (!currentUser.value) {
    await fetchMe().catch(() => null)
  }
  // only admin users can access this page, if not redirect to dashboard
  const userRole = currentUser.value?.role?.toLowerCase().trim() || ''
  if (userRole !== 'admin') {
    await navigateTo('/dashboard')
    return
  }
  // if the user is admin, fetch the users
  await fetchUsers()
})
</script>

<template>
  <main class="dashboard-layout hr-page">
    <AppNavbar />

    <!-- General Notifications (Centered at top) -->
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
          <span>{{ (texts as any)[statusMessage.key] }}</span>
        </div>
      </div>
    </transition>

    <section class="hr-content">
      <!-- Section header -->
      
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

      <!-- Main Toolbar -->
      <div class="hr-toolbar">
        <div class="filters-group">
          <!-- 1. Search Bar -->
          <div class="hr-search-wrapper">
            <div class="search-input-container">
              <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input v-model="searchQuery" type="text"
                :placeholder="currentLocale === 'pt' ? 'Pesquisar por nome...' : 'Search by name...'"
                class="search-input" />
            </div>
          </div>

          <!-- 2. Custom Role Select -->
          <div class="custom-select-wrapper">
            <div class="custom-select-trigger" @click.stop="isRoleOpen = !isRoleOpen; isStatusOpen = false">
              <span>{{ selectedRole ? formatRole(selectedRole) : (currentLocale === 'pt' ? 'Todos os Cargos' : 'All Roles') }}</span>
              <svg :class="['chevron-icon', { rotate: isRoleOpen }]" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>
            <transition name="fade-slide">
              <div v-if="isRoleOpen" class="custom-options">
                <div class="option-item" @click="selectedRole = ''; isRoleOpen = false">
                  {{ currentLocale === 'pt' ? 'Todos os Cargos' : 'All Roles' }}
                </div>
                <div class="option-item" @click="selectedRole = 'nurse'; isRoleOpen = false">
                  {{ texts.roles.nurse }}
                </div>
                <div class="option-item" @click="selectedRole = 'head_nurse'; isRoleOpen = false">
                  {{ texts.roles.head_nurse }}
                </div>
              </div>
            </transition>
          </div>

          <!-- 3. Custom Status Select -->
          <div class="custom-select-wrapper">
            <div class="custom-select-trigger" @click.stop="isStatusOpen = !isStatusOpen; isRoleOpen = false">
              <span>{{ selectedStatus ? (selectedStatus === 'active' ? texts.status.active : texts.status.inactive) :
                (currentLocale === 'pt' ? 'Todos os Estados' : 'All Status') }}</span>
              <svg :class="['chevron-icon', { rotate: isStatusOpen }]" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>
            <transition name="fade-slide">
              <div v-if="isStatusOpen" class="custom-options">
                <div class="option-item" @click="selectedStatus = ''; isStatusOpen = false">
                  {{ currentLocale === 'pt' ? 'Todos os Estados' : 'All Status' }}
                </div>
                <div class="option-item" @click="selectedStatus = 'active'; isStatusOpen = false">
                  {{ texts.status.active }}
                </div>
                <div class="option-item" @click="selectedStatus = 'inactive'; isStatusOpen = false">
                  {{ texts.status.inactive }}
                </div>
              </div>
            </transition>
          </div>
        </div>

        <!-- Create User Button -->
        <NuxtLink to="/user-create" class="create-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          {{ texts.createUser }}
        </NuxtLink>
      </div>
      <div class="hr-card">
        <div v-if="loading" class="hr-state-msg">
          <div class="spinner"></div>
          <p>{{ texts.loading }}</p>
        </div>

        <div v-else-if="error" class="hr-state-msg error">
          <p>{{ error }}</p>
        </div>

        <div v-else-if="users.length === 0" class="hr-state-msg">
          <p>{{ texts.noUsers }}</p>
        </div>

        <div v-else class="table-responsive">
          <table class="hr-table">
            <thead>
              <tr>
                <th class="col-left">{{ texts.tableHeader.name }}</th>
                <th class="col-center">{{ texts.tableHeader.role }}</th>
                <th class="col-center">{{ texts.tableHeader.status }}</th>
                <th class="col-right">{{ texts.tableHeader.actions }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in paginatedUsers" :key="u.id">
                <td class="col-left">
                  <div class="user-cell">
                    <div class="user-avatar">
                      {{ u.name.charAt(0).toUpperCase() }}
                    </div>
                    <span class="user-name-text">{{ u.name }}</span>
                  </div>
                </td>
                <td class="col-center">
                  <span :class="['role-badge', u.role.toLowerCase().trim().replace(' ', '_')]">
                    {{ formatRole(u.role) }}
                  </span>
                </td>
                <td class="col-center">
                  <span :class="['status-indicator', u.active ? 'active' : 'inactive']">
                    {{ u.active ? texts.status.active : texts.status.inactive }}
                  </span>
                </td>
                <td class="actions-cell col-right">
                  <NuxtLink :to="`/user-edit?id=${u.id}`" class="action-btn edit"
                    :title="currentLocale === 'pt' ? 'Editar' : 'Edit'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </NuxtLink>
                  <button @click="confirmDelete(u.id)" class="action-btn delete"
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
          <p>{{ currentLocale === 'pt' ? 'Tem a certeza que deseja remover este utilizador? Esta ação não pode ser revertida.' : 'Are you sure you want to remove this user ? This action cannot be undone.' }}</p>

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
