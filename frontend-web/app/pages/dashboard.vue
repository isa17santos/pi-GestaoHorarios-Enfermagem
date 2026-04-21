<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

import logoUrl from '~/assets/images/logotipo.png'

const { user, logout } = useAuth()

// Only users with head nurse profiles should see schedule creation actions.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

// Only users with admin profile should see HR actions.
const canManageHR = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin'
})


const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')
const localeLabel = computed(() =>
  currentLocale.value === 'pt' ? 'English' : 'Português'
)
const localeFlag = computed(() =>
  currentLocale.value === 'pt' ? 'en' : 'pt'
)
const toggleLanguage = () => {
  currentLocale.value = currentLocale.value === 'pt' ? 'en' : 'pt'
}

const texts = computed(() => ({
  welcome: currentLocale.value === 'pt' ? 'Bem-vindo' : 'Welcome',
  logout: currentLocale.value === 'pt' ? 'Terminar sessão' : 'Sign out',
  createSchedule: currentLocale.value === 'pt' ? 'Criar horário' : 'Create schedule',
  humanResources: currentLocale.value === 'pt' ? 'Gestão Recursos Humanos' : 'Human Resources Management',
  vacations: currentLocale.value === 'pt' ? 'Gestão férias' : 'Vacations',
  sickLeaves: currentLocale.value === 'pt' ? 'Gestão baixas' : 'Absences',
  shiftTypes: currentLocale.value === 'pt' ? 'Gestão tipos de turno' : 'Shift types',
  statistics: currentLocale.value === 'pt' ? 'Estatísticas' : 'Statistics',
  myProfile: currentLocale.value === 'pt' ? 'Meu Perfil' : 'My Profile',
}))
</script>

<template>
  <main class="dashboard-layout">
    <div class="dashboard-top-row">
      <!-- Logo -->
      <img :src="logoUrl" alt="ShiftCare" class="dashboard-logo-img" />
      
      <!-- Nav bar and Language Switch aligned together -->
      <div class="dashboard-actions-group">
        <header class="dashboard-header">
          <div class="header-right">
            <div class="header-user" v-if="user">
              <span class="user-name">{{ user.name }}</span>
              <span class="user-role">{{ user.role }}</span>
            </div>
            
            <NuxtLink to="/profile" class="header-action-btn" :title="texts.myProfile">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </NuxtLink>
            
            <button class="header-action-btn header-action-btn--danger" @click="logout" :title="texts.logout">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
            </button>
          </div>
        </header>

        <button class="language-switch dashboard-lang-override" type="button" @click="toggleLanguage">
          <span class="language-switch__flag">{{ localeFlag }}</span>
          <span>{{ localeLabel }}</span>
        </button>
      </div>
    </div>

    <section class="dashboard-content">
      <div class="dashboard-greeting" v-if="user">
        <h1>{{ texts.welcome }}, <strong>{{ user.name }}</strong>.</h1>
        <p class="greeting-sub">
          {{ currentLocale === 'pt' ? 'Gestão de Recursos Humanos e Operações' : 'Human Resources and Operations Management' }}
        </p>
      </div>

      <div class="bento-grid">
        <NuxtLink v-if="canCreateSchedule" to="/schedule-create" class="bento-card bento-card--schedule">
          <div class="bento-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
          </div>
          <h3>{{ texts.createSchedule }}</h3>
          <p>{{ currentLocale === 'pt' ? 'Planeamento de equipas e turnos' : 'Team and shift planning' }}</p>
        </NuxtLink>

        <NuxtLink v-if="canManageHR" to="/human-resources" class="bento-card bento-card--hr">
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
        </NuxtLink>

        <NuxtLink v-if="canManageHR" to="/vacations" class="bento-card bento-card--vacations">
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
        </NuxtLink>

        <NuxtLink v-if="canManageHR" to="/sick-leaves" class="bento-card bento-card--sick">
          <div class="bento-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
              <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
          </div>
          <h3>{{ texts.sickLeaves }}</h3>
          <p>{{ currentLocale === 'pt' ? 'Registo e acompanhamento' : 'Recording and monitoring' }}</p>
        </NuxtLink>

        <NuxtLink v-if="canManageHR" to="/shift-types" class="bento-card bento-card--shifts">
          <div class="bento-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
          </div>
          <h3>{{ texts.shiftTypes }}</h3>
          <p>{{ currentLocale === 'pt' ? 'Configuração de tipos de turnos' : 'Configuration of shift patterns' }}</p>
        </NuxtLink>

        <NuxtLink v-if="canManageHR" to="/statistics" class="bento-card bento-card--stats">
          <div class="bento-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
          </div>
          <h3>{{ texts.statistics }}</h3>
          <p>{{ currentLocale === 'pt' ? 'Controlo de serviços e recursos' : 'Service and resource control' }}</p>
        </NuxtLink>
      </div>
    </section>
  </main>
</template>

<style src="~/assets/css/dashboardAdmin.css"></style>
