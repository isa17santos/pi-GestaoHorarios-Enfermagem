// Shared month navigation state/logic, matching the calendar month nav used in swap-create.vue.

export const useMonthNavigation = () => {
  const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

  const now = new Date()
  const selectedYear = ref(now.getFullYear())
  const selectedMonth = ref(now.getMonth() + 1) // 1-12

  // Set by the caller from the API's `next_month_available` field on each fetch.
  const allowNextMonth = ref(false)

  const isCurrentMonth = computed(() => {
    const today = new Date()
    return selectedYear.value === today.getFullYear() && selectedMonth.value === today.getMonth() + 1
  })

  const canGoNext = computed(() => !isCurrentMonth.value || allowNextMonth.value)

  const goToPreviousMonth = () => {
    const prev = new Date(selectedYear.value, selectedMonth.value - 2, 1)
    selectedYear.value = prev.getFullYear()
    selectedMonth.value = prev.getMonth() + 1
  }

  const goToNextMonth = () => {
    if (!canGoNext.value) return

    const next = new Date(selectedYear.value, selectedMonth.value, 1)
    selectedYear.value = next.getFullYear()
    selectedMonth.value = next.getMonth() + 1
  }

  const monthLabel = computed(() => {
    const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
    const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(
      new Date(selectedYear.value, selectedMonth.value - 1, 1)
    )
    return label.replace(/(\S+)/g, (word, _m, offset) =>
      offset === 0 ? word : (/^\d/.test(word) ? word : word.toLowerCase())
    )
  })

  return {
    selectedYear,
    selectedMonth,
    allowNextMonth,
    canGoNext,
    monthLabel,
    goToPreviousMonth,
    goToNextMonth,
  }
}
