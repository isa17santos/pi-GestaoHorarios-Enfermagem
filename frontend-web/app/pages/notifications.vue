<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'

definePageMeta({
  middleware: 'auth',
})

const { user } = useAuth()
const { 
  notifications, 
  loading, 
  fetchNotifications, 
  markAsRead, 
  markAllAsRead 
} = useNotifications()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')
const filter = ref<'all' | 'unread' | 'read'>('all')
const statusMessage = ref<{ key: string; type: 'success' | 'error' } | null>(null)


useHead({
  title: currentLocale.value === 'pt' ? 'Notificações - ShiftCare' : 'Notifications - ShiftCare',
  meta: [
    { 
      name: 'description', 
      content: currentLocale.value === 'pt' ? 'Consulte as suas notificações de email do sistema.' : 'View your system email notifications.' 
    }
  ]
})

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Central de Notificações' : 'Notification Center',
  subtitle: currentLocale.value === 'pt' ? 'Consulte as mensagens e alertas de email enviados pelo sistema' : 'View email messages and alerts sent by the system',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  markAllRead: currentLocale.value === 'pt' ? 'Marcar todas como lidas' : 'Mark all as read',
  filterAll: currentLocale.value === 'pt' ? 'Todas' : 'All',
  filterUnread: currentLocale.value === 'pt' ? 'Por ler' : 'Unread',
  filterRead: currentLocale.value === 'pt' ? 'Lidas' : 'Read',
  empty: currentLocale.value === 'pt' ? 'Não tem notificações de momento.' : 'You have no notifications at the moment.',
  markReadSuccess: currentLocale.value === 'pt' ? 'Notificação aberta e lida.' : 'Notification opened and read.',
  markAllReadSuccess: currentLocale.value === 'pt' ? 'Todas as notificações marcadas como lidas.' : 'All notifications marked as read.',
  loading: currentLocale.value === 'pt' ? 'A carregar notificações...' : 'Loading notifications...',
  receivedAt: currentLocale.value === 'pt' ? 'Recebido a' : 'Received at',
  pagination: {
    previous: currentLocale.value === 'pt' ? 'Anterior' : 'Previous',
    next: currentLocale.value === 'pt' ? 'Seguinte' : 'Next',
  },
}))

const showToast = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 3000)
}

const handleMarkAllRead = async () => {
  await markAllAsRead()
  showToast('markAllReadSuccess', 'success')
}

const handleCardClick = async (notif: any) => {
  if (!notif.read) {
    await markAsRead(notif.id)
    showToast('markReadSuccess', 'success')
  }
}


const filteredNotifications = computed(() => {
  if (filter.value === 'unread') {
    return notifications.value.filter(n => !n.read)
  }
  if (filter.value === 'read') {
    return notifications.value.filter(n => n.read)
  }
  return notifications.value
})

// --- Pagination ---
const pageSize = 5 
const currentPage = ref(1)

const totalItems = computed(() => filteredNotifications.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / pageSize)))
const paginatedNotifications = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredNotifications.value.slice(start, start + pageSize)
})

const goToPreviousPage = () => { if (currentPage.value > 1) currentPage.value-- }
const goToNextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
const setPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) currentPage.value = page
}

watch(filter, () => {
  currentPage.value = 1
})

watch(totalPages, (newTotal) => {
  if (currentPage.value > newTotal) currentPage.value = newTotal
})
// ---------------------------

const shiftNameMap: Record<string, { pt: string; en: string }> = {
  morning:         { pt: 'Manhã',           en: 'Morning' },
  manhã:           { pt: 'Manhã',           en: 'Morning' },
  afternoon:       { pt: 'Tarde',           en: 'Afternoon' },
  tarde:           { pt: 'Tarde',           en: 'Afternoon' },
  night:           { pt: 'Noite',           en: 'Night' },
  noite:           { pt: 'Noite',           en: 'Night' },
  dayoff:          { pt: 'Folga',           en: 'Day Off' },
  'day off':       { pt: 'Folga',           en: 'Day Off' },
  folga:           { pt: 'Folga',           en: 'Day Off' },
  holidays:        { pt: 'Férias',          en: 'Holidays' },
  férias:          { pt: 'Férias',          en: 'Holidays' },
  'sick leave':    { pt: 'Baixa Médica',    en: 'Sick Leave' },
  'baixa médica':  { pt: 'Baixa Médica',    en: 'Sick Leave' },
  'parental leave':{ pt: 'Licença Parental',en: 'Parental Leave' },
}

const formatDate = (dateString: string) => {
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return dateString

  return date.toLocaleDateString(currentLocale.value === 'pt' ? 'pt-PT' : 'en-US', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatIsoDate = (iso: string) => {
  const date = new Date(iso + 'T00:00:00')
  if (isNaN(date.getTime())) return iso
  return date.toLocaleDateString(currentLocale.value === 'pt' ? 'pt-PT' : 'en-US', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

const formatNotificationBody = (body: string) => {
  const locale = currentLocale.value
  let result = body.replace(/\b(\d{4}-\d{2}-\d{2})\b/g, (_, iso) => formatIsoDate(iso))
  for (const [slug, translations] of Object.entries(shiftNameMap)) {
    const escaped = slug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    result = result.replace(new RegExp(`\\b${escaped}\\b`, 'gi'), translations[locale])
  }
  return result
}

onMounted(async () => {
  await fetchNotifications()
})
</script>

<template>
  <main class="dashboard-layout notifications-page">
    <AppNavbar />

    <transition name="slide-down">
      <div v-if="statusMessage" :class="['global-toast', statusMessage.type]">
        <div class="toast-content">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="22">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span>{{ (texts as any)[statusMessage.key] }}</span>
        </div>
      </div>
    </transition>

    <section class="notifications-content">
      <div class="notifications-container">
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

        <div class="notifications-toolbar">
          <div class="filters-tabs">
            <button 
              :class="['filter-tab-btn', { active: filter === 'all' }]" 
              @click="filter = 'all'"
            >
              {{ texts.filterAll }}
            </button>
            <button 
              :class="['filter-tab-btn', { active: filter === 'unread' }]" 
              @click="filter = 'unread'"
            >
              {{ texts.filterUnread }}
            </button>
            <button 
              :class="['filter-tab-btn', { active: filter === 'read' }]" 
              @click="filter = 'read'"
            >
              {{ texts.filterRead }}
            </button>
          </div>

          <div class="toolbar-actions">
            <button 
              class="action-outline-btn" 
              @click="handleMarkAllRead" 
              :disabled="!notifications.some(n => !n.read)"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              {{ texts.markAllRead }}
            </button>
          </div>
        </div>

        <div class="notifications-list-wrapper">
          <div v-if="loading" class="state-loading">
            <div class="spinner"></div>
            <p>{{ texts.loading }}</p>
          </div>

          <div v-else-if="filteredNotifications.length === 0" class="empty-state">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <p>{{ texts.empty }}</p>
          </div>

                    <template v-else>
            <div class="notifications-list">
              <div 
                v-for="notif in paginatedNotifications" 
                :key="notif.id" 
                :class="['notification-item-card', { 'unread': !notif.read }]"
                @click="handleCardClick(notif)"
              >
                <div class="notif-header-info">
                  <div :class="['notif-avatar', { 'unread-avatar': !notif.read }]">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                      <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                  </div>
                  
                  <div class="notif-text-content">
                    <div class="notif-title-row">
                      <h3 class="notif-subject">{{ notif.subject }}</h3>
                      <span v-if="!notif.read" class="unread-badge-dot"></span>
                    </div>
                    <p class="notif-body">{{ formatNotificationBody(notif.body) }}</p>
                    <span class="notif-date">{{ texts.receivedAt }}: {{ formatDate(notif.created_at) }}</span>
                  </div>
                </div>
                <div class="notif-actions" v-if="!notif.read">
                  <button 
                    class="mark-single-read-btn" 
                    @click.stop="handleCardClick(notif)"
                    :title="texts.markReadSuccess"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Controles de Paginação -->
            <div v-if="totalPages > 1" class="notif-pagination">
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
          </template>
        </div>
      </div>
    </section>
  </main>
</template>