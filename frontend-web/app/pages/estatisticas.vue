<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { user } = useAuth()
const { adminData, headNurseData, loadingStatistics: loadingDashboard, fetchStatistics } = useStatistics()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Role helpers
const isAdmin = computed(() => user.value?.role?.trim().toLowerCase() === 'admin')
const isHeadNurse = computed(() => {
  const role = user.value?.role?.trim().toLowerCase() || ''
  return role === 'head_nurse' || role === 'head nurse'
})

// Redirect nurses away — this page is not for them.
onMounted(async () => {
  const role = user.value?.role?.trim().toLowerCase()
  if (role === 'nurse') {
    await navigateTo('/dashboard')
    return
  }
  if (role === 'admin' || role === 'head_nurse') fetchStatistics()
})

const texts = computed(() => ({
  title:               currentLocale.value === 'pt' ? 'Estatísticas' : 'Statistics',
  nurses:              currentLocale.value === 'pt' ? 'Enfermeiros' : 'Nurses',
  nursesDesc:          currentLocale.value === 'pt' ? 'Utilizadores com role enfermeiro' : 'Users with nurse role',
  headNurses:          currentLocale.value === 'pt' ? 'Enfermeiros Chefes' : 'Head Nurses',
  headNursesDesc:      currentLocale.value === 'pt' ? 'Utilizadores com role enfermeiro chefe' : 'Users with head nurse role',
  medicalLeaves:       currentLocale.value === 'pt' ? 'Baixas este mês' : 'Medical leaves this month',
  medicalLeavesDesc:   currentLocale.value === 'pt' ? 'Baixas com sobreposição no mês atual' : 'Leaves overlapping the current month',
  vacations:           currentLocale.value === 'pt' ? 'Férias este mês' : 'Vacations this month',
  vacationsDesc:       currentLocale.value === 'pt' ? 'Férias com sobreposição no mês atual' : 'Vacations overlapping the current month',
  // Head nurse — quality indicator
  qualityLabel:        currentLocale.value === 'pt' ? 'Qualidade do serviço' : 'Service quality',
  qualityBom:          currentLocale.value === 'pt' ? 'Bom' : 'Good',
  medioo:              currentLocale.value === 'pt' ? 'Médio' : 'Fair',
  mau:                 currentLocale.value === 'pt' ? 'Mau' : 'Poor',
  qualityProvisorio:   currentLocale.value === 'pt' ? 'Indicador provisório — preferências em falta' : 'Provisional indicator — preferences pending',
  // Head nurse — acceptance rate
  acceptanceRate:      currentLocale.value === 'pt' ? 'Taxa de aceitação de trocas' : 'Swap acceptance rate',
  acceptanceRateDesc:  currentLocale.value === 'pt' ? 'Pedidos aceites sobre o total de respondidos este mês' : 'Accepted over total responded this month',
  noData:              currentLocale.value === 'pt' ? 'Sem dados' : 'No data',
  // Head nurse — hours table
  hoursTable:          currentLocale.value === 'pt' ? 'Horas por enfermeiro' : 'Hours per nurse',
  hoursTableDesc:      currentLocale.value === 'pt' ? 'Total de horas trabalhadas no mês atual' : 'Total hours worked this month',
  colName:             currentLocale.value === 'pt' ? 'Nome' : 'Name',
  colHours:            currentLocale.value === 'pt' ? 'Horas' : 'Hours',
}))
</script>

<template>
  <main class="dashboard-layout">
    <AppNavbar />

    <section class="dashboard-content">
      <div class="dashboard-greeting">
        <h1>{{ texts.title }}</h1>
      </div>

      <div class="bento-grid">

        <!-- ========================================== -->
        <!-- KPI CARDS DO ADMIN                         -->
        <!-- ========================================== -->
        <template v-if="isAdmin">
          <template v-if="loadingDashboard || !adminData">
            <!-- Placeholders enquanto os dados carregam -->
            <div v-for="n in 4" :key="n" class="bento-card bento-card--stats stats-loading" />
          </template>

          <template v-else>
            <div class="bento-card bento-card--hr">
              <div class="bento-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                </svg>
              </div>
              <h3>{{ texts.nurses }}</h3>
              <p class="bento-stat-value">{{ adminData.nurses_count }}</p>
              <p>{{ texts.nursesDesc }}</p>
            </div>

            <div class="bento-card bento-card--shifts">
              <div class="bento-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </div>
              <h3>{{ texts.headNurses }}</h3>
              <p class="bento-stat-value">{{ adminData.head_nurses_count }}</p>
              <p>{{ texts.headNursesDesc }}</p>
            </div>

            <div class="bento-card bento-card--sick">
              <div class="bento-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                  <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                </svg>
              </div>
              <h3>{{ texts.medicalLeaves }}</h3>
              <p class="bento-stat-value">{{ adminData.medical_leaves_this_month }}</p>
              <p>{{ texts.medicalLeavesDesc }}</p>
            </div>

            <div class="bento-card bento-card--vacations">
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
              <p class="bento-stat-value">{{ adminData.vacations_this_month }}</p>
              <p>{{ texts.vacationsDesc }}</p>
            </div>
          </template>
        </template>

        <!-- ========================================== -->
        <!-- CARDS DO ENFERMEIRO CHEFE (HEAD NURSE)     -->
        <!-- ========================================== -->
        <template v-if="isHeadNurse">
          <!-- Skeleton loader enquanto os dados chegam -->
          <template v-if="loadingDashboard || !headNurseData">
            <div v-for="n in 3" :key="n" class="bento-card bento-card--stats stats-loading" />
          </template>

          <template v-else>
            <!-- Quality indicator — classificação em 3 níveis baseada em trocas + violações de mínimos.
                 Cor via style inline porque é determinada por dados da API (excepção permitida pela convenção). -->
            <div class="bento-card bento-card--stats">
              <div class="bento-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
              </div>
              <h3>{{ texts.qualityLabel }}</h3>
              <p
                class="bento-stat-value quality-badge"
                :style="{ color: headNurseData.quality_indicator === 'bom' ? '#4caf7d' : headNurseData.quality_indicator === 'mau' ? 'var(--danger)' : 'var(--peach)' }"
              >
                {{
                  headNurseData.quality_indicator === 'bom' ? texts.qualityBom
                  : headNurseData.quality_indicator === 'mau' ? texts.mau
                  : texts.medioo
                }}
              </p>
              <p class="quality-note">{{ texts.qualityProvisorio }}</p>
            </div>

            <!-- Taxa de aceitação de trocas -->
            <div class="bento-card bento-card--shifts">
              <div class="bento-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                  <polyline points="17 1 21 5 17 9"></polyline>
                  <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                  <polyline points="7 23 3 19 7 15"></polyline>
                  <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                </svg>
              </div>
              <h3>{{ texts.acceptanceRate }}</h3>
              <p class="bento-stat-value">
                {{ headNurseData.acceptance_rate !== null ? `${headNurseData.acceptance_rate}%` : texts.noData }}
              </p>
              <p>{{ texts.acceptanceRateDesc }}</p>
            </div>

            <!-- Tabela de horas por enfermeiro, ordenada por horas decrescentes (ordem vem da API) -->
            <div class="bento-card bento-card--hr hours-card">
              <div class="bento-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
              </div>
              <h3>{{ texts.hoursTable }}</h3>
              <p style="margin-bottom: 16px;">{{ texts.hoursTableDesc }}</p>
              <table class="hours-table">
                <thead>
                  <tr>
                    <th>{{ texts.colName }}</th>
                    <th class="hours-col">{{ texts.colHours }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="entry in headNurseData.avg_hours_per_nurse" :key="entry.user_id">
                    <td>{{ entry.name }}</td>
                    <td class="hours-col">{{ entry.hours }}h</td>
                  </tr>
                  <tr v-if="headNurseData.avg_hours_per_nurse.length === 0">
                    <td colspan="2" class="hours-empty">{{ texts.noData }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </template>

      </div>
    </section>
  </main>
</template>

<style scoped>
/* Skeleton loader para os KPI cards enquanto aguardam dados */
.stats-loading {
  min-height: 160px;
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.4; }
}

/* Nota de rodapé discreta abaixo do quality indicator */
.quality-note {
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 8px;
  font-style: italic;
}

/* Card da tabela de horas ocupa coluna dupla em ecrãs largos */
.hours-card {
  grid-column: span 2;
}

@media (max-width: 768px) {
  .hours-card { grid-column: span 1; }
}

/* Tabela simples de horas — herda estilos do bento-card */
.hours-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}

.hours-table th {
  text-align: left;
  color: var(--muted);
  font-weight: 600;
  padding: 6px 0;
  border-bottom: 1px solid var(--line);
}

.hours-table td {
  padding: 8px 0;
  border-bottom: 1px solid var(--line);
  color: var(--text);
}

.hours-table tr:last-child td {
  border-bottom: none;
}

.hours-col {
  text-align: right;
  font-weight: 600;
  color: var(--primary);
}

.hours-empty {
  text-align: center;
  color: var(--muted);
  padding: 16px 0;
}
</style>
