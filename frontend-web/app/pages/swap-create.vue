<script setup lang="ts">
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

const { user } = useAuth()
const { fetchShifts, createSwap, errorSwaps } = useSwap()
const route = useRoute()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const selectedOfferedShift = ref<ShiftOption[]>([])
const selectedRequestedShift = ref<ShiftOption | null>(null)
const myShifts = ref<ShiftOption[]>([])
const availableShifts = ref<ShiftOption[]>([])
const loadingMyShifts = ref(false)
const loadingAvailable = ref(false)
const submitting = ref(false)
const submitError = ref<string | null>(null)
const submitSuccess = ref(false)
const notes = ref('')
const filterDate = ref('')
const filterNurse = ref('')
const activeShiftTypeFilters = ref<number[]>([])

const texts = computed(() => ({
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  title: currentLocale.value === 'pt' ? 'Novo Pedido de Troca' : 'New Swap Request',
  leftTitle: currentLocale.value === 'pt' ? 'O meu turno' : 'My shift',
  rightTitle: currentLocale.value === 'pt' ? 'Turno pretendido' : 'Requested shift',
  leftLoading: currentLocale.value === 'pt' ? 'A carregar...' : 'Loading...',
  rightLoading: currentLocale.value === 'pt' ? 'A carregar...' : 'Loading...',
  leftEmpty: currentLocale.value === 'pt' ? 'Sem turnos futuros disponíveis.' : 'No future shifts available.',
  rightEmpty: currentLocale.value === 'pt' ? 'Sem turnos disponíveis para pedido.' : 'No shifts available to request.',
  noResults: currentLocale.value === 'pt' ? 'Sem resultados para os filtros atuais.' : 'No results for current filters.',
  filters: {
    date: currentLocale.value === 'pt' ? 'Data' : 'Date',
    shiftType: currentLocale.value === 'pt' ? 'Tipo de turno' : 'Shift type',
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    allTypes: currentLocale.value === 'pt' ? 'Todos' : 'All types',
    nursePlaceholder: currentLocale.value === 'pt' ? 'Pesquisar por nome...' : 'Search by nurse name...',
  },
  notes: currentLocale.value === 'pt' ? 'Notas' : 'Notes',
  notesPlaceholder: currentLocale.value === 'pt' ? 'Opcional' : 'Optional',
  submit: currentLocale.value === 'pt' ? 'Criar pedido' : 'Create request',
  submitSuccess: currentLocale.value === 'pt' ? 'Pedido criado com sucesso!' : 'Request created successfully!',
  submitErrorFallback: currentLocale.value === 'pt' ? 'Não foi possível criar o pedido.' : 'Failed to create request.',
  by: currentLocale.value === 'pt' ? 'Por' : 'By',
  invalidForm: currentLocale.value === 'pt' ? 'Seleciona os dois turnos para continuar.' : 'Please select both shifts to continue.',
}))

const toColorClass = (color: string) => {
  const normalized = (color || '').toLowerCase().replace(/[^a-z0-9]/g, '')
  return normalized ? `swc-shift-dot--${normalized}` : 'swc-shift-dot--fallback'
}

const allShiftColors = computed(() => {
  const unique = new Set<string>()

  for (const shift of myShifts.value) {
    if (shift.shift_type.color) unique.add(shift.shift_type.color)
  }

  for (const shift of availableShifts.value) {
    if (shift.shift_type.color) unique.add(shift.shift_type.color)
  }

  return Array.from(unique)
})

// Build dynamic dot classes from backend shift colors while keeping template free of inline styles.
useHead(() => ({
  style: [
    {
      children: allShiftColors.value
        .filter((color) => /^[#(),.%\sa-zA-Z0-9-]+$/.test(color))
        .map((color) => `.${toColorClass(color)} { background: ${color}; }`)
        .join('\n'),
    },
  ],
}))

const formatDate = (value: string) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('pt-PT', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
}

const formatTime = (value: string) => {
  if (!value) return '-'
  return value.slice(0, 5)
}

const normalizeDate = (value: string) => {
  return String(value).slice(0, 10)
}

const availableShiftTypes = computed(() => {
  const map = new Map<number, { id: number; name: string; color: string }>()

  for (const shift of availableShifts.value) {
    const type = shift.shift_type
    if (!type) continue

    if (!map.has(type.id)) {
      map.set(type.id, {
        id: type.id,
        name: type.name,
        color: type.color,
      })
    }
  }

  return Array.from(map.values())
})

const filteredAvailableShifts = computed(() => {
  return availableShifts.value.filter((shift) => {
    // Never show the exact same day + shift-type combination as the offered shift.
    const isSameDayAndType = selectedOfferedShift.value.some((offeredShift) =>
      normalizeDate(shift.date) === normalizeDate(offeredShift.date)
      && shift.shift_type.id === offeredShift.shift_type.id,
    )
    if (isSameDayAndType) return false

    const matchesDate = !filterDate.value || normalizeDate(shift.date) === filterDate.value

    const matchesType = activeShiftTypeFilters.value.length === 0
      || activeShiftTypeFilters.value.includes(shift.shift_type.id)

    const nurseName = shift.users[0]?.name || ''
    const matchesNurse = !filterNurse.value
      || nurseName.toLowerCase().includes(filterNurse.value.trim().toLowerCase())

    return matchesDate && matchesType && matchesNurse
  })
})

const loadMyShifts = async () => {
  loadingMyShifts.value = true
  submitError.value = null

  try {
    const all = await fetchShifts({ mine: true, future: true })
    myShifts.value = all.filter((shift) => shift.shift_type.name !== 'dayOff')
  } catch (error) {
    console.error('Error loading my shifts:', error)
    submitError.value = error instanceof Error ? error.message : 'Failed to load shifts.'
  } finally {
    loadingMyShifts.value = false
  }
}

// Cache the available shifts list and only fetch once (or again if list is empty).
watch(() => selectedOfferedShift.value.length, async (newLength) => {
  selectedRequestedShift.value = null
  notes.value = ''
  submitSuccess.value = false
  filterNurse.value = ''
  activeShiftTypeFilters.value = []
  filterDate.value = ''

  if (newLength === 0) return
  if (availableShifts.value.length > 0) return

  loadingAvailable.value = true
  submitError.value = null

  try {
    const all = await fetchShifts({ exclude_mine: true, future: true })

    availableShifts.value = all.filter((shift) => {
      const isNotDayOff = shift.shift_type.name !== 'dayOff'
      const hasOtherUser = shift.users.some((nurse) => nurse.id !== user.value?.id)
      return isNotDayOff && hasOtherUser
    })
  } catch (error) {
    console.error('Error loading available shifts:', error)
    submitError.value = error instanceof Error ? error.message : 'Failed to load shifts.'
  } finally {
    loadingAvailable.value = false
  }
})

const goBack = async () => {
  await navigateTo('/swaps')
}

const submitCreateSwap = async () => {
  submitError.value = null
  submitSuccess.value = false

  if (selectedOfferedShift.value.length === 0 || !selectedRequestedShift.value) {
    submitError.value = texts.value.invalidForm
    return
  }

  const targetUserId = selectedRequestedShift.value.users[0]?.id
  if (!targetUserId) {
    submitError.value = texts.value.invalidForm
    return
  }

  submitting.value = true

  try {
    await Promise.all(
      selectedOfferedShift.value.map((offeredShift) =>
        createSwap({
          offered_shift_ids: [offeredShift.id],
          requested_shift_ids: [selectedRequestedShift.value!.id],
          target_user_id: targetUserId,
          notes: notes.value.trim() || undefined,
        }),
      ),
    )

    submitSuccess.value = true
    setTimeout(async () => {
      await navigateTo('/swaps')
    }, 1500)
  } catch (error) {
    console.error('Create swap error:', error)
    submitError.value = errorSwaps.value || texts.value.submitErrorFallback
  } finally {
    submitting.value = false
  }
}

const toggleShiftTypeFilter = (typeId: number) => {
  if (activeShiftTypeFilters.value.includes(typeId)) {
    activeShiftTypeFilters.value = activeShiftTypeFilters.value.filter((id) => id !== typeId)
    return
  }

  activeShiftTypeFilters.value.push(typeId)
}

const clearShiftTypeFilters = () => {
  activeShiftTypeFilters.value = []
}

onMounted(async () => {
  await loadMyShifts()

  const shiftIdParam = Array.isArray(route.query.shift_id)
    ? route.query.shift_id[0]
    : route.query.shift_id

  if (!shiftIdParam) return

  const matchedShift = myShifts.value.find((shift) => shift.id === Number(shiftIdParam))
  if (matchedShift) {
    selectedOfferedShift.value = [matchedShift]
  }
})
</script>

<template>
  <main class="dashboard-layout swc-page">
    <AppNavbar />

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

      <div class="swc-panels">
        <section class="swc-panel-left">
          <h2 class="swc-panel-title">{{ texts.leftTitle }}</h2>

          <div v-if="loadingMyShifts" class="swc-loading">{{ texts.leftLoading }}</div>
          <div v-else-if="myShifts.length === 0" class="swc-empty">{{ texts.leftEmpty }}</div>

          <div v-else class="swc-shift-list">
            <button
              v-for="shift in myShifts"
              :key="`my-${shift.id}`"
              :class="['swc-shift-card', { 'swc-shift-card--selected': selectedOfferedShift.some((item) => item.id === shift.id) }]"
              @click="selectedOfferedShift = selectedOfferedShift.some((item) => item.id === shift.id) ? selectedOfferedShift.filter((item) => item.id !== shift.id) : [...selectedOfferedShift, shift]"
            >
              <span :class="['swc-shift-dot', toColorClass(shift.shift_type.color)]"></span>
              <div>
                <p class="swc-shift-date">{{ formatDate(shift.date) }}</p>
                <p class="swc-shift-type">{{ shift.shift_type.name }}</p>
                <p class="swc-shift-time">{{ formatTime(shift.shift_type.start_time) }}-{{ formatTime(shift.shift_type.end_time) }}</p>
              </div>
            </button>
          </div>
        </section>

        <section v-if="selectedOfferedShift.length > 0" class="swc-panel-right">
          <h2 class="swc-panel-title">{{ texts.rightTitle }}</h2>

          <div class="swc-filter-bar">
            <div class="swc-filter-group">
              <label class="swc-filter-label">{{ texts.filters.date }}</label>
              <input v-model="filterDate" class="swc-date-input" type="date" />
            </div>

            <div class="swc-filter-group">
              <label class="swc-filter-label">{{ texts.filters.shiftType }}</label>
              <div class="swc-type-list">
                <button
                  :class="['swc-type-btn', { 'swc-type-btn--active': activeShiftTypeFilters.length === 0 }]"
                  @click="clearShiftTypeFilters"
                >
                  {{ texts.filters.allTypes }}
                </button>

                <button
                  v-for="type in availableShiftTypes"
                  :key="`type-${type.id}`"
                  :class="['swc-type-btn', { 'swc-type-btn--active': activeShiftTypeFilters.includes(type.id) }]"
                  @click="toggleShiftTypeFilter(type.id)"
                >
                  {{ type.name }}
                </button>
              </div>
            </div>

            <div class="swc-filter-group">
              <label class="swc-filter-label">{{ texts.filters.nurse }}</label>
              <input
                v-model="filterNurse"
                class="swc-nurse-input"
                type="text"
                :placeholder="texts.filters.nursePlaceholder"
              />
            </div>
          </div>

          <div v-if="loadingAvailable" class="swc-loading">{{ texts.rightLoading }}</div>
          <div v-else-if="availableShifts.length === 0" class="swc-empty">{{ texts.rightEmpty }}</div>
          <div v-else-if="filteredAvailableShifts.length === 0" class="swc-empty">{{ texts.noResults }}</div>

          <div v-else class="swc-shift-list">
            <button
              v-for="shift in filteredAvailableShifts"
              :key="`available-${shift.id}`"
              :class="['swc-shift-card', { 'swc-shift-card--selected': selectedRequestedShift?.id === shift.id }]"
              @click="selectedRequestedShift = shift"
            >
              <span :class="['swc-shift-dot', toColorClass(shift.shift_type.color)]"></span>
              <div>
                <p class="swc-shift-date">{{ formatDate(shift.date) }}</p>
                <p class="swc-shift-type">{{ shift.shift_type.name }}</p>
                <p class="swc-shift-time">{{ formatTime(shift.shift_type.start_time) }}-{{ formatTime(shift.shift_type.end_time) }}</p>
                <p class="swc-shift-nurse">{{ shift.users[0]?.name || '-' }}</p>
              </div>
            </button>
          </div>

          <div v-if="selectedRequestedShift" class="swc-notes-area">
            <label class="swc-filter-label">{{ texts.notes }}</label>
            <textarea v-model="notes" rows="4" class="swc-textarea" :placeholder="texts.notesPlaceholder"></textarea>

            <div class="swc-submit-area">
              <button class="swc-submit-btn" :disabled="submitting" @click="submitCreateSwap">
                {{ texts.submit }}
              </button>

              <p v-if="submitError" class="swc-submit-error">{{ submitError }}</p>
              <p v-if="submitSuccess" class="swc-submit-success">{{ texts.submitSuccess }}</p>
            </div>
          </div>
        </section>
      </div>
    </section>
  </main>
</template>
