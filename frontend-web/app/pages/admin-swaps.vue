<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
  ssr: false,
})

const { token, user: currentUser, fetchMe } = useAuth()
const { swapRequests, loadingSwaps, errorSwaps, fetchSwaps } = useSwap()
const config = useRuntimeConfig()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const selectedUserId = ref<number | null>(null)
const selectedMonth = ref<string>('')
const searchQuery = ref<string>('')
const isUserDropdownOpen = ref(false)
const isMonthDropdownOpen = ref(false)
const isGeneratingPdf = ref(false)
const pageSize = 10
const currentPage = ref(1)

type NurseUser = { id: number; name: string; role: string; active: boolean }
const nurses = ref<NurseUser[]>([])

const texts = computed(() => ({
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  title: currentLocale.value === 'pt' ? 'Histórico de Trocas' : 'Swap History',
  subtitle: currentLocale.value === 'pt' ? 'Pedidos de troca aceites entre enfermeiros' : 'Accepted shift swap requests',
  loading: currentLocale.value === 'pt' ? 'A carregar pedidos...' : 'Loading requests...',
  noRequests: currentLocale.value === 'pt' ? 'Sem pedidos para apresentar.' : 'No requests to display.',
  exportPdf: currentLocale.value === 'pt' ? 'Exportar PDF' : 'Export PDF',
  generatingPdf: currentLocale.value === 'pt' ? 'A gerar...' : 'Generating...',
  searchPlaceholder: currentLocale.value === 'pt' ? 'Pesquisar enfermeiro...' : 'Search nurse...',
  allNurses: currentLocale.value === 'pt' ? 'Todos os enfermeiros' : 'All nurses',
  allMonths: currentLocale.value === 'pt' ? 'Todos os meses' : 'All months',
  filters: {
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    month: currentLocale.value === 'pt' ? 'Mês' : 'Month',
  },
  card: {
    offered: currentLocale.value === 'pt' ? 'Turno oferecido' : 'Offered shift',
    requested: currentLocale.value === 'pt' ? 'Turno pretendido' : 'Requested shift',
    by: currentLocale.value === 'pt' ? 'Por' : 'By',
    with: currentLocale.value === 'pt' ? 'Entre' : 'Between',
    and: currentLocale.value === 'pt' ? 'e' : 'and',
    notes: currentLocale.value === 'pt' ? 'Notas' : 'Notes',
    accepted: currentLocale.value === 'pt' ? 'Aceite' : 'Accepted',
  },
  pagination: {
    previous: currentLocale.value === 'pt' ? 'Anterior' : 'Previous',
    next: currentLocale.value === 'pt' ? 'Seguinte' : 'Next',
  },
  pdf: {
    title: currentLocale.value === 'pt' ? 'Registo de Trocas de Turno' : 'Shift Swap Register',
    printed: currentLocale.value === 'pt' ? 'Impresso em' : 'Printed on',
    filterNurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    filterMonth: currentLocale.value === 'pt' ? 'Mês' : 'Month',
    all: currentLocale.value === 'pt' ? 'Todos' : 'All',
    colDate: currentLocale.value === 'pt' ? 'Data' : 'Date',
    colNurseA: currentLocale.value === 'pt' ? 'Enfermeiro A' : 'Nurse A',
    colShiftA: currentLocale.value === 'pt' ? 'Turno cedido' : 'Shift given',
    colNurseB: currentLocale.value === 'pt' ? 'Enfermeiro B' : 'Nurse B',
    colShiftB: currentLocale.value === 'pt' ? 'Turno recebido' : 'Shift received',
    signature: currentLocale.value === 'pt' ? 'Assinatura' : 'Signature',
  },
}))

const fetchNurses = async () => {
  try {
    const response = await $fetch<{ data: NurseUser[] }>(`${config.public.apiBase}/users`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    nurses.value = response.data.filter((u) => {
      const role = u.role?.toLowerCase().trim().replace(' ', '_')
      return (role === 'nurse' || role === 'head_nurse') && u.active
    })
  } catch {
    // silent
  }
}

const filteredNurses = computed(() => {
  if (!searchQuery.value.trim()) return nurses.value
  const q = searchQuery.value.toLowerCase()
  return nurses.value.filter((n) => n.name.toLowerCase().includes(q))
})

const selectedNurseName = computed(() => {
  if (!selectedUserId.value) return texts.value.allNurses
  return nurses.value.find((n) => n.id === selectedUserId.value)?.name ?? texts.value.allNurses
})

// Generate list of last 24 months + current month for the dropdown
const monthOptions = computed(() => {
  const options: { value: string; label: string }[] = []
  const now = new Date()
  for (let i = 0; i < 24; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    const label = new Intl.DateTimeFormat(currentLocale.value === 'pt' ? 'pt-PT' : 'en-US', {
      month: 'long',
      year: 'numeric',
    }).format(d)
    options.push({ value, label: label.charAt(0).toUpperCase() + label.slice(1) })
  }
  return options
})

const selectedMonthLabel = computed(() => {
  if (!selectedMonth.value) return texts.value.allMonths
  return monthOptions.value.find((m) => m.value === selectedMonth.value)?.label ?? texts.value.allMonths
})

const formatDate = (value: string) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const formatted = new Intl.DateTimeFormat(currentLocale.value === 'pt' ? 'pt-PT' : 'en-US', {
    weekday: 'short',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date)
  const normalized = formatted.replace('.', '')
  return normalized.charAt(0).toUpperCase() + normalized.slice(1)
}

const getShiftName = (shiftType: any) => {
  if (!shiftType?.name) return '-'
  const name = (shiftType.name || '').toLowerCase()
  const pt: Record<string, string> = {
    morning: 'Manhã', afternoon: 'Tarde', night: 'Noite',
    dayoff: 'Folga', 'day off': 'Folga', holidays: 'Férias',
    'sick leave': 'Baixa Médica', 'parental leave': 'Licença Parental',
  }
  const en: Record<string, string> = {
    morning: 'Morning', afternoon: 'Afternoon', night: 'Night',
    dayoff: 'Day Off', 'day off': 'Day Off', holidays: 'Holidays',
    'sick leave': 'Sick Leave', 'parental leave': 'Parental Leave',
  }
  return currentLocale.value === 'pt' ? (pt[name] || shiftType.name) : (en[name] || shiftType.name)
}

const getPrimaryShift = (shifts: any[]) => (Array.isArray(shifts) && shifts.length > 0 ? shifts[0] : null)
const isMultiOfferedSwap = (swap: any) => (swap.offered_shifts?.length ?? 0) > 1
const isMultiRequestedSwap = (swap: any) => (swap.requested_shifts?.length ?? 0) > 1

const getRequesterName = (swap: any) =>
  swap.participants?.find((p: any) => p.role === 'requester')?.name || swap.creator?.name || '-'
const getTargetName = (swap: any) =>
  swap.participants?.find((p: any) => p.role === 'target')?.name || '-'

const totalItems = computed(() => swapRequests.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / pageSize)))
const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return swapRequests.value.slice(start, start + pageSize)
})

const goToPreviousPage = () => { if (currentPage.value > 1) currentPage.value-- }
const goToNextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
const setPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) currentPage.value = page
}

const loadSwaps = async () => {
  currentPage.value = 1
  await fetchSwaps(
    undefined,
    'accepted',
    selectedUserId.value ?? undefined,
    selectedMonth.value || undefined,
  )
}

const closeAllDropdowns = () => {
  isUserDropdownOpen.value = false
  isMonthDropdownOpen.value = false
}

if (process.client) {
  window.addEventListener('click', (event: MouseEvent) => {
    const target = event.target as HTMLElement
    if (!target.closest('.sw-select-wrapper') && !target.closest('.sw-search-input')) {
      closeAllDropdowns()
    }
  })
}

// PDF generation via html2canvas + jsPDF (same pattern as schedule-view.vue)
const pdfPageRef = ref<HTMLElement | null>(null)

const generatePdf = async () => {
  if (isGeneratingPdf.value || !swapRequests.value.length) return
  isGeneratingPdf.value = true

  try {
    const [{ default: html2canvas }, { default: jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])

    const el = pdfPageRef.value
    if (!el) return

    el.style.display = 'block'
    await nextTick()

    const canvas = await html2canvas(el, { scale: 2, useCORS: true })
    el.style.display = 'none'

    const imgData = canvas.toDataURL('image/jpeg', 0.92)
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const pageW = pdf.internal.pageSize.getWidth()
    const pageH = pdf.internal.pageSize.getHeight()
    const ratio = canvas.width / canvas.height
    const imgH = pageW / ratio

    if (imgH <= pageH) {
      pdf.addImage(imgData, 'JPEG', 0, 0, pageW, imgH)
    } else {
      // Multi-page: slice canvas vertically
      const sliceH = Math.floor((canvas.height * pageH) / imgH)
      let yOffset = 0
      let first = true
      while (yOffset < canvas.height) {
        const sliceCanvas = document.createElement('canvas')
        sliceCanvas.width = canvas.width
        sliceCanvas.height = Math.min(sliceH, canvas.height - yOffset)
        const ctx = sliceCanvas.getContext('2d')!
        ctx.drawImage(canvas, 0, -yOffset)
        const slice = sliceCanvas.toDataURL('image/jpeg', 0.92)
        if (!first) pdf.addPage()
        pdf.addImage(slice, 'JPEG', 0, 0, pageW, pageH)
        yOffset += sliceH
        first = false
      }
    }

    const nurseSlug = selectedNurseName.value !== texts.value.allNurses
      ? `_${selectedNurseName.value.replace(/\s+/g, '_')}`
      : ''
    const monthSlug = selectedMonth.value ? `_${selectedMonth.value}` : ''
    pdf.save(`trocas${nurseSlug}${monthSlug}.pdf`)
  } finally {
    isGeneratingPdf.value = false
  }
}

const pdfRows = computed(() =>
  swapRequests.value.map((swap) => ({
    date: formatDate(swap.created_at),
    nurseA: getRequesterName(swap),
    shiftA: getShiftName(getPrimaryShift(swap.offered_shifts)?.shift_type),
    nurseB: getTargetName(swap),
    shiftB: getShiftName(getPrimaryShift(swap.requested_shifts)?.shift_type),
  })),
)

watch(totalPages, (newTotal) => {
  if (currentPage.value > newTotal) currentPage.value = newTotal
})

onMounted(async () => {
  if (!currentUser.value) await fetchMe().catch(() => null)
  const role = currentUser.value?.role?.toLowerCase().trim() || ''
  if (role !== 'admin') {
    await navigateTo('/dashboard')
    return
  }
  await Promise.all([fetchNurses(), loadSwaps()])
})
</script>

<template>
  <main class="dashboard-layout sw-page">
    <AppNavbar />

    <section class="dashboard-content sw-content">
      <div class="sw-top-bar">
        <div class="sw-title-group">
          <NuxtLink to="/dashboard" class="sw-back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            {{ texts.back }}
          </NuxtLink>
          <h1>{{ texts.title }}</h1>
          <p class="sw-subtitle">{{ texts.subtitle }}</p>
        </div>

        <button
          class="sw-create-btn"
          :disabled="isGeneratingPdf || !swapRequests.length"
          @click="generatePdf"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="12" y1="18" x2="12" y2="12"></line>
            <line x1="9" y1="15" x2="15" y2="15"></line>
          </svg>
          {{ isGeneratingPdf ? texts.generatingPdf : texts.exportPdf }}
        </button>
      </div>

      <div class="sw-toolbar">
        <div class="sw-filters-group">
          <!-- Search + nurse dropdown -->
          <div class="sw-select-wrapper">
            <div class="sw-select-trigger sw-search-trigger" @click.stop="isUserDropdownOpen = !isUserDropdownOpen; isMonthDropdownOpen = false">
              <span>{{ selectedNurseName }}</span>
              <svg :class="['sw-chevron-icon', { rotate: isUserDropdownOpen }]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>

            <transition name="fade-slide">
              <div v-if="isUserDropdownOpen" class="sw-custom-options sw-nurse-options">
                <div class="sw-search-input-wrapper">
                  <input
                    v-model="searchQuery"
                    class="sw-search-input"
                    type="text"
                    :placeholder="texts.searchPlaceholder"
                    @click.stop
                  />
                </div>
                <div
                  class="sw-option-item"
                  @click="selectedUserId = null; isUserDropdownOpen = false; searchQuery = ''; loadSwaps()"
                >
                  {{ texts.allNurses }}
                </div>
                <div
                  v-for="nurse in filteredNurses"
                  :key="nurse.id"
                  class="sw-option-item"
                  @click="selectedUserId = nurse.id; isUserDropdownOpen = false; searchQuery = ''; loadSwaps()"
                >
                  {{ nurse.name }}
                </div>
              </div>
            </transition>
          </div>

          <!-- Month dropdown -->
          <div class="sw-select-wrapper">
            <div class="sw-select-trigger" @click.stop="isMonthDropdownOpen = !isMonthDropdownOpen; isUserDropdownOpen = false">
              <span>{{ selectedMonthLabel }}</span>
              <svg :class="['sw-chevron-icon', { rotate: isMonthDropdownOpen }]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>

            <transition name="fade-slide">
              <div v-if="isMonthDropdownOpen" class="sw-custom-options sw-month-options">
                <div
                  class="sw-option-item"
                  @click="selectedMonth = ''; isMonthDropdownOpen = false; loadSwaps()"
                >
                  {{ texts.allMonths }}
                </div>
                <div
                  v-for="opt in monthOptions"
                  :key="opt.value"
                  class="sw-option-item"
                  @click="selectedMonth = opt.value; isMonthDropdownOpen = false; loadSwaps()"
                >
                  {{ opt.label }}
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>

      <div class="sw-card">
        <div v-if="loadingSwaps" class="sw-state-msg">
          <div class="sw-spinner"></div>
          <p>{{ texts.loading }}</p>
        </div>

        <div v-else-if="errorSwaps" class="sw-state-msg error">
          <p>{{ errorSwaps }}</p>
        </div>

        <div v-else-if="!swapRequests?.length" class="sw-state-msg">
          <p>{{ texts.noRequests }}</p>
        </div>

        <div v-else class="sw-list-shell">
          <div class="sw-list">
            <article
              v-for="swapItem in paginatedItems"
              :key="swapItem.id"
              class="sw-item sw-item--resolved"
            >
              <div class="sw-item-header">
                <div class="sw-item-top-row">
                  <span class="sw-counterparty">
                    {{ texts.card.with }} {{ getRequesterName(swapItem) }} {{ texts.card.and }} {{ getTargetName(swapItem) }}
                  </span>
                  <span class="sw-status-badge status-accepted">{{ texts.card.accepted }}</span>
                </div>
                <p class="sw-item-date">{{ formatDate(swapItem.created_at) }}</p>
              </div>

              <div class="sw-swap-header">
                <!-- Offered shifts -->
                <div
                  v-if="!isMultiOfferedSwap(swapItem)"
                  class="sw-swap-header__panel"
                  :style="{ borderInlineStart: `3px solid ${getPrimaryShift(swapItem.offered_shifts)?.shift_type?.color || 'transparent'}` }"
                >
                  <span class="sw-swap-header__badge">{{ texts.card.offered }}</span>
                  <p class="sw-swap-header__date">{{ formatDate(getPrimaryShift(swapItem.offered_shifts)?.date || '-') }}</p>
                  <p class="sw-swap-header__type">{{ getShiftName(getPrimaryShift(swapItem.offered_shifts)?.shift_type) }}</p>
                  <p class="sw-swap-header__owner">{{ texts.card.by }} {{ getPrimaryShift(swapItem.offered_shifts)?.owner?.name || '-' }}</p>
                </div>

                <div v-else class="sw-swap-header__panel sw-swap-header__panel--multi">
                  <div
                    v-for="(offeredShift, index) in swapItem.offered_shifts"
                    :key="`offered-${offeredShift.id ?? index}`"
                    class="sw-swap-header__panel"
                    :style="{ borderInlineStart: `3px solid ${offeredShift.shift_type?.color || 'transparent'}` }"
                  >
                    <span class="sw-swap-header__badge">
                      {{ index === 0 ? texts.card.offered : (currentLocale === 'pt' ? 'Folga oferecida' : 'Offered day off') }}
                    </span>
                    <p class="sw-swap-header__date">{{ formatDate(offeredShift.date || '-') }}</p>
                    <p class="sw-swap-header__type">{{ getShiftName(offeredShift.shift_type) }}</p>
                    <p class="sw-swap-header__owner">{{ texts.card.by }} {{ offeredShift.owner?.name || '-' }}</p>
                  </div>
                </div>

                <div class="sw-swap-header__icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                    <path d="M20 7h-4V3"></path>
                    <path d="M20 7a8 8 0 0 0-14-3"></path>
                    <path d="M4 17h4v4"></path>
                    <path d="M4 17a8 8 0 0 0 14 3"></path>
                  </svg>
                </div>

                <!-- Requested shifts -->
                <div
                  v-if="!isMultiRequestedSwap(swapItem)"
                  class="sw-swap-header__panel"
                  :style="{ borderInlineStart: `3px solid ${getPrimaryShift(swapItem.requested_shifts)?.shift_type?.color || 'transparent'}` }"
                >
                  <span class="sw-swap-header__badge">{{ texts.card.requested }}</span>
                  <p class="sw-swap-header__date">{{ formatDate(getPrimaryShift(swapItem.requested_shifts)?.date || '-') }}</p>
                  <p class="sw-swap-header__type">{{ getShiftName(getPrimaryShift(swapItem.requested_shifts)?.shift_type) }}</p>
                  <p class="sw-swap-header__owner">{{ texts.card.by }} {{ getPrimaryShift(swapItem.requested_shifts)?.owner?.name || '-' }}</p>
                </div>

                <div v-else class="sw-swap-header__panel sw-swap-header__panel--multi">
                  <div
                    v-for="(requestedShift, index) in swapItem.requested_shifts"
                    :key="`requested-${requestedShift.id ?? index}`"
                    class="sw-swap-header__panel"
                    :style="{ borderInlineStart: `3px solid ${requestedShift.shift_type?.color || 'transparent'}` }"
                  >
                    <span class="sw-swap-header__badge">
                      {{ index === 0 ? (currentLocale === 'pt' ? 'Folga pretendida' : 'Requested day off') : (currentLocale === 'pt' ? 'Turno a receber' : 'Shift to receive') }}
                    </span>
                    <p class="sw-swap-header__date">{{ formatDate(requestedShift.date || '-') }}</p>
                    <p class="sw-swap-header__type">{{ getShiftName(requestedShift.shift_type) }}</p>
                    <p class="sw-swap-header__owner">{{ texts.card.by }} {{ requestedShift.owner?.name || '-' }}</p>
                  </div>
                </div>
              </div>

              <p v-if="swapItem.notes" class="sw-notes"><strong>{{ texts.card.notes }}:</strong> {{ swapItem.notes }}</p>
            </article>
          </div>
        </div>

        <div v-if="!loadingSwaps && !errorSwaps" class="sw-pagination">
          <button class="pagination-btn" :disabled="currentPage === 1" :title="texts.pagination.previous" @click="goToPreviousPage">
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

          <button class="pagination-btn" :disabled="currentPage === totalPages || totalItems === 0" :title="texts.pagination.next" @click="goToNextPage">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </div>
      </div>
    </section>

    <!-- Hidden element rendered to canvas for PDF generation -->
    <div ref="pdfPageRef" class="pdf-page" style="display:none; width:900px; padding:40px; background:#fff; font-family:Arial,sans-serif; color:#222;">
      <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; margin:0 0 6px; color:#432c68;">{{ texts.pdf.title }}</h1>
        <p style="font-size:12px; color:#666; margin:0;">
          {{ texts.pdf.printed }}: {{ new Date().toLocaleDateString(currentLocale === 'pt' ? 'pt-PT' : 'en-US') }}
          &nbsp;|&nbsp;
          {{ texts.pdf.filterNurse }}: {{ selectedNurseName }}
          &nbsp;|&nbsp;
          {{ texts.pdf.filterMonth }}: {{ selectedMonthLabel }}
        </p>
      </div>

      <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
          <tr style="background:#f3eeff; color:#432c68;">
            <th style="padding:8px 10px; text-align:left; border-bottom:2px solid #d4bfff;">{{ texts.pdf.colDate }}</th>
            <th style="padding:8px 10px; text-align:left; border-bottom:2px solid #d4bfff;">{{ texts.pdf.colNurseA }}</th>
            <th style="padding:8px 10px; text-align:left; border-bottom:2px solid #d4bfff;">{{ texts.pdf.colShiftA }}</th>
            <th style="padding:8px 10px; text-align:left; border-bottom:2px solid #d4bfff;">{{ texts.pdf.colNurseB }}</th>
            <th style="padding:8px 10px; text-align:left; border-bottom:2px solid #d4bfff;">{{ texts.pdf.colShiftB }}</th>
            <th style="padding:8px 10px; text-align:left; border-bottom:2px solid #d4bfff;">{{ texts.pdf.signature }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, i) in pdfRows"
            :key="i"
            :style="{ background: i % 2 === 0 ? '#fff' : '#faf8ff' }"
          >
            <td style="padding:8px 10px; border-bottom:1px solid #e8e0f7;">{{ row.date }}</td>
            <td style="padding:8px 10px; border-bottom:1px solid #e8e0f7;">{{ row.nurseA }}</td>
            <td style="padding:8px 10px; border-bottom:1px solid #e8e0f7;">{{ row.shiftA }}</td>
            <td style="padding:8px 10px; border-bottom:1px solid #e8e0f7;">{{ row.nurseB }}</td>
            <td style="padding:8px 10px; border-bottom:1px solid #e8e0f7;">{{ row.shiftB }}</td>
            <td style="padding:8px 10px; border-bottom:1px solid #e8e0f7; min-width:120px;">___________</td>
          </tr>
        </tbody>
      </table>
    </div>
  </main>
</template>

<style scoped>
/* Dropdowns with search — extend existing sw-* classes from swaps.vue */
.sw-nurse-options {
  max-height: 300px;
  overflow-y: auto;
}

.sw-month-options {
  max-height: 260px;
  overflow-y: auto;
}

.sw-search-input-wrapper {
  padding: 8px 10px 4px;
  position: sticky;
  top: 0;
  background: var(--surface, #fff);
  z-index: 1;
}

.sw-search-input {
  width: 100%;
  padding: 6px 10px;
  border: 1px solid rgba(172, 138, 241, 0.4);
  border-radius: 8px;
  font-size: 0.9rem;
  outline: none;
  box-sizing: border-box;
  background: var(--bg, #fcf8ff);
  color: var(--text, #432c68);
}

.sw-search-input:focus {
  border-color: var(--primary, #ac8af1);
}
</style>
