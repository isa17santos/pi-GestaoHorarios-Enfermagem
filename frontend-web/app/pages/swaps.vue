<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
  ssr: false,
})

type SwapStatusFilter = '' | 'pending' | 'accepted' | 'rejected' | 'cancelled'
type SwapDirectionFilter = '' | 'sent' | 'received'

type ActionType = 'accept' | 'reject' | 'cancel'

const { user } = useAuth()
const { swapRequests, loadingSwaps, errorSwaps, fetchSwaps, acceptSwap, rejectSwap, cancelSwap } = useSwap()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const selectedDirection = ref<SwapDirectionFilter>('')
const selectedStatus = ref<SwapStatusFilter>('')

const isDirectionOpen = ref(false)
const isStatusOpen = ref(false)

const showActionModal = ref(false)

const actionLoading = ref(false)
const actionSwapId = ref<number | null>(null)
const actionType = ref<ActionType | null>(null)

const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const texts = computed(() => ({
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  title: currentLocale.value === 'pt' ? 'Pedidos de Troca de Turno' : 'Shift Swap Requests',
  subtitle: currentLocale.value === 'pt' ? 'Gerir os meus pedidos enviados e recebidos' : 'Manage my sent and received requests',
  newRequest: currentLocale.value === 'pt' ? 'Novo pedido' : 'New request',
  loading: currentLocale.value === 'pt' ? 'A carregar pedidos...' : 'Loading requests...',
  noRequests: currentLocale.value === 'pt' ? 'Sem pedidos para apresentar.' : 'No requests to display.',
  sentSection: currentLocale.value === 'pt' ? 'Enviados' : 'Sent',
  receivedSection: currentLocale.value === 'pt' ? 'Recebidos' : 'Received',
  filters: {
    allDirections: currentLocale.value === 'pt' ? 'Todas as direções' : 'All directions',
    sent: currentLocale.value === 'pt' ? 'Enviados' : 'Sent',
    received: currentLocale.value === 'pt' ? 'Recebidos' : 'Received',
    allStatus: currentLocale.value === 'pt' ? 'Todos' : 'All',
    pending: currentLocale.value === 'pt' ? 'Pendente' : 'Pending',
    accepted: currentLocale.value === 'pt' ? 'Aceite' : 'Accepted',
    rejected: currentLocale.value === 'pt' ? 'Rejeitado' : 'Rejected',
    cancelled: currentLocale.value === 'pt' ? 'Cancelado' : 'Cancelled',
  },
  card: {
    offered: currentLocale.value === 'pt' ? 'Turno oferecido' : 'Offered shift',
    requested: currentLocale.value === 'pt' ? 'Turno pretendido' : 'Requested shift',
    with: currentLocale.value === 'pt' ? 'Com' : 'With',
    by: currentLocale.value === 'pt' ? 'Por' : 'By',
    on: currentLocale.value === 'pt' ? 'em' : 'on',
    notes: currentLocale.value === 'pt' ? 'Notas' : 'Notes',
  },
  actions: {
    accept: currentLocale.value === 'pt' ? 'Aceitar' : 'Accept',
    reject: currentLocale.value === 'pt' ? 'Rejeitar' : 'Reject',
    cancel: currentLocale.value === 'pt' ? 'Cancelar' : 'Cancel',
  },
  confirmModal: {
    title: currentLocale.value === 'pt' ? 'Confirmar Ação' : 'Confirm Action',
    accept: currentLocale.value === 'pt'
      ? 'Tem a certeza que quer aceitar esta troca?'
      : 'Are you sure you want to accept this swap?',
    reject: currentLocale.value === 'pt'
      ? 'Tem a certeza que quer rejeitar esta troca?'
      : 'Are you sure you want to reject this swap?',
    cancel: currentLocale.value === 'pt'
      ? 'Tem a certeza que quer cancelar este pedido?'
      : 'Are you sure you want to cancel this request?',
    close: currentLocale.value === 'pt' ? 'Fechar' : 'Close',
    confirm: currentLocale.value === 'pt' ? 'Confirmar' : 'Confirm',
  },
  toasts: {
    createSuccess: currentLocale.value === 'pt' ? 'Pedido criado com sucesso!' : 'Request created successfully!',
    createError: currentLocale.value === 'pt' ? 'Erro ao criar pedido.' : 'Failed to create request.',
    acceptSuccess: currentLocale.value === 'pt' ? 'Troca aceite com sucesso!' : 'Swap accepted successfully!',
    acceptError: currentLocale.value === 'pt' ? 'Erro ao aceitar troca.' : 'Failed to accept swap.',
    rejectSuccess: currentLocale.value === 'pt' ? 'Troca rejeitada com sucesso!' : 'Swap rejected successfully!',
    rejectError: currentLocale.value === 'pt' ? 'Erro ao rejeitar troca.' : 'Failed to reject swap.',
    cancelSuccess: currentLocale.value === 'pt' ? 'Pedido cancelado com sucesso!' : 'Request cancelled successfully!',
    cancelError: currentLocale.value === 'pt' ? 'Erro ao cancelar pedido.' : 'Failed to cancel request.',
    invalidForm: currentLocale.value === 'pt' ? 'Preencha os IDs com valores válidos.' : 'Please provide valid IDs.',
  },
}))

const authUserId = computed(() => Number(user.value?.id ?? 0))

const formatDate = (value: string) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(currentLocale.value === 'pt' ? 'pt-PT' : 'en-US', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(date)
}

// Maps raw shift type names from the API (e.g. 'morning', 'dayoff') to
// localised display labels, falling back to the raw name when no mapping exists.
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

const getRequesterId = (swap: any) => {
  const participant = swap.participants?.find((p: any) => p.role === 'requester')
  return Number(participant?.id ?? swap.creator?.id ?? 0)
}

const getTargetId = (swap: any) => {
  const participant = swap.participants?.find((p: any) => p.role === 'target')
  return Number(participant?.id ?? 0)
}

const isSentSwap = (swap: any) => getRequesterId(swap) === authUserId.value
const isReceivedSwap = (swap: any) => getTargetId(swap) === authUserId.value

const getOtherParticipantName = (swap: any) => {
  if (isSentSwap(swap)) {
    return swap.participants?.find((p: any) => p.role === 'target')?.name || '-'
  }

  return swap.participants?.find((p: any) => p.role === 'requester')?.name || '-'
}

const statusLabel = (status: string) => {
  if (status === 'pending') return texts.value.filters.pending
  if (status === 'accepted') return texts.value.filters.accepted
  if (status === 'rejected') return texts.value.filters.rejected
  if (status === 'cancelled') return texts.value.filters.cancelled
  return status
}

const getPrimaryShift = (shifts: any[]) => (Array.isArray(shifts) && shifts.length > 0 ? shifts[0] : null)

const sentSwaps = computed(() => {
  if (authUserId.value === 0) return []
  return swapRequests.value.filter((req) => isSentSwap(req))
})
const receivedSwaps = computed(() => {
  if (authUserId.value === 0) return []
  return swapRequests.value.filter((req) => isReceivedSwap(req))
})

const listToRender = computed(() => {
  if (!selectedDirection.value) return swapRequests.value
  return selectedDirection.value === 'sent' ? sentSwaps.value : receivedSwaps.value
})

// Groups swaps into display sections. Without a direction filter the list splits
// into named "Sent" and "Received" sections; with a filter it's a single flat list.
const sections = computed(() => {
  if (selectedDirection.value) {
    return [{ key: 'filtered', title: null as string | null, items: listToRender.value }]
  }
  return [
    { key: 'sent', title: texts.value.sentSection, items: sentSwaps.value },
    { key: 'received', title: texts.value.receivedSection, items: receivedSwaps.value },
  ]
})

const updateSwapInState = (updatedSwap: any) => {
  const index = swapRequests.value.findIndex((item) => item.id === updatedSwap.id)
  if (index >= 0) {
    swapRequests.value.splice(index, 1, updatedSwap)
    return
  }

  swapRequests.value.unshift(updatedSwap)
}

const loadSwaps = async () => {
  await fetchSwaps(
    selectedDirection.value || undefined,
    selectedStatus.value || undefined
  )
}

const closeAllDropdowns = () => {
  isDirectionOpen.value = false
  isStatusOpen.value = false
}

if (process.client) {
  window.addEventListener('click', (event: MouseEvent) => {
    const target = event.target as HTMLElement
    if (!target.closest('.sw-select-wrapper')) {
      closeAllDropdowns()
    }
  })
}

const openActionModal = (type: ActionType, swapId: number) => {
  actionType.value = type
  actionSwapId.value = swapId
  showActionModal.value = true
}

const closeActionModal = () => {
  showActionModal.value = false
  actionSwapId.value = null
  actionType.value = null
}

const confirmActionText = computed(() => {
  if (actionType.value === 'accept') return texts.value.confirmModal.accept
  if (actionType.value === 'reject') return texts.value.confirmModal.reject
  if (actionType.value === 'cancel') return texts.value.confirmModal.cancel
  return ''
})

const executeAction = async () => {
  if (!actionType.value || !actionSwapId.value) return

  actionLoading.value = true

  try {
    let updatedSwap

    if (actionType.value === 'accept') {
      updatedSwap = await acceptSwap(actionSwapId.value)
      showNotification('acceptSuccess', 'success')
    } else if (actionType.value === 'reject') {
      updatedSwap = await rejectSwap(actionSwapId.value)
      showNotification('rejectSuccess', 'success')
    } else {
      updatedSwap = await cancelSwap(actionSwapId.value)
      showNotification('cancelSuccess', 'success')
    }

    updateSwapInState(updatedSwap)
    closeActionModal()
  } catch (error) {
    if (actionType.value === 'accept') showNotification('acceptError', 'error')
    if (actionType.value === 'reject') showNotification('rejectError', 'error')
    if (actionType.value === 'cancel') showNotification('cancelError', 'error')
    console.error('Swap action error:', error)
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  await fetchSwaps(
    selectedDirection.value || undefined,
    selectedStatus.value || undefined
  )
})

watch([selectedDirection, selectedStatus], async () => {
  await fetchSwaps(
    selectedDirection.value || undefined,
    selectedStatus.value || undefined
  )
})
</script>

<template>
  <main class="dashboard-layout sw-page">
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
          <span>{{ texts.toasts[statusMessage.key as keyof typeof texts.toasts] }}</span>
        </div>
      </div>
    </transition>

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

        <button class="sw-create-btn" @click="navigateTo('/swap-select')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          {{ texts.newRequest }}
        </button>
      </div>

      <div class="sw-toolbar">
        <div class="sw-filters-group">
          <div class="sw-select-wrapper">
            <div class="sw-select-trigger" @click.stop="isDirectionOpen = !isDirectionOpen; isStatusOpen = false">
              <span>
                {{
                  selectedDirection
                    ? (selectedDirection === 'sent' ? texts.filters.sent : texts.filters.received)
                    : texts.filters.allDirections
                }}
              </span>
              <svg :class="['sw-chevron-icon', { rotate: isDirectionOpen }]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>

            <transition name="fade-slide">
              <div v-if="isDirectionOpen" class="sw-custom-options">
                <div class="sw-option-item" @click="selectedDirection = ''; isDirectionOpen = false">{{ texts.filters.allDirections }}</div>
                <div class="sw-option-item" @click="selectedDirection = 'sent'; isDirectionOpen = false">{{ texts.filters.sent }}</div>
                <div class="sw-option-item" @click="selectedDirection = 'received'; isDirectionOpen = false">{{ texts.filters.received }}</div>
              </div>
            </transition>
          </div>

          <div class="sw-select-wrapper">
            <div class="sw-select-trigger" @click.stop="isStatusOpen = !isStatusOpen; isDirectionOpen = false">
              <span>
                {{
                  selectedStatus
                    ? statusLabel(selectedStatus)
                    : texts.filters.allStatus
                }}
              </span>
              <svg :class="['sw-chevron-icon', { rotate: isStatusOpen }]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>

            <transition name="fade-slide">
              <div v-if="isStatusOpen" class="sw-custom-options">
                <div class="sw-option-item" @click="selectedStatus = ''; isStatusOpen = false">{{ texts.filters.allStatus }}</div>
                <div class="sw-option-item" @click="selectedStatus = 'pending'; isStatusOpen = false">{{ texts.filters.pending }}</div>
                <div class="sw-option-item" @click="selectedStatus = 'accepted'; isStatusOpen = false">{{ texts.filters.accepted }}</div>
                <div class="sw-option-item" @click="selectedStatus = 'rejected'; isStatusOpen = false">{{ texts.filters.rejected }}</div>
                <div class="sw-option-item" @click="selectedStatus = 'cancelled'; isStatusOpen = false">{{ texts.filters.cancelled }}</div>
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
          <!--
            v-else-if="!swapRequests?.length"  → swapRequests (destructured ref, auto-unwrapped in template)
            v-for="section in sections"        → sections computed: Array<{ key, title, items: SwapRequest[] }>
              v-for="swapItem in section.items"  → section.items: SwapRequest[]
          -->
          <div v-for="section in sections" :key="section.key" class="sw-section">
            <h2 v-if="section.title" class="sw-section-title">{{ section.title }}</h2>
            <div v-if="section.items.length === 0" class="sw-empty-section">{{ texts.noRequests }}</div>
            <div v-else class="sw-list">
              <article
                v-for="swapItem in section.items"
                :key="swapItem.id"
                :class="['sw-item', { 'sw-item--resolved': swapItem.status !== 'pending' }]"
              >
                <div class="sw-item-header">
                  <div class="sw-item-top-row">
                    <span class="sw-counterparty">Com {{ getOtherParticipantName(swapItem) }}</span>
                    <span :class="['sw-status-badge', `status-${swapItem.status}`]">{{ statusLabel(swapItem.status) }}</span>
                  </div>
                  <p class="sw-item-date">{{ formatDate(swapItem.created_at) }}</p>
                </div>

                <!-- Option B swap header: offered and requested panels flanking a centre swap icon. -->
                <div class="sw-swap-header">
                  <div class="sw-swap-header__panel">
                    <span class="sw-swap-header__badge">{{ texts.card.offered }}</span>
                    <p class="sw-swap-header__date">{{ formatDate(getPrimaryShift(swapItem.offered_shifts)?.date || '-') }}</p>
                    <p class="sw-swap-header__type">{{ getShiftName(getPrimaryShift(swapItem.offered_shifts)?.shift_type) }}</p>
                    <p class="sw-swap-header__owner">{{ texts.card.by }} {{ getPrimaryShift(swapItem.offered_shifts)?.owner?.name || '-' }}</p>
                  </div>

                  <div class="sw-swap-header__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                      <path d="M20 7h-4V3"></path>
                      <path d="M20 7a8 8 0 0 0-14-3"></path>
                      <path d="M4 17h4v4"></path>
                      <path d="M4 17a8 8 0 0 0 14 3"></path>
                    </svg>
                  </div>

                  <div class="sw-swap-header__panel">
                    <span class="sw-swap-header__badge">{{ texts.card.requested }}</span>
                    <p class="sw-swap-header__date">{{ formatDate(getPrimaryShift(swapItem.requested_shifts)?.date || '-') }}</p>
                    <p class="sw-swap-header__type">{{ getShiftName(getPrimaryShift(swapItem.requested_shifts)?.shift_type) }}</p>
                    <p class="sw-swap-header__owner">{{ texts.card.by }} {{ getPrimaryShift(swapItem.requested_shifts)?.owner?.name || '-' }}</p>
                  </div>
                </div>

                <p v-if="swapItem.notes" class="sw-notes"><strong>{{ texts.card.notes }}:</strong> {{ swapItem.notes }}</p>

                <div class="sw-actions-row">
                  <button
                    v-if="isSentSwap(swapItem) && swapItem.status === 'pending'"
                    class="sw-action-btn cancel"
                    @click="openActionModal('cancel', swapItem.id)"
                  >
                    {{ texts.actions.cancel }}
                  </button>

                  <template v-if="isReceivedSwap(swapItem) && swapItem.status === 'pending'">
                    <button class="sw-action-btn accept" @click="openActionModal('accept', swapItem.id)">
                      {{ texts.actions.accept }}
                    </button>
                    <button class="sw-action-btn reject" @click="openActionModal('reject', swapItem.id)">
                      {{ texts.actions.reject }}
                    </button>
                  </template>
                </div>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>

    <transition name="fade">
      <div v-if="showActionModal" class="sw-modal-overlay" @click.self="closeActionModal">
        <div class="sw-modal-card">
          <div class="sw-modal-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 8v4m0 4h.01"></path>
              <circle cx="12" cy="12" r="10"></circle>
            </svg>
          </div>
          <h2>{{ texts.confirmModal.title }}</h2>
          <p>{{ confirmActionText }}</p>

          <div class="sw-modal-actions">
            <button class="sw-modal-btn cancel" :disabled="actionLoading" @click="closeActionModal">
              {{ texts.confirmModal.close }}
            </button>
            <button class="sw-modal-btn confirm" :disabled="actionLoading" @click="executeAction">
              {{ texts.confirmModal.confirm }}
            </button>
          </div>
        </div>
      </div>
    </transition>

  </main>
</template>
