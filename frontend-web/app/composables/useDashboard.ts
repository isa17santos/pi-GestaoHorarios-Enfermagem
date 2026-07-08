// Response shapes for each role variant returned by GET /api/dashboard

type DashboardNurse = {
  role: 'nurse'
  monthly_hours: number
  shift_type_breakdown: Record<string, number>
  pending_swaps_count: number
  swaps_this_month: number
}

type DashboardHeadNurse = {
  role: 'head_nurse'
  team_pending_swaps_count: number
  team_swaps_this_month: number
}

type DashboardAdmin = {
  role: 'admin'
  nurses_count: number
  head_nurses_count: number
  medical_leaves_this_month: number
  vacations_this_month: number
}

type DashboardData = DashboardNurse | DashboardHeadNurse | DashboardAdmin

type DashboardResponse = {
  data: DashboardData
}

export const useDashboard = () => {
  const config = useRuntimeConfig()
  const { token } = useAuth()

  // ==================== STATE ====================

  // Shared state via useState to preserve reactivity across components.
  const dashboardData = useState<DashboardData | null>('dashboard.data', () => null)
  const loadingDashboard = useState<boolean>('dashboard.loading', () => false)
  const errorDashboard = useState<string | null>('dashboard.error', () => null)

  // ==================== FUNCTIONS ====================

  /**
   * Fetch dashboard summary from GET /api/dashboard and store the result.
   * The returned shape depends on the authenticated user's role.
   */
  const fetchDashboard = async () => {
    if (!token.value) return

    loadingDashboard.value = true
    errorDashboard.value = null

    try {
      const response = await $fetch<DashboardResponse>(
        `${config.public.apiBase}/dashboard`,
        {
          headers: {
            Authorization: `Bearer ${token.value}`,
          },
        }
      )

      dashboardData.value = response.data
      return response.data
    } catch (error) {
      const apiError = error as { data?: { message?: string }; message?: string }
      errorDashboard.value = apiError?.data?.message ?? apiError?.message ?? 'Erro ao carregar o painel.'
      console.error('Erro ao carregar dashboard:', error)
    } finally {
      loadingDashboard.value = false
    }
  }

  // Typed helpers to narrow dashboardData to a specific role shape.
  const nurseData = computed(() =>
    dashboardData.value?.role === 'nurse' ? (dashboardData.value as DashboardNurse) : null
  )

  const headNurseData = computed(() =>
    dashboardData.value?.role === 'head_nurse' ? (dashboardData.value as DashboardHeadNurse) : null
  )

  const adminData = computed(() =>
    dashboardData.value?.role === 'admin' ? (dashboardData.value as DashboardAdmin) : null
  )

  return {
    dashboardData,
    loadingDashboard,
    errorDashboard,
    nurseData,
    headNurseData,
    adminData,
    fetchDashboard,
  }
}
