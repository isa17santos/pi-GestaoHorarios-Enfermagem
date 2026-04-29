<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { user } = useAuth()

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
  createSchedule: currentLocale.value === 'pt' ? 'Criar horário' : 'Create schedule',
  editSchedule: currentLocale.value === 'pt' ? 'Editar horário' : 'Edit schedule',
  viewSchedule: currentLocale.value === 'pt' ? 'Consultar horário' : 'View schedule',
  preferences: currentLocale.value === 'pt' ? 'Perfis de preferências' : 'Preference profiles',
  
  // Textos Admin
  humanResources: currentLocale.value === 'pt' ? 'Gestão Recursos Humanos' : 'Human Resources Management',
  vacations: currentLocale.value === 'pt' ? 'Gestão férias' : 'Vacations',
  sickLeaves: currentLocale.value === 'pt' ? 'Gestão baixas' : 'Absences',
  shiftTypes: currentLocale.value === 'pt' ? 'Gestão tipos de turno' : 'Shift types',
  
  // Textos Enfermeiro
  schedule: currentLocale.value === 'pt' ? 'Horário' : 'Schedule',
  swaps: currentLocale.value === 'pt' ? 'Trocas' : 'Shift Swaps',
  
  // Partilhado
  statistics: currentLocale.value === 'pt' ? 'Estatísticas' : 'Statistics',
}))
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

      <div class="bento-grid">

        <!-- ========================================== -->
        <!-- CARDS DO ENFERMEIRO CHEFE (HEAD NURSE)     -->
        <!-- ========================================== -->
        <template v-if="isHeadNurse">
          <NuxtLink to="/schedule-create" class="bento-card bento-card--hr">
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
          </NuxtLink>

          <NuxtLink to="/schedule-edit" class="bento-card bento-card--vacations">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </div>
            <h3>{{ texts.editSchedule }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Fazer alterações à escala atual' : 'Make changes to the current schedule' }}</p>
          </NuxtLink>

          <NuxtLink to="/schedule-view" class="bento-card bento-card--sick">
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
          </NuxtLink>
        </template>


        <!-- ========================================== -->
        <!-- CARDS DO ENFERMEIRO (NURSE)                -->
        <!-- ========================================== -->
        <template v-if="isNurse">
          <NuxtLink to="/schedule-view" class="bento-card bento-card--hr">
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
          </NuxtLink>

          <NuxtLink to="/swaps" class="bento-card bento-card--vacations">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <polyline points="17 1 21 5 17 9"></polyline>
                <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                <polyline points="7 23 3 19 7 15"></polyline>
                <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
              </svg>
            </div>
            <h3>{{ texts.swaps }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Gerir pedidos de troca de turno' : 'Manage shift swap requests' }}</p>
          </NuxtLink>
        </template>


        <!-- ========================================== -->
        <!-- CARDS DO ADMIN (RECURSOS HUMANOS)          -->
        <!-- ========================================== -->
        <template v-if="isAdmin">
          <NuxtLink to="/human-resources" class="bento-card bento-card--hr">
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

          <NuxtLink to="/vacations" class="bento-card bento-card--vacations">
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

          <NuxtLink to="/sick-leaves" class="bento-card bento-card--sick">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
              </svg>
            </div>
            <h3>{{ texts.sickLeaves }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Registo e acompanhamento' : 'Recording and monitoring' }}</p>
          </NuxtLink>

          <NuxtLink to="/shift-types" class="bento-card bento-card--shifts">
            <div class="bento-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <h3>{{ texts.shiftTypes }}</h3>
            <p>{{ currentLocale === 'pt' ? 'Configuração de tipos de turnos' : 'Configuration of shift patterns' }}</p>
          </NuxtLink>
        </template>


        <!-- ========================================== -->
        <!-- CARD PARTILHADO: ESTATÍSTICAS              -->
        <!-- ========================================== -->
        <NuxtLink v-if="isAdmin || isNurse || isHeadNurse" to="/statistics" class="bento-card bento-card--stats">
          <div class="bento-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
          </div>
          <h3>{{ texts.statistics }}</h3>
          
          <p v-if="isAdmin">{{ currentLocale === 'pt' ? 'Controlo de serviços e recursos' : 'Service and resource control' }}</p>
          <p v-else-if="isHeadNurse">{{ currentLocale === 'pt' ? 'Relatórios de cobertura da equipa' : 'Team coverage reports' }}</p>
          <p v-else>{{ currentLocale === 'pt' ? 'As minhas estatísticas e horas' : 'My statistics and hours' }}</p>
        </NuxtLink>

      </div>
    </section>
  </main>
</template>