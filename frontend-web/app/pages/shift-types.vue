<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

type ShiftType = {
  id: number
  name: string
  color: string | null
  start_time: string
  end_time: string
  min_nurses: number
}

const { token, user: currentUser, fetchMe } = useAuth()
const config = useRuntimeConfig()
const router = useRouter()
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const shiftTypes = ref<ShiftType[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)
const showDeleteModal = ref(false)
const shiftTypeIdToDelete = ref<number | null>(null)

const currentPage = ref(1)
const itemsPerPage = 6
const searchQuery = ref('')
const selectedShiftGroup = ref<'all' | 'work' | 'non_work'>('all')
const isShiftGroupOpen = ref(false)

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Gestão de Tipos de Turno' : 'Shift Types Management',
  subtitle: currentLocale.value === 'pt' ? 'Configuração de padrões de turnos da equipa' : 'Configure team shift patterns',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  create: currentLocale.value === 'pt' ? 'Novo tipo de turno' : 'New shift type',
  loading: currentLocale.value === 'pt' ? 'A carregar tipos de turno...' : 'Loading shift types...',
  empty: currentLocale.value === 'pt' ? 'Nenhum tipo de turno encontrado.' : 'No shift types found.',
  fetchError: currentLocale.value === 'pt' ? 'Erro ao carregar os tipos de turno.' : 'Error loading shift types.',
  search: currentLocale.value === 'pt' ? 'Pesquisar por nome...' : 'Search by name...',
  allShiftGroups: currentLocale.value === 'pt' ? 'Todos os Turnos' : 'All Shift Types',
  workShiftGroup: currentLocale.value === 'pt' ? 'Turnos Laborais' : 'Working Shifts',
  nonWorkShiftGroup: currentLocale.value === 'pt' ? 'Turnos Não Laborais' : 'Non-working Shifts',
  notDefined: currentLocale.value === 'pt' ? 'Não definido' : 'Not defined',
  deleteSuccess: currentLocale.value === 'pt' ? 'Tipo de turno removido com sucesso!' : 'Shift type removed successfully!',
  deleteError: currentLocale.value === 'pt' ? 'Erro ao remover tipo de turno.' : 'Error removing shift type.',
  soon: currentLocale.value === 'pt' ? 'Funcionalidade disponível em breve.' : 'Feature available soon.',
  tableHeader: {
    name: currentLocale.value === 'pt' ? 'Nome' : 'Name',
    color: currentLocale.value === 'pt' ? 'Cor' : 'Color',
    time: currentLocale.value === 'pt' ? 'Horário' : 'Time',
    minNurses: currentLocale.value === 'pt' ? 'Mín. enfermeiros' : 'Min. nurses',
    actions: currentLocale.value === 'pt' ? 'Ações' : 'Actions',
  },
  actions: {
    edit: currentLocale.value === 'pt' ? 'Editar' : 'Edit',
    remove: currentLocale.value === 'pt' ? 'Remover' : 'Remove',
  },
}))

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const deleteShiftType = async (id: number) => {
  try {
    await $fetch(`${config.public.apiBase}/shift-types/${id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
    })

    await fetchShiftTypes()
    showNotification('deleteSuccess', 'success')
  } catch (err) {
    console.error('Delete shift type error:', err)
    showNotification('deleteError', 'error')
  }
}

const confirmDelete = (id: number) => {
  shiftTypeIdToDelete.value = id
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (shiftTypeIdToDelete.value) {
    await deleteShiftType(shiftTypeIdToDelete.value)
    showDeleteModal.value = false
    shiftTypeIdToDelete.value = null
  }
}

const normalizeText = (value: string) => {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim()
}

// Normaliza para comparação, incluindo camelCase, snake_case e kebab-case.
const normalizeShiftNameForMatch = (value: string) => {
  return normalizeText(
    value
      .replace(/([a-z])([A-Z])/g, '$1 $2')
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
  )
}

const compactMatchText = (value: string) => {
  return normalizeShiftNameForMatch(value).replace(/\s+/g, '')
}

const toTitleCase = (value: string) => {
  return value
    .split(' ')
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

const resolveShiftLabel = (name: string): { pt: string, en: string } => {
  const normalizedName = normalizeShiftNameForMatch(name)

  const dictionary: Array<{ pattern: RegExp, labels: { pt: string, en: string } }> = [
    {
      pattern: /\bday\s*off\b|\bday[-_]?off\b|\brest\b|\bfolga\b/,
      labels: { pt: 'Descanso', en: 'Day Off' },
    },
    {
      pattern: /\bmorning\b|\bday\s+shift\b|\bmanha\b|\bmatinal\b/,
      labels: { pt: 'Manhã', en: 'Morning' },
    },
    {
      pattern: /\bafternoon\b|\bevening\b|\btarde\b|\bvespertino\b/,
      labels: { pt: 'Tarde', en: 'Afternoon' },
    },
    {
      pattern: /\bnight\b|\bovernight\b|\bnoite\b|\bnoturno\b|\bnocturno\b/,
      labels: { pt: 'Noite', en: 'Night' },
    },
    {
      pattern: /\bvacation\b|\bvacations\b|\bholiday\b|\bholidays\b|\bannual\s+leave\b|\btime[\s_-]*off\b|\bferias\b/,
      labels: { pt: 'Férias', en: 'Vacation' },
    },
    {
      pattern: /\bparental\s+leave\b|\bmaternity\s+leave\b|\bpaternity\s+leave\b|\blicenca\s+parental\b/,
      labels: { pt: 'Licença Parental', en: 'Parental Leave' },
    },
    {
      pattern: /\bsick\s+leave\b|\bsickness\b|\billness\b|\bmedical\s+leave\b|\bbaixa\b/,
      labels: { pt: 'Baixa', en: 'Sick Leave' },
    },
  ]

  const found = dictionary.find((item) => item.pattern.test(normalizedName))
  if (found) return found.labels

  // Fallback resiliente para turnos futuros: mantém legível mesmo sem tradução dedicada.
  const pretty = toTitleCase(normalizedName)
  return { pt: pretty, en: pretty }
}

const getShiftDisplayName = (name: string) => {
  const labels = resolveShiftLabel(name)
  return currentLocale.value === 'pt' ? labels.pt : labels.en
}

const isNonWorkingShift = (name: string) => {
  const normalizedName = normalizeShiftNameForMatch(name)
  return /\bday\s*off\b|\bday[-_]?off\b|\brest\b|\bfolga\b|\bvacation\b|\bvacations\b|\bholiday\b|\bholidays\b|\bannual\s+leave\b|\btime[\s_-]*off\b|\bferias\b|\bparental\s+leave\b|\bmaternity\s+leave\b|\bpaternity\s+leave\b|\blicenca\s+parental\b|\bsick\s+leave\b|\bsickness\b|\billness\b|\bmedical\s+leave\b|\bbaixa\b/.test(normalizedName)
}

const shiftGroupLabel = computed(() => {
  if (selectedShiftGroup.value === 'work') return texts.value.workShiftGroup
  if (selectedShiftGroup.value === 'non_work') return texts.value.nonWorkShiftGroup
  return texts.value.allShiftGroups
})

const getShiftInitial = (name: string): string => {
  const displayName = getShiftDisplayName(name)
  const normalizedDisplayName = normalizeText(displayName)
  const firstLetter = normalizedDisplayName.match(/[a-z]/i)?.[0] ?? displayName.charAt(0)
  return firstLetter.toUpperCase()
}

const normalizeHex = (value: string | null | undefined) => {
  if (!value) return '#000000'
  const normalized = value.trim().toUpperCase()
  const isHex = /^#([0-9A-F]{3}|[0-9A-F]{6})$/i.test(normalized)
  return isHex ? normalized : '#000000'
}

const formatTime = (time: string) => {
  const [hours = '00', minutes = '00'] = time.split(':')
  return `${hours}:${minutes}`
}

const isValidHexColor = (value: string | null | undefined) => {
  if (!value) return false
  return /^#([0-9A-F]{3}|[0-9A-F]{6})$/i.test(value.trim())
}

// Mantém a mesma paleta usada em schedule-create-grid.vue.
const schedulePalette = [
  '#d9f3ff',
  '#dff7e8',
  '#fff3d6',
  '#f4e6ff',
  '#ffdfe4',
  '#e3efff',
  '#fce7d8',
]

const shiftTypeStyleMap = computed(() => {
  return shiftTypes.value.reduce<Record<number, string>>((acc, shiftType, index) => {
    const fallbackColor = schedulePalette[index % schedulePalette.length] ?? '#f5f5f7'
    const dbColor = isValidHexColor(shiftType.color) ? normalizeHex(shiftType.color) : null
    acc[shiftType.id] = dbColor || fallbackColor
    return acc
  }, {})
})

const getShiftTypeBackgroundColor = (shiftTypeId: number) => {
  return shiftTypeStyleMap.value[shiftTypeId] || '#f5f5f7'
}

const persistSchedulePaletteColors = async (items: ShiftType[]) => {
  const requests: Promise<void>[] = []

  items.forEach((item, index) => {
    if (isValidHexColor(item.color)) return

    const fallbackColor = schedulePalette[index % schedulePalette.length] ?? '#f5f5f7'

    requests.push(
      $fetch(`${config.public.apiBase}/shift-types/${item.id}`, {
        method: 'PATCH',
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
        body: {
          name: item.name,
          color: fallbackColor,
          start_time: item.start_time,
          end_time: item.end_time,
          min_nurses: item.min_nurses,
        },
      })
        .then(() => {
          item.color = fallbackColor
        })
        .catch((err) => {
          console.warn(`Could not persist color for shift type ${item.id}:`, err)
        })
    )
  })

  if (requests.length === 0) return
  await Promise.all(requests)
}

const fetchShiftTypes = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await $fetch<{ data: ShiftType[] }>(`${config.public.apiBase}/shift-types`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    shiftTypes.value = Array.isArray(response.data) ? response.data : []
    await persistSchedulePaletteColors(shiftTypes.value)
  } catch (e) {
    console.error('Error loading shift types:', e)
    error.value = texts.value.fetchError
  } finally {
    loading.value = false
  }
}

const filteredShiftTypes = computed(() => {
  const query = normalizeShiftNameForMatch(searchQuery.value)
  const queryCompact = compactMatchText(searchQuery.value)
  const byGroup = shiftTypes.value.filter((item) => {
    if (selectedShiftGroup.value === 'all') return true
    if (selectedShiftGroup.value === 'work') return !isNonWorkingShift(item.name)
    return isNonWorkingShift(item.name)
  })

  if (!query) return byGroup

  return byGroup.filter((item) => {
    const rawName = normalizeShiftNameForMatch(item.name)
    const rawNameCompact = compactMatchText(item.name)
    const labels = resolveShiftLabel(item.name)
    const translatedPt = normalizeShiftNameForMatch(labels.pt)
    const translatedEn = normalizeShiftNameForMatch(labels.en)
    const translatedPtCompact = compactMatchText(labels.pt)
    const translatedEnCompact = compactMatchText(labels.en)

    return rawName.includes(query)
      || translatedPt.includes(query)
      || translatedEn.includes(query)
      || rawNameCompact.includes(queryCompact)
      || translatedPtCompact.includes(queryCompact)
      || translatedEnCompact.includes(queryCompact)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredShiftTypes.value.length / itemsPerPage)))

const paginatedShiftTypes = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredShiftTypes.value.slice(start, end)
})

const setPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

watch(searchQuery, () => {
  currentPage.value = 1
})

watch(selectedShiftGroup, () => {
  currentPage.value = 1
})

const handleWindowClick = (e: MouseEvent) => {
  const target = e.target as HTMLElement
  if (!target.closest('.custom-select-wrapper')) {
    isShiftGroupOpen.value = false
  }
}

onMounted(async () => {
  if (process.client) {
    window.addEventListener('click', handleWindowClick)
  }

  if (!currentUser.value) {
    await fetchMe().catch(() => null)
  }

  await fetchShiftTypes()
})

onBeforeUnmount(() => {
  if (process.client) {
    window.removeEventListener('click', handleWindowClick)
  }
})
</script>

<template>
  <main class="dashboard-layout hr-page shift-types-page">
    <AppNavbar />

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
          <span>{{ (texts as any)[statusMessage.key] }}</span>
        </div>
      </div>
    </transition>

    <section class="hr-content">
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

      <div class="hr-toolbar">
        <div class="filters-group">
          <div class="hr-search-wrapper">
            <div class="search-input-container">
              <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input
                v-model="searchQuery"
                type="text"
                :placeholder="texts.search"
                class="search-input"
              />
            </div>
          </div>

          <div class="custom-select-wrapper">
            <div class="custom-select-trigger" @click.stop="isShiftGroupOpen = !isShiftGroupOpen">
              <span>{{ shiftGroupLabel }}</span>
              <svg :class="['chevron-icon', { rotate: isShiftGroupOpen }]" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>

            <transition name="fade-slide">
              <div v-if="isShiftGroupOpen" class="custom-options">
                <div class="option-item" @click="selectedShiftGroup = 'all'; isShiftGroupOpen = false">
                  {{ texts.allShiftGroups }}
                </div>
                <div class="option-item" @click="selectedShiftGroup = 'work'; isShiftGroupOpen = false">
                  {{ texts.workShiftGroup }}
                </div>
                <div class="option-item" @click="selectedShiftGroup = 'non_work'; isShiftGroupOpen = false">
                  {{ texts.nonWorkShiftGroup }}
                </div>
              </div>
            </transition>
          </div>
        </div>

        <NuxtLink to="/shift-types-create" class="create-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          {{ texts.create }}
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

        <div v-else-if="shiftTypes.length === 0" class="hr-state-msg">
          <p>{{ texts.empty }}</p>
        </div>

        <div v-else class="table-responsive">
          <table class="hr-table">
            <thead>
              <tr>
                <th class="col-left">{{ texts.tableHeader.name }}</th>
                <th class="col-center st-color-col">{{ texts.tableHeader.color }}</th>
                <th class="col-center">{{ texts.tableHeader.time }}</th>
                <th class="col-center">{{ texts.tableHeader.minNurses }}</th>
                <th class="col-right">{{ texts.tableHeader.actions }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedShiftTypes" :key="item.id">
                <td class="col-left">
                  <div class="user-cell">
                    <div class="user-avatar" :style="{ backgroundColor: getShiftTypeBackgroundColor(item.id) }">
                      {{ getShiftInitial(item.name) }}
                    </div>
                    <span class="user-name-text">{{ getShiftDisplayName(item.name) }}</span>
                  </div>
                </td>

                <td class="col-center st-color-col">
                  <span class="st-color-badge">
                    <span class="st-color-swatch" :style="{ backgroundColor: normalizeHex(item.color) }"></span>
                    <span>{{ item.color || texts.notDefined }}</span>
                  </span>
                </td>

                <td class="col-center">
                  <span class="status-indicator st-time-badge" :style="{ backgroundColor: getShiftTypeBackgroundColor(item.id) }">
                    {{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}
                  </span>
                </td>

                <td class="col-center">
                  <span class="role-badge st-min-badge" :style="{ backgroundColor: getShiftTypeBackgroundColor(item.id) }">
                    {{ item.min_nurses }}
                  </span>
                </td>

                <td class="actions-cell col-right">
                  <button
                    class="action-btn edit"
                    type="button"
                    :title="texts.actions.edit"
                    @click="router.push(`/shift-types-edit?id=${item.id}`)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </button>

                  <button
                    class="action-btn delete"
                    type="button"
                    :title="texts.actions.remove"
                    @click="confirmDelete(item.id)"
                  >
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

      <div v-if="filteredShiftTypes.length > 0 && totalPages > 1" class="hr-pagination">
        <button class="pagination-btn" :disabled="currentPage === 1" @click="setPage(currentPage - 1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </button>

        <div class="pagination-numbers">
          <button
            v-for="p in totalPages"
            :key="p"
            :class="['page-num', { active: p === currentPage }]"
            @click="setPage(p)"
          >
            {{ p }}
          </button>
        </div>

        <button class="pagination-btn" :disabled="currentPage === totalPages" @click="setPage(currentPage + 1)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </button>
      </div>

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
            <p>{{ currentLocale === 'pt' ? 'Tem a certeza que deseja remover este tipo de turno? Esta ação não pode ser revertida.' : 'Are you sure you want to remove this shift type ? This action cannot be undone.' }}</p>

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
    </section>
  </main>
</template>

<style scoped>
.shift-types-page .filters-group {
  width: 100%;
}

.shift-types-page .hr-search-wrapper {
  margin: 0;
  max-width: 420px;
}

.st-color-col {
  min-width: 210px;
}

.st-color-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  color: var(--text);
}

.st-color-swatch {
  width: 18px;
  height: 18px;
  border-radius: 6px;
  border: 1px solid rgba(67, 44, 104, 0.2);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.65);
}

.shift-types-page .user-avatar {
  color: white;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
  box-shadow: 0 6px 15px rgba(102, 67, 155, 0.16);
}

.st-time-badge,
.st-min-badge {
  color: var(--text);
  border: 1px solid rgba(102, 67, 155, 0.14);
}

@media (max-width: 992px) {
  .shift-types-page .create-btn {
    align-self: stretch;
    width: 100%;
  }
}
</style>
