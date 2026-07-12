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

// Limiar de desvio face à média de horas — enfermeiros fora deste intervalo são destacados na tabela
const HOURS_DEVIATION_THRESHOLD = 0.15

// Média de horas calculada client-side a partir dos dados já recebidos do backend
const avgHours = computed(() => {
  const entries = headNurseData.value?.avg_hours_per_nurse ?? []
  if (entries.length === 0) return 0
  return entries.reduce((sum, entry) => sum + entry.hours, 0) / entries.length
})

// Classifica cada enfermeiro como 'above' | 'below' | 'normal' consoante o desvio à média
const hoursWithDeviation = computed(() => {
  const entries = headNurseData.value?.avg_hours_per_nurse ?? []
  const avg = avgHours.value
  return entries.map((entry) => {
    let deviation: 'above' | 'below' | 'normal' = 'normal'
    if (avg > 0) {
      const diff = (entry.hours - avg) / avg
      if (diff > HOURS_DEVIATION_THRESHOLD) deviation = 'above'
      else if (diff < -HOURS_DEVIATION_THRESHOLD) deviation = 'below'
    }
    return { ...entry, deviation }
  })
})

// Gauge circular da taxa de aceitação — perímetro fixo (raio 52) para calcular o offset do traço
const ACCEPTANCE_GAUGE_CIRCUMFERENCE = 2 * Math.PI * 52
const acceptanceGaugeOffset = computed(() => {
  const rate = headNurseData.value?.acceptance_rate
  if (rate === null || rate === undefined) return ACCEPTANCE_GAUGE_CIRCUMFERENCE
  const clamped = Math.min(100, Math.max(0, rate))
  return ACCEPTANCE_GAUGE_CIRCUMFERENCE * (1 - clamped / 100)
})

// Posição (%) do marcador do quality_score na barra 0-100 — clamped por segurança,
// já que o score já vem limitado a [0,100] no backend.
const qualityScorePosition = computed(() => {
  const score = headNurseData.value?.quality_score
  if (score === null || score === undefined) return 0
  return Math.min(100, Math.max(0, score))
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
  nursesDesc:          currentLocale.value === 'pt' ? 'Utilizadores com role enfermeiro' : 'Users with nurse role',
  headNurses:          currentLocale.value === 'pt' ? 'Enfermeiros Chefes ativos' : 'Active head nurses',
  headNursesDesc:      currentLocale.value === 'pt' ? 'Utilizadores com role enfermeiro chefe' : 'Users with head nurse role',
  medicalLeaves:       currentLocale.value === 'pt' ? 'Baixas este mês' : 'Medical leaves this month',
  medicalLeavesDesc:   currentLocale.value === 'pt' ? 'Baixas com sobreposição no mês atual' : 'Leaves overlapping the current month',
  vacations:           currentLocale.value === 'pt' ? 'Férias este mês' : 'Vacations this month',
  vacationsDesc:       currentLocale.value === 'pt' ? 'Férias com sobreposição no mês atual' : 'Vacations overlapping the current month',
  inactiveUsers:       currentLocale.value === 'pt' ? 'Contas inativas' : 'Inactive accounts',
  inactiveUsersDesc:   currentLocale.value === 'pt' ? 'Utilizadores desativados no sistema' : 'Users deactivated in the system',
  pendingPasswordChange:     currentLocale.value === 'pt' ? 'Alteração de password pendente' : 'Pending password change',
  pendingPasswordChangeDesc: currentLocale.value === 'pt' ? 'Utilizadores que ainda não alteraram a password' : 'Users who have not changed their password yet',
  // Head nurse — quality indicator
  qualityLabel:        currentLocale.value === 'pt' ? 'Qualidade de Horário' : 'Schedule Quality',
  qualityBom:          currentLocale.value === 'pt' ? 'Bom' : 'Good',
  medioo:              currentLocale.value === 'pt' ? 'Médio' : 'Fair',
  mau:                 currentLocale.value === 'pt' ? 'Mau' : 'Poor',
  // Substitui a nota genérica "Indicador provisório" por algo específico sobre a calibração
  // atual dos thresholds do backend, para o head nurse perceber o alcance da ressalva.
  qualityProvisorio:   currentLocale.value === 'pt'
    ? 'Calibrado para equipas de referência (~15 enfermeiros); valores a confirmar com uso real'
    : 'Calibrated for reference teams (~15 nurses); values to be confirmed with real-world use',
  // Head nurse — quality breakdown (fatores usados no cálculo do quality_indicator).
  // Textos revistos para serem compreensíveis sem contexto do desenvolvimento — cada item
  // explica o que significa, não só o número cru.
  qualityBasedOn:      currentLocale.value === 'pt' ? 'Baseado em:' : 'Based on:',
  breakdownSwaps:      currentLocale.value === 'pt' ? 'trocas de turno este mês' : 'shift swaps this month',
  breakdownSwapsHint:  currentLocale.value === 'pt' ? 'Um número elevado de trocas pode indicar instabilidade no horário' : 'A high number of swaps may indicate schedule instability',
  breakdownMinNurses:  currentLocale.value === 'pt' ? 'turnos com falta de enfermeiros' : 'shifts understaffed',
  // Violações de preferências divididas em duas linhas, uma por unidade: type é contado por
  // enfermeiro (padrão sistemático no mês), weekend é contado por turno (ocorrência pontual).
  // Misturar as duas num só número dava contagens que podiam exceder o total de enfermeiros.
  breakdownPreferenceType:    currentLocale.value === 'pt' ? 'enfermeiros com preferências de turno não respeitadas' : 'nurses with unmet shift preferences',
  breakdownPreferenceWeekend: currentLocale.value === 'pt' ? 'turnos de fim de semana contra preferência' : 'weekend shifts against preference',
  // Head nurse — barra de quality_score (0-100)
  qualityScoreLabel:   currentLocale.value === 'pt' ? 'Pontuação' : 'Score',
  // Head nurse — acceptance rate
  acceptanceRate:      currentLocale.value === 'pt' ? 'Taxa de aceitação de trocas' : 'Swap acceptance rate',
  acceptanceRateDesc:  currentLocale.value === 'pt' ? 'Pedidos aceites sobre o total de respondidos este mês' : 'Accepted over total responded this month',
  noData:              currentLocale.value === 'pt' ? 'Sem dados' : 'No data',
  // Head nurse — hours table
  hoursTable:          currentLocale.value === 'pt' ? 'Horas por enfermeiro' : 'Hours per nurse',
  hoursTableDesc:      currentLocale.value === 'pt' ? 'Total de horas trabalhadas no mês atual' : 'Total hours worked this month',
  colName:             currentLocale.value === 'pt' ? 'Nome' : 'Name',
  colHours:            currentLocale.value === 'pt' ? 'Horas' : 'Hours',
  legendAbove:         currentLocale.value === 'pt' ? 'Acima da média' : 'Above average',
  legendBelow:         currentLocale.value === 'pt' ? 'Abaixo da média' : 'Below average',
  legendThreshold:     currentLocale.value === 'pt' ? 'Limiar' : 'Threshold',
  avgRowLabel:         currentLocale.value === 'pt' ? 'Média' : 'Average',
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
              <div class="stats-kpi-card">
                <span class="stats-kpi-value">{{ adminData.nurses_count }}</span>
                <span class="stats-kpi-label">{{ texts.nurses }}</span>
              </div>

              <div class="stats-kpi-card">
                <span class="stats-kpi-value">{{ adminData.head_nurses_count }}</span>
                <span class="stats-kpi-label">{{ texts.headNurses }}</span>
              </div>

              <div class="stats-kpi-card">
                <span class="stats-kpi-value">{{ adminData.medical_leaves_this_month }}</span>
                <span class="stats-kpi-label">{{ texts.medicalLeaves }}</span>
              </div>

              <div class="stats-kpi-card">
                <span class="stats-kpi-value">{{ adminData.vacations_this_month }}</span>
                <span class="stats-kpi-label">{{ texts.vacations }}</span>
              </div>
            </div>

            <!-- Segunda linha — estado atual do sistema, sem componente de mês -->
            <div class="stats-kpi-row admin-kpi-row--system">
              <!-- Contas inativas -->
              <div class="stats-kpi-card" :title="texts.inactiveUsersDesc">
                <span class="stats-kpi-value">{{ adminData.inactive_users_count }}</span>
                <span class="stats-kpi-label">{{ texts.inactiveUsers }}</span>
              </div>

              <!-- Alteração de password pendente -->
              <div class="stats-kpi-card" :title="texts.pendingPasswordChangeDesc">
                <span class="stats-kpi-value">{{ adminData.pending_password_change_count }}</span>
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
            <!-- Quality indicator — classificação em 3 níveis baseada em trocas + violações de mínimos.
                 Cor via style inline porque é determinada por dados da API (excepção permitida pela convenção).
                 Sem bento-icon: este estilo de ícone fica reservado para cards de navegação/ação,
                 não para blocos informativos (mesmo tratamento já aplicado ao bloco admin). -->
            <div class="bento-card bento-card--stats quality-card">
              <h3>{{ texts.qualityLabel }}</h3>
              <!-- Badge/pill com fundo suave na cor do nível — mais destaque que texto colorido
                   simples. O nível vem da API, por isso a cor é aplicada via classe dinâmica
                   quality-badge--<nível> (bom/medio/mau), definida em CSS abaixo. -->
              <p
                class="bento-stat-value quality-badge"
                :class="`quality-badge--${headNurseData.quality_indicator}`"
              >
                {{
                  headNurseData.quality_indicator === 'bom' ? texts.qualityBom
                  : headNurseData.quality_indicator === 'mau' ? texts.mau
                  : texts.medioo
                }}
              </p>
              <!-- Barra de quality_score (0-100) — complementa o badge com uma escala contínua.
                   3 zonas de fundo (mau/medio/bom) nas mesmas cores do badge, e um marcador
                   vertical na posição exata do score atual. -->
              <div class="quality-score-bar-wrapper">
                <div class="quality-score-bar">
                  <span class="quality-score-zone quality-score-zone--mau"></span>
                  <span class="quality-score-zone quality-score-zone--medio"></span>
                  <span class="quality-score-zone quality-score-zone--bom"></span>
                  <span class="quality-score-marker" :style="{ left: `${qualityScorePosition}%` }"></span>
                </div>
                <span class="quality-score-label">{{ texts.qualityScoreLabel }}: {{ headNurseData.quality_score }}/100</span>
              </div>

              <p class="quality-note">{{ texts.qualityProvisorio }}</p>

              <!-- Quality breakdown — os 4 fatores usados no cálculo do quality_score acima.
                   Informação de apoio (não é o elemento mais destacado do card, esse continua a
                   ser o badge + barra acima), mas em tamanho de texto normal da página — não uma
                   legenda/caption minúscula — para ser confortavelmente legível. Cada item numa
                   linha própria com um marcador (bullet), para leitura rápida em vez de texto
                   corrido. O item de trocas tem um tooltip (title) com contexto extra, para não
                   sobrecarregar o card com texto de apoio permanente. -->
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
                    v-for="entry in hoursWithDeviation"
                    :key="entry.user_id"
                    :class="{
                      'row-above': entry.deviation === 'above',
                      'row-below': entry.deviation === 'below',
                    }"
                  >
                    <td>{{ entry.name }}</td>
                    <td class="hours-col">{{ entry.hours }}h</td>
                  </tr>
                  <tr v-if="hoursWithDeviation.length === 0">
                    <td colspan="2" class="hours-empty">{{ texts.noData }}</td>
                  </tr>
                </tbody>
              </table>
              <!-- Legenda das cores de desvio -->
              <div v-if="hoursWithDeviation.length > 0" class="hours-legend">
                <span class="legend-item"><span class="legend-dot legend-dot--above"></span>{{ texts.legendAbove }}</span>
                <span class="legend-item"><span class="legend-dot legend-dot--below"></span>{{ texts.legendBelow }}</span>
                <span class="legend-item">{{ texts.legendThreshold }}: ±{{ (HOURS_DEVIATION_THRESHOLD * 100).toFixed(0) }}%</span>
                <span class="legend-average">{{ texts.avgRowLabel }}: {{ avgHours.toFixed(1) }}h</span>
              </div>
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

/* Título e badge de nível centrados — diferente do padrão à esquerda dos restantes
   bento-card, para ficar alinhado com a barra/breakdown, também centrados abaixo.
   .quality-badge é inline-block, por isso precisa de text-align: center no pai para centrar. */
.quality-card h3,
.quality-card .quality-badge {
  text-align: center;
}

/* Badge/pill do quality_indicator — fundo suave + texto na cor do nível, em vez do texto
   colorido simples anterior. Uma variante por nível ('bom' | 'medio' | 'mau'), aplicada via
   classe dinâmica no template. */
.quality-badge {
  display: inline-block;
  padding: 6px 20px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 1.1rem;
}

.quality-badge--bom {
  background: rgba(76, 175, 125, 0.14);
  color: #4caf7d;
}

.quality-badge--medio {
  background: rgba(255, 179, 128, 0.16);
  color: var(--peach);
}

.quality-badge--mau {
  background: var(--danger-background);
  color: var(--danger);
}

/* Barra de quality_score (0-100) — 3 zonas de fundo fixas nas proporções dos thresholds
   (0-39 mau, 40-69 medio, 70-100 bom) mais um marcador vertical na posição do score atual.
   Mesmas cores do badge (var(--danger) é o tom "Mau" usado em toda a app — é laranja, não
   vermelho puro, mas é o vermelho/tom de alerta oficial da paleta). Cor sólida em cada zona
   (sem opacity). O border-radius fica só no wrapper com overflow:hidden — assim as pontas
   arredondadas cortam sempre a zona que lá estiver (mau no início, bom no fim), em vez de
   depender de border-radius nos filhos, que não preenchia visualmente o canto. Uma borda fina
   entre zonas (box-shadow) marca a transição sem quebrar o preenchimento contínuo da barra. */
.quality-score-bar-wrapper {
  width: 100%;
  max-width: 220px;
  margin: 12px auto 0;
}

.quality-score-bar {
  position: relative;
  width: 100%;
  height: 10px;
  border-radius: 999px;
  overflow: hidden;
  display: flex;
  background: var(--line);
}

.quality-score-zone {
  height: 100%;
}

.quality-score-zone:not(:last-child) {
  box-shadow: 1px 0 0 var(--surface-strong);
}

/* Proporções alinhadas com QUALITY_SCORE_MEDIO_THRESHOLD (40) e
   QUALITY_SCORE_GOOD_THRESHOLD (70) do backend: 0-39% / 40-69% / 70-100%. */
.quality-score-zone--mau {
  width: 40%;
  background: var(--danger);
}

.quality-score-zone--medio {
  width: 30%;
  background: var(--peach);
}

.quality-score-zone--bom {
  width: 30%;
  background: #4caf7d;
}

.quality-score-marker {
  position: absolute;
  top: -3px;
  width: 3px;
  height: 16px;
  background: var(--text);
  border-radius: 2px;
  transform: translateX(-50%);
}

.quality-score-label {
  display: block;
  margin-top: 6px;
  text-align: center;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--muted);
}

/* Nota de rodapé discreta abaixo do quality indicator */
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

/* Destaque de linhas na tabela de horas consoante o desvio face à média */
.row-above {
  background: var(--danger-background);
}

.row-above td {
  color: var(--danger);
  font-weight: 600;
}

.row-below {
  background: rgba(172, 138, 241, 0.16);
}

.row-below td {
  color: var(--primary-strong);
  font-weight: 600;
}

/* Legenda de cores por baixo da tabela de horas */
.hours-legend {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 12px;
  font-size: 0.8rem;
  color: var(--muted);
}

.legend-average {
  margin-left: auto;
  font-weight: 600;
  color: var(--primary-strong);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.legend-dot--above {
  background: var(--danger);
}

.legend-dot--below {
  background: var(--primary-strong);
}
</style>
