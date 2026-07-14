<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { user } = useAuth()
const { adminData, headNurseData, loadingStatistics: loadingDashboard, fetchStatistics } = useStatistics()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Nome do mês atual, capitalizado — mesmo padrão usado no resumo mensal do dashboard.vue
const currentMonthLabel = computed(() => {
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-GB'
  const month = new Date().toLocaleDateString(locale, { month: 'long', year: 'numeric' })
  return month.charAt(0).toUpperCase() + month.slice(1)
})

// Gauge circular da taxa de aceitação — perímetro fixo (raio 52) para calcular o offset do traço
const ACCEPTANCE_GAUGE_CIRCUMFERENCE = 2 * Math.PI * 52
const acceptanceGaugeOffset = computed(() => {
  const rate = headNurseData.value?.acceptance_rate
  if (rate === null || rate === undefined) return ACCEPTANCE_GAUGE_CIRCUMFERENCE
  const clamped = Math.min(100, Math.max(0, rate))
  return ACCEPTANCE_GAUGE_CIRCUMFERENCE * (1 - clamped / 100)
})

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
  back:                currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  title:               currentLocale.value === 'pt' ? 'Estatísticas' : 'Statistics',
  subtitle:            currentLocale.value === 'pt' ? 'Indicadores de serviço e cobertura da equipa' : 'Service and team coverage indicators',
  nurses:              currentLocale.value === 'pt' ? 'Enfermeiros ativos' : 'Active nurses',
  nursesDesc:          currentLocale.value === 'pt' ? 'Pessoas — utilizadores com role enfermeiro' : 'People — users with nurse role',
  headNurses:          currentLocale.value === 'pt' ? 'Enfermeiros Chefes ativos' : 'Active head nurses',
  headNursesDesc:      currentLocale.value === 'pt' ? 'Pessoas — utilizadores com role enfermeiro chefe' : 'People — users with head nurse role',
  medicalLeaves:       currentLocale.value === 'pt' ? 'Pessoas estão de baixa este mês' : 'People are on medical leave this month',
  medicalLeavesDesc:   currentLocale.value === 'pt' ? 'Baixas com sobreposição no mês atual' : 'Leaves overlapping the current month',
  vacations:           currentLocale.value === 'pt' ? 'Pessoas estão de férias este mês' : 'People are on vacation this month',
  vacationsDesc:       currentLocale.value === 'pt' ? 'Férias com sobreposição no mês atual' : 'Vacations overlapping the current month',
  inactiveUsers:       currentLocale.value === 'pt' ? 'Contas inativas' : 'Inactive accounts',
  inactiveUsersDesc:   currentLocale.value === 'pt' ? 'Pessoas — utilizadores desativados no sistema' : 'People — users deactivated in the system',
  pendingPasswordChange:     currentLocale.value === 'pt' ? 'Alteração de password pendente' : 'Pending password change',
  pendingPasswordChangeDesc: currentLocale.value === 'pt' ? 'Pessoas — utilizadores que ainda não alteraram a password' : 'People — users who have not changed their password yet',
  // Head nurse — quality data (raw numbers only, no score/level/formula)
  qualityLabel:        currentLocale.value === 'pt' ? 'Qualidade de Horário' : 'Schedule Quality',
  qualityExample:      currentLocale.value === 'pt'
    ? 'Estes são apenas os dados que podem alimentar uma futura fórmula de qualidade de horário — não há pontuação nem classificação calculada'
    : 'These are just the data points that could feed a future schedule-quality formula — no score or classification is calculated',
  // Textos revistos para serem compreensíveis sem contexto do desenvolvimento — cada item
  // explica o que significa, não só o número cru.
  qualityBasedOn:      currentLocale.value === 'pt' ? 'Dados disponíveis:' : 'Available data:',
  breakdownSwaps:      currentLocale.value === 'pt' ? 'trocas de turno este mês' : 'shift swaps this month',
  breakdownSwapsHint:  currentLocale.value === 'pt' ? 'Um número elevado de trocas pode indicar instabilidade no horário' : 'A high number of swaps may indicate schedule instability',
  breakdownMinNurses:  currentLocale.value === 'pt' ? 'turnos com falta de enfermeiros' : 'shifts understaffed',
  // Violações de preferências divididas em duas linhas, uma por unidade: type é contado por
  // enfermeiro (padrão sistemático no mês), weekend é contado por turno (ocorrência pontual).
  // Misturar as duas num só número dava contagens que podiam exceder o total de enfermeiros.
  breakdownPreferenceType:    currentLocale.value === 'pt' ? 'enfermeiros com preferências de turno não respeitadas' : 'nurses with unmet shift preferences',
  breakdownPreferenceWeekend: currentLocale.value === 'pt' ? 'turnos de fim de semana contra preferência' : 'weekend shifts against preference',
  // Head nurse — acceptance rate
  acceptanceRate:      currentLocale.value === 'pt' ? 'Taxa de aceitação de trocas' : 'Swap acceptance rate',
  acceptanceRateDesc:  currentLocale.value === 'pt' ? 'Percentagem de turnos trocados com sucesso, considerando pedidos concorrentes pelo mesmo turno' : 'Percentage of shifts successfully swapped, accounting for competing requests on the same shift',
  swapsAccepted:       currentLocale.value === 'pt' ? 'trocas aceites' : 'accepted swaps',
  swapsRejected:       currentLocale.value === 'pt' ? 'trocas rejeitadas' : 'rejected swaps',
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
      <!-- Botão voltar + título + descrição — mesmo agrupamento/espaçamento das outras páginas (uc-title-group) -->
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

      <!-- Mês de referência das estatísticas apresentadas -->
      <p class="stats-month-label">{{ currentMonthLabel }}</p>

      <!-- ========================================== -->
      <!-- KPIS DO ADMIN — mesmo padrão informativo usado no resumo mensal -->
      <!-- do dashboard.vue (stats-kpi-row / stats-kpi-card), não os cards -->
      <!-- de navegação bento-card. Fica fora do .bento-grid propositadamente. -->
      <!-- ========================================== -->
      <template v-if="isAdmin">
        <template v-if="loadingDashboard || !adminData">
          <!-- Placeholders enquanto os dados carregam -->
          <div class="bento-grid">
            <div v-for="n in 4" :key="n" class="bento-card bento-card--stats stats-loading" />
          </div>
        </template>

        <template v-else>
          <div class="dashboard-stats-section">
            <div class="stats-kpi-row">
              <div class="stats-kpi-card" :title="texts.nursesDesc">
                <span class="stats-kpi-value stats-kpi-value--with-icon">
                  {{ adminData.nurses_count }}
                  <svg class="stats-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <span class="stats-kpi-label">{{ texts.nurses }}</span>
              </div>

              <div class="stats-kpi-card" :title="texts.headNursesDesc">
                <span class="stats-kpi-value stats-kpi-value--with-icon">
                  {{ adminData.head_nurses_count }}
                  <svg class="stats-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <span class="stats-kpi-label">{{ texts.headNurses }}</span>
              </div>

              <div class="stats-kpi-card" :title="texts.medicalLeavesDesc">
                <span class="stats-kpi-value stats-kpi-value--with-icon">
                  {{ adminData.medical_leaves_this_month }}
                  <svg class="stats-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <span class="stats-kpi-label">{{ texts.medicalLeaves }}</span>
              </div>

              <div class="stats-kpi-card" :title="texts.vacationsDesc">
                <span class="stats-kpi-value stats-kpi-value--with-icon">
                  {{ adminData.vacations_this_month }}
                  <svg class="stats-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <span class="stats-kpi-label">{{ texts.vacations }}</span>
              </div>
            </div>

            <!-- Segunda linha — estado atual do sistema, sem componente de mês -->
            <div class="stats-kpi-row admin-kpi-row--system">
              <!-- Contas inativas -->
              <div class="stats-kpi-card" :title="texts.inactiveUsersDesc">
                <span class="stats-kpi-value stats-kpi-value--with-icon">
                  {{ adminData.inactive_users_count }}
                  <svg class="stats-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <span class="stats-kpi-label">{{ texts.inactiveUsers }}</span>
              </div>

              <!-- Alteração de password pendente -->
              <div class="stats-kpi-card" :title="texts.pendingPasswordChangeDesc">
                <span class="stats-kpi-value stats-kpi-value--with-icon">
                  {{ adminData.pending_password_change_count }}
                  <svg class="stats-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <span class="stats-kpi-label">{{ texts.pendingPasswordChange }}</span>
              </div>
            </div>
          </div>
        </template>
      </template>

      <div class="bento-grid">

        <!-- ========================================== -->
        <!-- CARDS DO ENFERMEIRO CHEFE (HEAD NURSE)     -->
        <!-- ========================================== -->
        <template v-if="isHeadNurse">
          <!-- Skeleton loader enquanto os dados chegam -->
          <template v-if="loadingDashboard || !headNurseData">
            <div v-for="n in 3" :key="n" class="bento-card bento-card--stats stats-loading" />
          </template>

          <template v-else>
            <!-- Dados de qualidade de horário — apenas os números brutos que poderiam alimentar uma
                 futura fórmula de qualidade; sem pontuação, classificação ou badge calculados.
                 Sem bento-icon: este estilo de ícone fica reservado para cards de navegação/ação,
                 não para blocos informativos (mesmo tratamento já aplicado ao bloco admin). -->
            <div class="bento-card bento-card--stats quality-card">
              <h3>{{ texts.qualityLabel }}</h3>
              <p class="quality-note">{{ texts.qualityExample }}</p>

              <div class="quality-breakdown">
                <span class="quality-breakdown-label">{{ texts.qualityBasedOn }}</span>
                <ul class="quality-breakdown-list">
                  <li :title="texts.breakdownSwapsHint"><strong>{{ headNurseData.quality_breakdown.swaps_this_month }}</strong> {{ texts.breakdownSwaps }}</li>
                  <li><strong>{{ headNurseData.quality_breakdown.min_nurses_violations }}</strong> {{ texts.breakdownMinNurses }}</li>
                  <li><strong>{{ headNurseData.quality_breakdown.preference_type_violations }}</strong> {{ texts.breakdownPreferenceType }}</li>
                  <li><strong>{{ headNurseData.quality_breakdown.preference_weekend_violations }}</strong> {{ texts.breakdownPreferenceWeekend }}</li>
                </ul>
              </div>
            </div>

            <!-- Taxa de aceitação de trocas — gauge circular preenchido proporcionalmente ao valor.
                 Sem bento-icon (ver nota no card de qualidade acima). -->
            <div class="bento-card bento-card--shifts acceptance-card">
              <h3>{{ texts.acceptanceRate }}</h3>
              <div class="acceptance-gauge">
                <svg viewBox="0 0 120 120" width="120" height="120">
                  <circle class="gauge-track" cx="60" cy="60" r="52" />
                  <circle
                    v-if="headNurseData.acceptance_rate !== null"
                    class="gauge-fill"
                    cx="60" cy="60" r="52"
                    :stroke-dasharray="ACCEPTANCE_GAUGE_CIRCUMFERENCE"
                    :stroke-dashoffset="acceptanceGaugeOffset"
                  />
                </svg>
                <div class="gauge-center">
                  <span v-if="headNurseData.acceptance_rate !== null" class="gauge-value">
                    {{ headNurseData.acceptance_rate.toLocaleString(currentLocale === 'pt' ? 'pt-PT' : 'en-US', { maximumFractionDigits: 1 }) }}%
                  </span>
                  <span v-else class="gauge-value gauge-value--empty">{{ texts.noData }}</span>
                </div>
              </div>
              <div class="acceptance-counts">
                <span class="acceptance-count acceptance-count--accepted">{{ headNurseData.swaps_accepted }} {{ texts.swapsAccepted }}</span>
                <span class="acceptance-count acceptance-count--rejected">{{ headNurseData.swaps_rejected }} {{ texts.swapsRejected }}</span>
              </div>
              <p>{{ texts.acceptanceRateDesc }}</p>
            </div>

            <!-- Tabela de horas por enfermeiro, ordenada por horas decrescentes (ordem vem da API).
                 Linhas destacadas por cor consoante o desvio face à média (calculada client-side).
                 Sem bento-icon (ver nota no card de qualidade acima). -->
            <div class="bento-card bento-card--hr hours-card">
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
                  <tr
                    v-for="entry in headNurseData.avg_hours_per_nurse"
                    :key="entry.user_id"
                  >
                    <td>{{ entry.name }}</td>
                    <td class="hours-col">{{ entry.hours }}h</td>
                  </tr>
                  <tr v-if="!headNurseData.avg_hours_per_nurse || headNurseData.avg_hours_per_nurse.length === 0">
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
/* Cards desta página são apenas informativos (não clicáveis) — anula o hover
   de elevação/scale herdado de .bento-card, que é pensado para cards de navegação */
.bento-card:hover {
  transform: none;
  box-shadow: none;
}

/* Ícone de pessoa no KPI de férias — único KPI com ícone, para reforçar visualmente que a
   contagem é de pessoas (ver nota acima sobre a convenção "sem ícone" nos restantes cards). */
.stats-kpi-value--with-icon {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.stats-kpi-icon {
  flex-shrink: 0;
  color: var(--primary-strong);
}

/* Segunda linha de KPIs do admin (estado do sistema) — espaço claro face à linha mensal acima */
.admin-kpi-row--system {
  margin-top: 24px;
}

/* Skeleton loader para os KPI cards enquanto aguardam dados */
.stats-loading {
  min-height: 160px;
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.4; }
}

/* Botão voltar + título + descrição — mesmo padrão visual/espaçamento de human-resources.vue / user-create.vue */
.back-link {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--primary-strong);
  font-weight: 700;
  font-size: 1.05rem;
  text-decoration: none;
  padding: 12px 24px;
  border-radius: 18px;
  background: rgba(125, 83, 213, 0.05);
  border: 1px solid rgba(125, 83, 213, 0.1);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  width: 160px;
}

.back-link:hover {
  background: rgba(125, 83, 213, 0.1);
  transform: translateX(-6px);
  box-shadow: 0 10px 20px rgba(125, 83, 213, 0.05);
}

.uc-title-group h1 {
  font-size: clamp(2.2rem, 5vw, 3rem);
  margin: 25px 0 10px;
  color: var(--text);
  letter-spacing: -0.02em;
}

.uc-subtitle {
  color: var(--muted);
  font-size: 1.2rem;
  margin: 0 0 40px;
}

/* Mês de referência, acima dos blocos de estatísticas — maior e centrado na página para
   funcionar como um subtítulo de destaque, não uma etiqueta discreta. */
.stats-month-label {
  font-size: 1.4rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
  text-align: center;
  margin: 0 0 20px;
}

/* Título centrado — diferente do padrão à esquerda dos restantes bento-card, para ficar
   alinhado com o breakdown, também centrado abaixo. */
.quality-card h3 {
  text-align: center;
}

/* Nota de rodapé discreta abaixo do título do quality card */
.quality-note {
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 8px;
  font-style: italic;
}

/* Quality breakdown — os 4 fatores do cálculo, por baixo do badge/barra. Cor secundária
   (var(--muted)) para não competir com o badge/score, que continuam a ser o elemento mais
   destacado do card, mas em tamanho de texto normal (0.9rem, próximo do texto corrido da
   página) — não uma legenda/caption minúscula — para ser confortavelmente legível. */
.quality-breakdown {
  margin-top: 14px;
  text-align: left;
}

.quality-breakdown-label {
  display: block;
  font-size: 0.85rem;
  color: var(--muted);
  font-weight: 700;
  margin-bottom: 6px;
}

/* Cada fator numa linha própria com um marcador (bullet), para leitura rápida em vez de
   texto corrido. */
.quality-breakdown-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  font-size: 0.9rem;
  color: var(--muted);
}

.quality-breakdown-list li {
  display: flex;
  align-items: baseline;
  gap: 8px;
  line-height: 1.4;
}

.quality-breakdown-list li::before {
  content: '';
  flex-shrink: 0;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--primary);
  transform: translateY(-2px);
}

.quality-breakdown-list strong {
  color: var(--text);
  font-weight: 700;
  display: inline-block;
  min-width: 1.4em;
  text-align: center;
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

/* !important necessário porque .hours-table th define text-align: left com maior
   especificidade (tag+classe) — sem isto o header "Horas" ficava alinhado à esquerda
   enquanto os valores da coluna ficavam à direita. */
.hours-col {
  text-align: right !important;
  font-weight: 600;
  color: var(--primary);
}

.hours-empty {
  text-align: center;
  color: var(--muted);
  padding: 16px 0;
}

/* Gauge circular da taxa de aceitação de trocas — space-evenly distribui o espaço restante
   entre o gauge e a descrição de forma equilibrada, mantendo o título fixo no topo (em vez de
   tudo colado ao título quando o card ganha mais altura por estar ao lado do card de
   qualidade, mais alto por ter badge + barra + breakdown). */
.acceptance-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-evenly;
  text-align: center;
}

.acceptance-gauge {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 8px 0;
}

.acceptance-gauge svg {
  transform: rotate(-90deg);
}

.gauge-track {
  fill: none;
  stroke: var(--line);
  stroke-width: 10;
}

.gauge-fill {
  fill: none;
  stroke: var(--primary);
  stroke-width: 10;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.4s ease;
}

.gauge-center {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Contagens de trocas aceites/rejeitadas por baixo do gauge — complementa a percentagem
   com os números absolutos que a compõem. */
.acceptance-counts {
  display: flex;
  gap: 16px;
  font-size: 0.9rem;
  font-weight: 600;
}

.acceptance-count--accepted {
  color: #4caf7d;
}

.acceptance-count--rejected {
  color: var(--danger);
}

.gauge-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--primary-strong);
}

.gauge-value--empty {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted);
}

</style>
