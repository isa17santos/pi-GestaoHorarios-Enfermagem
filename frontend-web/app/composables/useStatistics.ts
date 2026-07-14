// Response shapes for each role variant returned by GET /api/statistics

type StatisticsAdmin = {
  role: 'admin'
  month: number
  year: number
  next_month_available: boolean
  nurses_count: number
  head_nurses_count: number
  medical_leaves_this_month: number
  vacations_this_month: number
  inactive_users_count: number
  pending_password_change_count: number
}

type NurseHoursEntry = {
  user_id: number
  name: string
  hours: number
}

type QualityBreakdown = {
  swaps_this_month: number
  min_nurses_violations: number
  // Violações de preferências separadas por unidade: type é contado por enfermeiro (padrão
  // sistemático), weekend é contado por turno — mantê-las separadas evita números que
  // excedem o total de enfermeiros da equipa.
  preference_type_violations: number
  preference_weekend_violations: number
}

type StatisticsHeadNurse = {
  role: 'head_nurse'
  month: number
  year: number
  next_month_available: boolean
  acceptance_rate: number | null
  swaps_accepted: number
  swaps_rejected: number
  avg_hours_per_nurse: NurseHoursEntry[]
  quality_breakdown: QualityBreakdown
}

type StatisticsData = StatisticsAdmin | StatisticsHeadNurse

type StatisticsResponse = {
  data: StatisticsData
}

export const useStatistics = () => {
  const config = useRuntimeConfig()
  const { token } = useAuth()

  const statisticsData = useState<StatisticsData | null>('statistics.data', () => null)
  const loadingStatistics = useState<boolean>('statistics.loading', () => false)
  const errorStatistics = useState<string | null>('statistics.error', () => null)

  const fetchStatistics = async (year?: number, month?: number) => {
    if (!token.value) return

    loadingStatistics.value = true
    errorStatistics.value = null

    try {
      const response = await $fetch<StatisticsResponse>(
        `${config.public.apiBase}/statistics`,
        {
          headers: {
            Authorization: `Bearer ${token.value}`,
          },
          query: year && month ? { year, month } : undefined,
        }
      )

      statisticsData.value = response.data
      return response.data
    } catch (error) {
      const apiError = error as { data?: { message?: string }; message?: string }
      errorStatistics.value = apiError?.data?.message ?? apiError?.message ?? 'Erro ao carregar as estatísticas.'
      throw error
    } finally {
      loadingStatistics.value = false
    }
  }

  const adminData = computed(() =>
    statisticsData.value?.role === 'admin' ? (statisticsData.value as StatisticsAdmin) : null
  )

  const headNurseData = computed(() =>
    statisticsData.value?.role === 'head_nurse' ? (statisticsData.value as StatisticsHeadNurse) : null
  )

  return {
    statisticsData,
    loadingStatistics,
    errorStatistics,
    adminData,
    headNurseData,
    fetchStatistics,
  }
}
