<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

type ProfilePreference = {
  id?: number
  month: number
  year: number
  prefers_morning: boolean
  prefers_afternoon: boolean
  prefers_night: boolean
  avoid_weekends: boolean
  prefers_weekends: boolean
  notes: string
}

const config = useRuntimeConfig()
const { token, user, fetchMe } = useAuth()
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Estado base da página e feedback global de operações assíncronas
const loadingProfile = ref(true)
const loadingPreferences = ref(true)
const savingProfile = ref(false)
const savingPreferenceKey = ref<string | null>(null)
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)
const errors = ref<Record<string, string>>({})

// Modelo reativo do formulário de dados pessoais
const profileForm = ref({
  email: '',
})

const preferences = ref<ProfilePreference[]>([])
const expandedPreferenceKey = ref<string | null>(null)
const newPreference = ref<ProfilePreference | null>(null)
const preferenceSearchQuery = ref('')
const isMonthPickerOpen = ref(false)
const monthPickerRef = ref<HTMLElement | null>(null)
const visiblePickerYear = ref(new Date().getFullYear())
const newPreferenceMonth = ref('')

// Estado do picker de mês para os cartões de preferência existentes
// (partilhado porque só um cartão pode estar expandido de cada vez)
const isEditPickerOpen = ref(false)
const editPickerRef = ref<HTMLElement | null>(null)
const visibleEditPickerYear = ref(new Date().getFullYear())
const editPickerMonth = ref('') // YYYY-MM do cartão atualmente expandido

// Estado do modal de confirmação de eliminação de preferência
const showDeleteModal = ref(false)
const pendingDeletePreference = ref<ProfilePreference | null>(null)
const deletingPreferenceKey = ref<string | null>(null)

// Estado do modal de confirmação de substituição de preferência existente
const showOverwriteModal = ref(false)
const pendingOverwrite = ref<{ preference: ProfilePreference, month: number, year: number } | null>(null)

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

// Dicionário único de textos para internacionalização (PT/EN) da página
const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Perfil' : 'Profile',
  subtitle: currentLocale.value === 'pt' ? 'Atualize os seus dados pessoais e preferências mensais' : 'Update your personal data and monthly preferences',
  sectionPersonal: currentLocale.value === 'pt' ? 'Dados Pessoais' : 'Personal Data',
  sectionPreferences: currentLocale.value === 'pt' ? 'Preferências Mensais' : 'Monthly Preferences',
  labels: {
    name: currentLocale.value === 'pt' ? 'Nome Completo' : 'Full Name',
    email: currentLocale.value === 'pt' ? 'Email' : 'Email',
    month: currentLocale.value === 'pt' ? 'Mês' : 'Month',
    year: currentLocale.value === 'pt' ? 'Ano' : 'Year',
    notes: currentLocale.value === 'pt' ? 'Notas' : 'Notes',
  },
  placeholders: {
    name: currentLocale.value === 'pt' ? 'Ex: Maria Silva' : 'e.g. Mary Johnson',
    email: currentLocale.value === 'pt' ? 'maria.silva@example.pt' : 'mary.johnson@example.com',
    notes: currentLocale.value === 'pt' ? 'Notas sobre as suas preferências para este mês...' : 'Notes about your preferences for this month...',
  },
  toggles: {
    prefers_morning: currentLocale.value === 'pt' ? 'Prefere manhã' : 'Prefers morning',
    prefers_afternoon: currentLocale.value === 'pt' ? 'Prefere tarde' : 'Prefers afternoon',
    prefers_night: currentLocale.value === 'pt' ? 'Prefere noite' : 'Prefers night',
    avoid_weekends: currentLocale.value === 'pt' ? 'Evitar fins de semana' : 'Avoid weekends',
    prefers_weekends: currentLocale.value === 'pt' ? 'Prefere fins de semana' : 'Prefers weekends',
  },
  addPreferences: currentLocale.value === 'pt' ? 'Adicionar Preferências' : 'Add Preferences',
  searchPreferencesLabel: currentLocale.value === 'pt' ? 'Procurar Preferências' : 'Search Preferences',
  searchPreferencesPlaceholder: currentLocale.value === 'pt' ? 'Pesquisar por mês ou ano' : 'Search by month or year',
  changePassword: currentLocale.value === 'pt' ? 'Alterar Palavra-passe' : 'Change Password',
  saveProfile: currentLocale.value === 'pt' ? 'Guardar Dados Pessoais' : 'Save Personal Data',
  savingProfile: currentLocale.value === 'pt' ? 'A guardar...' : 'Saving...',
  savePreferences: currentLocale.value === 'pt' ? 'Guardar Preferências' : 'Save Preferences',
  savingPreferences: currentLocale.value === 'pt' ? 'A guardar...' : 'Saving...',
  noPreferences: currentLocale.value === 'pt' ? 'Ainda não existem preferências mensais. Clique em "Adicionar Preferências" para criar a primeira.' : 'There are no monthly preferences yet. Click "Add Preferences" to create the first one.',
  noSearchResults: currentLocale.value === 'pt' ? 'Sem resultados para a pesquisa atual.' : 'No results for the current search.',
  noChanges: currentLocale.value === 'pt' ? 'Não existem alterações para guardar' : 'No changes to save',
  profileSuccess: currentLocale.value === 'pt' ? 'Perfil atualizado com sucesso!' : 'Profile updated successfully!',
  profileError: currentLocale.value === 'pt' ? 'Erro ao atualizar perfil.' : 'Error updating profile.',
  preferencesSuccess: currentLocale.value === 'pt' ? 'Preferências guardadas com sucesso!' : 'Preferences saved successfully!',
  preferencesError: currentLocale.value === 'pt' ? 'Erro ao guardar preferências.' : 'Error saving preferences.',
  fetchPreferencesError: currentLocale.value === 'pt' ? 'Erro ao carregar preferências.' : 'Error loading preferences.',
  validation: {
    email: currentLocale.value === 'pt' ? 'O email é obrigatório' : 'Email is required',
    emailInvalid: currentLocale.value === 'pt' ? 'Introduza um email válido' : 'Enter a valid email address',
    month: currentLocale.value === 'pt' ? 'Selecione o mês' : 'Select a month',
    year: currentLocale.value === 'pt' ? 'Selecione o ano' : 'Select a year',
    formError: currentLocale.value === 'pt' ? 'Por favor, corrija os erros no formulário.' : 'Please fix the form errors.',
  },
  yes: currentLocale.value === 'pt' ? 'Sim' : 'Yes',
  no: currentLocale.value === 'pt' ? 'Não' : 'No',
  picker: {
    selectMonth: currentLocale.value === 'pt' ? 'Selecionar mês' : 'Select month',
    previousYear: currentLocale.value === 'pt' ? 'Ano anterior' : 'Previous year',
    nextYear: currentLocale.value === 'pt' ? 'Ano seguinte' : 'Next year',
  },
  // Textos para modais de confirmação de eliminação e substituição
  deletePreference: currentLocale.value === 'pt' ? 'Eliminar Preferência' : 'Delete Preference',
  deleteConfirm: currentLocale.value === 'pt' ? 'Tem a certeza que pretende eliminar esta preferência? Esta ação não pode ser revertida.' : 'Are you sure you want to delete this preference? This action cannot be undone.',
  overwriteTitle: currentLocale.value === 'pt' ? 'Preferência já existe' : 'Preference already exists',
  overwriteConfirm: currentLocale.value === 'pt' ? 'Já existe uma preferência para este mês. Pretende substituí-la pelos novos dados?' : 'A preference for this month already exists. Do you want to replace it with the new data?',
  overwrite: currentLocale.value === 'pt' ? 'Substituir' : 'Overwrite',
  cancel: currentLocale.value === 'pt' ? 'Cancelar' : 'Cancel',
  delete: currentLocale.value === 'pt' ? 'Eliminar' : 'Delete',
  deleting: currentLocale.value === 'pt' ? 'A eliminar...' : 'Deleting...',
  deleteSuccess: currentLocale.value === 'pt' ? 'Preferência eliminada com sucesso!' : 'Preference deleted successfully!',
  deleteError: currentLocale.value === 'pt' ? 'Erro ao eliminar preferência.' : 'Error deleting preference.',
}))

const currentMonthMin = computed(() => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
})

const minPickerYear = computed(() => Number(currentMonthMin.value.slice(0, 4)))

// Nomes curtos dos meses no locale atual, reutilizados nos dois pickers
const pickerMonthNames = computed(() => {
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'

  return Array.from({ length: 12 }, (_, index) => {
    const date = new Date(2026, index, 1)
    const label = new Intl.DateTimeFormat(locale, { month: 'short' }).format(date)
    return label.charAt(0).toUpperCase() + label.slice(1).replace('.', '')
  })
})

const pickerMonths = computed(() => {
  return Array.from({ length: 12 }, (_, index) => {
    const month = index + 1
    const value = `${visiblePickerYear.value}-${String(month).padStart(2, '0')}`
    return {
      value,
      label: pickerMonthNames.value[index] || String(month),
      disabled: value < currentMonthMin.value,
    }
  })
})

const selectedMonthLabel = computed(() => {
  if (!newPreferenceMonth.value) {
    return texts.value.picker.selectMonth
  }

  const selectedDate = new Date(`${newPreferenceMonth.value}-01`)
  if (Number.isNaN(selectedDate.getTime())) {
    return newPreferenceMonth.value
  }

  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(selectedDate)
  return label.charAt(0).toUpperCase() + label.slice(1)
})

const canGoToPreviousPickerYear = computed(() => visiblePickerYear.value > minPickerYear.value)

// Meses do picker de edição — idêntico ao do novo formulário mas usa o ano do picker de edição
const editPickerMonths = computed(() => {
  return Array.from({ length: 12 }, (_, index) => {
    const month = index + 1
    const value = `${visibleEditPickerYear.value}-${String(month).padStart(2, '0')}`
    return {
      value,
      label: pickerMonthNames.value[index] || String(month),
      // Desativa meses anteriores ao mês atual
      disabled: value < currentMonthMin.value,
    }
  })
})

// Label do mês selecionado no picker de edição, formatado para o idioma atual
const selectedEditMonthLabel = computed(() => {
  if (!editPickerMonth.value) {
    return texts.value.picker.selectMonth
  }
  const selectedDate = new Date(`${editPickerMonth.value}-01`)
  if (Number.isNaN(selectedDate.getTime())) {
    return editPickerMonth.value
  }
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  const label = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(selectedDate)
  return label.charAt(0).toUpperCase() + label.slice(1)
})

// Impede navegar para anos anteriores ao mínimo permitido no picker de edição
const canGoToPreviousEditPickerYear = computed(() => visibleEditPickerYear.value > minPickerYear.value)

const monthOptions = computed(() => {
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'

  return Array.from({ length: 12 }, (_, idx) => {
    const month = idx + 1
    const date = new Date(2026, idx, 1)
    const label = new Intl.DateTimeFormat(locale, { month: 'long' }).format(date)
    return {
      value: month,
      label: label.charAt(0).toUpperCase() + label.slice(1),
    }
  })
})

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 6 }, (_, idx) => currentYear - 1 + idx)
})

// Normaliza texto para pesquisa case-insensitive e sem dependência de acentos.
const normalizeSearchValue = (value: string) => {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

// Filtra preferências por nome do mês (curto/longo no idioma atual) ou ano digitado.
const filteredPreferences = computed(() => {
  const query = normalizeSearchValue(preferenceSearchQuery.value)

  if (!query) {
    return preferences.value
  }

  return preferences.value.filter((preference) => {
    const monthIndex = Math.max(0, preference.month - 1)
    const shortMonthLabel = pickerMonthNames.value[monthIndex] || ''
    const longMonthLabel = monthOptions.value.find(option => option.value === preference.month)?.label || ''
    const searchableText = `${shortMonthLabel} ${longMonthLabel} ${preference.year}`
    return normalizeSearchValue(searchableText).includes(query)
  })
})

// Normaliza respostas da API para garantir estrutura e tipos previsíveis no formulário
const normalizePreference = (item: any): ProfilePreference => ({
  id: typeof item?.id === 'number' ? item.id : undefined,
  month: Number(item?.month) || new Date().getMonth() + 1,
  year: Number(item?.year) || new Date().getFullYear(),
  prefers_morning: Boolean(item?.prefers_morning),
  prefers_afternoon: Boolean(item?.prefers_afternoon),
  prefers_night: Boolean(item?.prefers_night),
  avoid_weekends: Boolean(item?.avoid_weekends),
  prefers_weekends: Boolean(item?.prefers_weekends),
  notes: typeof item?.notes === 'string' ? item.notes : '',
})

const getPreferenceKey = (preference: ProfilePreference) => {
  return preference.id ? `id-${preference.id}` : `month-${preference.month}-year-${preference.year}`
}

const formatMonthYear = (month: number, year: number) => {
  const locale = currentLocale.value === 'pt' ? 'pt-PT' : 'en-US'
  const date = new Date(year, Math.max(0, month - 1), 1)
  const value = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(date)
  return value.charAt(0).toUpperCase() + value.slice(1)
}

const isExpanded = (preference: ProfilePreference) => {
  return expandedPreferenceKey.value === getPreferenceKey(preference)
}

const toggleExpandPreference = (preference: ProfilePreference) => {
  const key = getPreferenceKey(preference)
  if (expandedPreferenceKey.value === key) {
    // Fecha o cartão e garante que o picker de edição também fecha
    expandedPreferenceKey.value = null
    closeEditMonthPicker()
  } else {
    // Abre o cartão e inicializa o picker de edição com o mês/ano da preferência
    expandedPreferenceKey.value = key
    editPickerMonth.value = `${preference.year}-${String(preference.month).padStart(2, '0')}`
    visibleEditPickerYear.value = preference.year
    isEditPickerOpen.value = false
  }
}

const openNewPreferenceForm = () => {
  const now = new Date()
  newPreference.value = {
    month: now.getMonth() + 1,
    year: now.getFullYear(),
    prefers_morning: false,
    prefers_afternoon: false,
    prefers_night: false,
    avoid_weekends: false,
    prefers_weekends: false,
    notes: '',
  }

  newPreferenceMonth.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  visiblePickerYear.value = now.getFullYear()
  isMonthPickerOpen.value = false
  expandedPreferenceKey.value = 'new'
}

// Fecha o formulário de nova preferência e limpa os campos base de criação
const cancelNewPreferenceForm = () => {
  newPreference.value = null
  newPreferenceMonth.value = ''
}

const openMonthPicker = () => {
  isMonthPickerOpen.value = true

  if (newPreferenceMonth.value) {
    const selectedYear = Number(newPreferenceMonth.value.slice(0, 4))
    if (Number.isFinite(selectedYear)) {
      visiblePickerYear.value = selectedYear
      return
    }
  }

  visiblePickerYear.value = minPickerYear.value
}

const closeMonthPicker = () => {
  isMonthPickerOpen.value = false
}

const toggleMonthPicker = () => {
  if (isMonthPickerOpen.value) {
    closeMonthPicker()
    return
  }

  openMonthPicker()
}

const selectMonthFromPicker = (monthValue: string) => {
  if (!newPreference.value) return
  if (monthValue < currentMonthMin.value) return

  newPreferenceMonth.value = monthValue
  const [yearRaw, monthRaw] = monthValue.split('-')
  const parsedYear = Number(yearRaw)
  const parsedMonth = Number(monthRaw)

  if (Number.isFinite(parsedYear) && Number.isFinite(parsedMonth)) {
    newPreference.value.year = parsedYear
    newPreference.value.month = parsedMonth
  }

  closeMonthPicker()
}

const goToPreviousPickerYear = () => {
  if (!canGoToPreviousPickerYear.value) return
  visiblePickerYear.value -= 1
}

const goToNextPickerYear = () => {
  visiblePickerYear.value += 1
}

const handleMonthPickerOutsideClick = (event: MouseEvent) => {
  if (!isMonthPickerOpen.value) return

  const target = event.target as Node | null
  if (!target) return

  if (monthPickerRef.value?.contains(target)) return
  closeMonthPicker()
}

// Abre o picker de edição e sincroniza o ano visível com o mês já selecionado
const openEditMonthPicker = () => {
  isEditPickerOpen.value = true

  if (editPickerMonth.value) {
    const selectedYear = Number(editPickerMonth.value.slice(0, 4))
    if (Number.isFinite(selectedYear)) {
      visibleEditPickerYear.value = selectedYear
      return
    }
  }

  visibleEditPickerYear.value = minPickerYear.value
}

// Fecha o picker de edição
const closeEditMonthPicker = () => {
  isEditPickerOpen.value = false
}

// Alterna o picker de edição entre aberto e fechado
const toggleEditMonthPicker = () => {
  if (isEditPickerOpen.value) {
    closeEditMonthPicker()
    return
  }

  openEditMonthPicker()
}

// Seleciona um mês no picker de edição e atualiza os campos da preferência
const selectEditMonthFromPicker = (monthValue: string, preference: ProfilePreference) => {
  // Impede a seleção de meses no passado
  if (monthValue < currentMonthMin.value) return

  editPickerMonth.value = monthValue

  const [yearRaw, monthRaw] = monthValue.split('-')
  const parsedYear = Number(yearRaw)
  const parsedMonth = Number(monthRaw)

  if (Number.isFinite(parsedYear) && Number.isFinite(parsedMonth)) {
    preference.year = parsedYear
    preference.month = parsedMonth
  }

  closeEditMonthPicker()
}

// Recua um ano na navegação do picker de edição
const goToPreviousEditPickerYear = () => {
  if (!canGoToPreviousEditPickerYear.value) return
  visibleEditPickerYear.value -= 1
}

// Avança um ano na navegação do picker de edição
const goToNextEditPickerYear = () => {
  visibleEditPickerYear.value += 1
}

// Regista o elemento do picker de edição via ref funcional (usado em v-for)
const setEditPickerRef = (el: unknown, preference: ProfilePreference) => {
  if (isExpanded(preference)) {
    editPickerRef.value = el instanceof HTMLElement ? el : null
  }
}

// Fecha o picker de edição ao clicar fora do seu elemento
const handleEditPickerOutsideClick = (event: MouseEvent) => {
  if (!isEditPickerOpen.value) return

  const target = event.target as Node | null
  if (!target) return

  if (editPickerRef.value?.contains(target)) return
  closeEditMonthPicker()
}

const hydrateProfileFormFromUser = () => {
  // O nome é apenas informativo nesta página; apenas o email é editável
  profileForm.value.email = user.value?.email || ''
}

const fetchPreferences = async () => {
  loadingPreferences.value = true

  try {
    const response = await $fetch<any>(`${config.public.apiBase}/profile/preferences`, {
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
    })

    const rawItems = Array.isArray(response)
      ? response
      : Array.isArray(response?.data)
        ? response.data
        : []

    const now = new Date()
    const currentYear = now.getFullYear()
    const currentMonth = now.getMonth() + 1

    preferences.value = rawItems
      .map((item: any) => normalizePreference(item))
      .filter((preference: ProfilePreference) => {
        if (preference.year > currentYear) return true
        if (preference.year < currentYear) return false
        return preference.month >= currentMonth
      })
  } catch (err) {
    console.error('Fetch preferences error:', err)
    showNotification('fetchPreferencesError', 'error')
  } finally {
    loadingPreferences.value = false
  }
}

const validateProfileForm = () => {
  errors.value = {}

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

  if (!profileForm.value.email.trim()) {
    errors.value.email = 'email'
  } else if (!emailRegex.test(profileForm.value.email)) {
    errors.value.email = 'emailInvalid'
  }

  return Object.keys(errors.value).length === 0
}

// Garante exclusão mútua entre preferências de fim de semana.
// Se uma opção ficar ativa, a opção oposta é desativada automaticamente.
const enforceWeekendPreferenceExclusivity = (
  preference: ProfilePreference,
  changedToggle: 'avoid_weekends' | 'prefers_weekends',
) => {
  if (changedToggle === 'avoid_weekends' && preference.avoid_weekends) {
    preference.prefers_weekends = false
  }

  if (changedToggle === 'prefers_weekends' && preference.prefers_weekends) {
    preference.avoid_weekends = false
  }
}

const saveProfile = async () => {
  if (!validateProfileForm()) {
    showNotification('formError', 'error')
    return
  }

  // Evita chamada à API desnecessária quando o email não foi alterado
  if (profileForm.value.email === user.value?.email) {
    showNotification('noChanges', 'error')
    return
  }

  savingProfile.value = true

  try {
    // Atualização de perfil simplificada: apenas o email pode ser alterado aqui
    await $fetch(`${config.public.apiBase}/profile`, {
      method: 'PATCH',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
      body: {
        email: profileForm.value.email,
      },
    })

    await fetchMe().catch(() => null)
    hydrateProfileFormFromUser()
    showNotification('profileSuccess', 'success')
  } catch (err: any) {
    console.error('Update profile error:', err)
    showNotification('profileError', 'error')
  } finally {
    savingProfile.value = false
  }
}

// Envia a preferência para a API e atualiza a lista após guardar com sucesso
const dispatchSavePreference = async (preference: ProfilePreference, key: string, payloadMonth: number, payloadYear: number) => {
  savingPreferenceKey.value = key

  try {
    await $fetch(`${config.public.apiBase}/profile/preferences`, {
      method: 'PATCH',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
      body: {
        month: payloadMonth,
        year: payloadYear,
        prefers_morning: preference.prefers_morning,
        prefers_afternoon: preference.prefers_afternoon,
        prefers_night: preference.prefers_night,
        avoid_weekends: preference.avoid_weekends,
        prefers_weekends: preference.prefers_weekends,
        notes: preference.notes || null,
      },
    })

    showNotification('preferencesSuccess', 'success')

    await fetchPreferences()

    // Colapsa sempre o cartão expandido após guardar, independentemente do tipo de chave
    expandedPreferenceKey.value = null

    // Limpa o estado do formulário de nova preferência após guardar
    if (key === 'new') {
      newPreference.value = null
      newPreferenceMonth.value = ''
      closeMonthPicker()
    }
  } catch (err) {
    console.error('Update preferences error:', err)
    showNotification('preferencesError', 'error')
  } finally {
    savingPreferenceKey.value = null
  }
}

// Valida os dados e guarda a preferência — para nova preferência verifica duplicados primeiro
const savePreference = async (preference: ProfilePreference, key: string) => {
  let payloadMonth = preference.month
  let payloadYear = preference.year

  if (key === 'new') {
    // Valida que o utilizador selecionou um mês no picker
    if (!newPreferenceMonth.value) {
      errors.value['new-month'] = 'month'
      showNotification('formError', 'error')
      return
    }

    const [yearRaw, monthRaw] = newPreferenceMonth.value.split('-')
    payloadYear = Number(yearRaw)
    payloadMonth = Number(monthRaw)

    if (!Number.isFinite(payloadMonth) || !Number.isFinite(payloadYear)) {
      errors.value['new-month'] = 'month'
      showNotification('formError', 'error')
      return
    }

    delete errors.value['new-month']

    // Verifica se já existe uma preferência para o mesmo mês/ano
    const duplicate = preferences.value.find(p => p.month === payloadMonth && p.year === payloadYear)
    if (duplicate) {
      // Guarda os dados pendentes e abre o modal de confirmação de substituição
      pendingOverwrite.value = { preference: { ...preference }, month: payloadMonth, year: payloadYear }
      showOverwriteModal.value = true
      return
    }
  }

  if (!preference.month) {
    errors.value[`${key}-month`] = 'month'
  } else {
    delete errors.value[`${key}-month`]
  }

  if (!preference.year) {
    errors.value[`${key}-year`] = 'year'
  } else {
    delete errors.value[`${key}-year`]
  }

  if (errors.value[`${key}-month`] || errors.value[`${key}-year`]) {
    showNotification('formError', 'error')
    return
  }

  await dispatchSavePreference(preference, key, payloadMonth, payloadYear)
}

// Cancela o modal de substituição sem guardar
const cancelOverwrite = () => {
  showOverwriteModal.value = false
  pendingOverwrite.value = null
}

// Confirma a substituição da preferência existente e prossegue com o envio
const confirmOverwrite = async () => {
  showOverwriteModal.value = false
  if (!pendingOverwrite.value) return

  const { preference, month, year } = pendingOverwrite.value
  pendingOverwrite.value = null
  await dispatchSavePreference(preference, 'new', month, year)
}

// Abre o modal de confirmação antes de eliminar a preferência
const requestDeletePreference = (preference: ProfilePreference) => {
  pendingDeletePreference.value = preference
  showDeleteModal.value = true
}

// Cancela o modal de eliminação sem apagar
const cancelDeletePreference = () => {
  showDeleteModal.value = false
  pendingDeletePreference.value = null
}

// Executa a eliminação da preferência após confirmação no modal
const executeDeletePreference = async () => {
  const preference = pendingDeletePreference.value
  if (!preference) return

  const key = getPreferenceKey(preference)
  deletingPreferenceKey.value = key

  try {
    // A eliminação passa sempre pelo endpoint dedicado com ID da preferência
    await $fetch(`${config.public.apiBase}/profile/preferences/${preference.id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
    })

    showDeleteModal.value = false
    pendingDeletePreference.value = null

    // Fecha o cartão se estava expandido
    if (expandedPreferenceKey.value === key) {
      expandedPreferenceKey.value = null
    }

    await fetchPreferences()
    showNotification('deleteSuccess', 'success')
  } catch (err) {
    console.error('Delete preference error:', err)
    showNotification('deleteError', 'error')
  } finally {
    deletingPreferenceKey.value = null
  }
}

onMounted(async () => {
  if (process.client) {
    // Regista os handlers de clique fora para os dois pickers de mês
    window.addEventListener('mousedown', handleMonthPickerOutsideClick)
    window.addEventListener('mousedown', handleEditPickerOutsideClick)
  }

  if (!user.value) {
    await fetchMe().catch(() => null)
  }

  hydrateProfileFormFromUser()
  loadingProfile.value = false

  await fetchPreferences()
})

onBeforeUnmount(() => {
  if (process.client) {
    window.removeEventListener('mousedown', handleMonthPickerOutsideClick)
    window.removeEventListener('mousedown', handleEditPickerOutsideClick)
  }
})
</script>

<template>
  <main class="dashboard-layout profile-page">
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
          <span>{{ (texts as any)[statusMessage.key] || (texts.validation as any)[statusMessage.key] }}</span>
        </div>
      </div>
    </transition>

    <div class="profile-top-bar">
      <div class="profile-title-group">
        <h1>{{ texts.title }}</h1>
        <p class="profile-subtitle">{{ texts.subtitle }}</p>
      </div>
    </div>

    <section class="profile-card">
      <div class="profile-card-head">
        <h2>{{ texts.sectionPersonal }}</h2>
      </div>

      <div v-if="loadingProfile" class="loading-state">
        <div class="spinner"></div>
      </div>

      <form v-else class="profile-form" @submit.prevent="saveProfile" novalidate>
        <div class="profile-grid">
          <!-- Nome apenas de leitura; edição de perfil limitada ao email -->
          <div class="form-group profile-readonly-name">
            <label>{{ texts.labels.name }}</label>
            <strong>{{ user?.name || '-' }}</strong>
          </div>

          <div class="form-group">
            <label>{{ texts.labels.email }}</label>
            <input
              v-model="profileForm.email"
              type="email"
              :placeholder="texts.placeholders.email"
              class="uc-input"
              :class="{ 'input-error': errors.email }"
            />
            <transition name="fade">
              <span v-if="errors.email" class="field-error">{{ (texts.validation as any)[errors.email] }}</span>
            </transition>
          </div>
        </div>

        <!-- A alteração de palavra-passe segue o fluxo de recuperação (envio de email + token) -->
        <!-- Ações de dados pessoais: métricas alinhadas ao botão "Adicionar Preferências" -->
        <div class="profile-actions profile-actions--personal">
          <NuxtLink to="/forgot-password" class="secondary-btn">
            {{ texts.changePassword }}
          </NuxtLink>
          <button type="submit" class="submit-btn" :class="{ loading: savingProfile }" :disabled="savingProfile">
            {{ savingProfile ? texts.savingProfile : texts.saveProfile }}
          </button>
        </div>
      </form>
    </section>

    <section class="profile-card preferences-card">
      <!-- Cabeçalho da secção de preferências: título + botão na linha de cima, pesquisa em baixo -->
      <div class="preferences-card-head">
        <!-- Linha do título: "Preferências Mensais" à esquerda, "Adicionar Preferências" à direita -->
        <div class="profile-card-head profile-card-head-split">
          <h2>{{ texts.sectionPreferences }}</h2>
          <button type="button" class="secondary-btn" @click="openNewPreferenceForm">
            {{ texts.addPreferences }}
          </button>
        </div>

        <!-- Pesquisa abaixo do título, a largura total — idêntico ao padrão de shift-types.vue -->
        <div class="hr-search-wrapper">
          <div class="search-input-container">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input
              v-model="preferenceSearchQuery"
              type="text"
              :placeholder="texts.searchPreferencesPlaceholder"
              class="search-input"
            />
          </div>
        </div>
      </div>

      <div v-if="loadingPreferences" class="loading-state">
        <div class="spinner"></div>
      </div>

      <div v-else class="preferences-list">
        <article
          v-for="preference in filteredPreferences"
          :key="getPreferenceKey(preference)"
          class="preference-item"
          :class="{ expanded: isExpanded(preference) }"
        >
          <!-- Cabeçalho do cartão: botão de toggle à esquerda e botão de eliminação à direita -->
          <div class="preference-header-wrap">
            <button type="button" class="preference-header" @click="toggleExpandPreference(preference)">
              <div>
                <h3>{{ formatMonthYear(preference.month, preference.year) }}</h3>
                <p>{{ texts.sectionPreferences }}</p>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18" :class="{ rotate: isExpanded(preference) }">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
            <!-- Botão de lixo — abre o modal de confirmação antes de apagar -->
            <button
              type="button"
              class="preference-delete-btn"
              :title="texts.deletePreference"
              :aria-label="texts.deletePreference"
              :disabled="deletingPreferenceKey === getPreferenceKey(preference)"
              @click.stop="requestDeletePreference(preference)"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                <path d="M10 11v6"></path>
                <path d="M14 11v6"></path>
                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
              </svg>
            </button>
          </div>

          <!-- Pré-visualização colapsada: pills visuais activas/inactivas + notas truncadas -->
          <div v-if="!isExpanded(preference)" class="preference-preview">
            <div class="preview-grid">
              <!-- Pill activa tem fundo primário; inactiva tem aparência discreta -->
              <span :class="['preview-pill', preference.prefers_morning ? 'preview-pill--active' : 'preview-pill--inactive']">{{ texts.toggles.prefers_morning }}{{ preference.prefers_morning ? ' ✓' : '' }}</span>
              <span :class="['preview-pill', preference.prefers_afternoon ? 'preview-pill--active' : 'preview-pill--inactive']">{{ texts.toggles.prefers_afternoon }}{{ preference.prefers_afternoon ? ' ✓' : '' }}</span>
              <span :class="['preview-pill', preference.prefers_night ? 'preview-pill--active' : 'preview-pill--inactive']">{{ texts.toggles.prefers_night }}{{ preference.prefers_night ? ' ✓' : '' }}</span>
              <span :class="['preview-pill', preference.avoid_weekends ? 'preview-pill--active' : 'preview-pill--inactive']">{{ texts.toggles.avoid_weekends }}{{ preference.avoid_weekends ? ' ✓' : '' }}</span>
              <span :class="['preview-pill', preference.prefers_weekends ? 'preview-pill--active' : 'preview-pill--inactive']">{{ texts.toggles.prefers_weekends }}{{ preference.prefers_weekends ? ' ✓' : '' }}</span>
            </div>
            <!-- Notas só são mostradas se não estiverem vazias, truncadas a 1 linha -->
            <p v-if="preference.notes" class="preview-notes">{{ preference.notes }}</p>
          </div>

          <div v-if="isExpanded(preference)" class="preference-body">
            <!-- Picker personalizado de mês/ano para o cartão de edição -->
            <div class="preference-grid">
              <div class="form-group">
                <label>{{ texts.labels.month }}</label>
                <!-- O ref é atribuído via função para apontar sempre para o picker visível -->
                <div
                  :ref="(el) => setEditPickerRef(el, preference)"
                  class="schedule-month-picker"
                >
                  <button
                    type="button"
                    class="schedule-month-picker__trigger"
                    :aria-expanded="isEditPickerOpen"
                    :aria-label="texts.labels.month"
                    @click="toggleEditMonthPicker"
                  >
                    <span>{{ selectedEditMonthLabel }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true">
                      <path d="M6 9l6 6 6-6" />
                    </svg>
                  </button>

                  <div v-if="isEditPickerOpen" class="schedule-month-picker__panel" role="dialog" :aria-label="texts.labels.month">
                    <div class="schedule-month-picker__header">
                      <button
                        type="button"
                        class="schedule-secondary-button"
                        :disabled="!canGoToPreviousEditPickerYear"
                        @click="goToPreviousEditPickerYear"
                      >
                        {{ texts.picker.previousYear }}
                      </button>

                      <strong>{{ visibleEditPickerYear }}</strong>

                      <button
                        type="button"
                        class="schedule-secondary-button"
                        @click="goToNextEditPickerYear"
                      >
                        {{ texts.picker.nextYear }}
                      </button>
                    </div>

                    <div class="schedule-month-picker__months">
                      <button
                        v-for="monthOption in editPickerMonths"
                        :key="monthOption.value"
                        type="button"
                        class="schedule-month-picker__month"
                        :class="{ 'is-selected': editPickerMonth === monthOption.value }"
                        :disabled="monthOption.disabled"
                        @click="selectEditMonthFromPicker(monthOption.value, preference)"
                      >
                        {{ monthOption.label }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="switch-grid">
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_morning }}</span>
                <input v-model="preference.prefers_morning" type="checkbox" class="switch-input" />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_afternoon }}</span>
                <input v-model="preference.prefers_afternoon" type="checkbox" class="switch-input" />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_night }}</span>
                <input v-model="preference.prefers_night" type="checkbox" class="switch-input" />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.avoid_weekends }}</span>
                <input
                  v-model="preference.avoid_weekends"
                  type="checkbox"
                  class="switch-input"
                  @change="enforceWeekendPreferenceExclusivity(preference, 'avoid_weekends')"
                />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_weekends }}</span>
                <input
                  v-model="preference.prefers_weekends"
                  type="checkbox"
                  class="switch-input"
                  @change="enforceWeekendPreferenceExclusivity(preference, 'prefers_weekends')"
                />
              </label>
            </div>

            <div class="form-group">
              <label>{{ texts.labels.notes }}</label>
              <textarea v-model="preference.notes" class="notes-textarea" :placeholder="texts.placeholders.notes"></textarea>
            </div>

            <div class="profile-actions">
              <button
                type="button"
                class="submit-btn"
                :class="{ loading: savingPreferenceKey === getPreferenceKey(preference) }"
                :disabled="savingPreferenceKey === getPreferenceKey(preference)"
                @click="savePreference(preference, getPreferenceKey(preference))"
              >
                {{ savingPreferenceKey === getPreferenceKey(preference) ? texts.savingPreferences : texts.savePreferences }}
              </button>
            </div>
          </div>
        </article>

        <article v-if="newPreference" class="preference-item expanded">
          <div class="preference-header-wrap">
            <div class="preference-header static">
              <div>
                <h3>{{ selectedMonthLabel }}</h3>
                <p>{{ texts.addPreferences }}</p>
              </div>
            </div>
            <!-- Botão X para cancelar a criação da nova preferência -->
            <button
              type="button"
              class="preference-delete-btn"
              :title="texts.cancel"
              :aria-label="texts.cancel"
              @click="cancelNewPreferenceForm"
            >
              <span class="preference-close-mark" aria-hidden="true">×</span>
            </button>
          </div>

          <div class="preference-body">
            <div class="preference-grid">
              <div class="form-group">
                <label>{{ texts.labels.month }}</label>
                <div ref="monthPickerRef" class="schedule-month-picker">
                  <button
                    type="button"
                    class="schedule-month-picker__trigger"
                    :class="{ 'input-error': errors['new-month'] }"
                    :aria-expanded="isMonthPickerOpen"
                    :aria-label="texts.labels.month"
                    @click="toggleMonthPicker"
                  >
                    <span>{{ selectedMonthLabel }}</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true">
                      <path d="M6 9l6 6 6-6" />
                    </svg>
                  </button>

                  <div v-if="isMonthPickerOpen" class="schedule-month-picker__panel" role="dialog" :aria-label="texts.labels.month">
                    <div class="schedule-month-picker__header">
                      <button
                        type="button"
                        class="schedule-secondary-button"
                        :disabled="!canGoToPreviousPickerYear"
                        @click="goToPreviousPickerYear"
                      >
                        {{ texts.picker.previousYear }}
                      </button>

                      <strong>{{ visiblePickerYear }}</strong>

                      <button
                        type="button"
                        class="schedule-secondary-button"
                        @click="goToNextPickerYear"
                      >
                        {{ texts.picker.nextYear }}
                      </button>
                    </div>

                    <div class="schedule-month-picker__months">
                      <button
                        v-for="monthOption in pickerMonths"
                        :key="monthOption.value"
                        type="button"
                        class="schedule-month-picker__month"
                        :class="{ 'is-selected': newPreferenceMonth === monthOption.value }"
                        :disabled="monthOption.disabled"
                        @click="selectMonthFromPicker(monthOption.value)"
                      >
                        {{ monthOption.label }}
                      </button>
                    </div>
                  </div>
                </div>

                <transition name="fade">
                  <span v-if="errors['new-month']" class="field-error">{{ (texts.validation as any)[errors['new-month']] }}</span>
                </transition>
              </div>
            </div>

            <div class="switch-grid">
              <!-- Os toggles de fim de semana partilham a mesma regra de exclusão mútua -->
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_morning }}</span>
                <input v-model="newPreference.prefers_morning" type="checkbox" class="switch-input" />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_afternoon }}</span>
                <input v-model="newPreference.prefers_afternoon" type="checkbox" class="switch-input" />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_night }}</span>
                <input v-model="newPreference.prefers_night" type="checkbox" class="switch-input" />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.avoid_weekends }}</span>
                <input
                  v-model="newPreference.avoid_weekends"
                  type="checkbox"
                  class="switch-input"
                  @change="enforceWeekendPreferenceExclusivity(newPreference, 'avoid_weekends')"
                />
              </label>
              <label class="switch-row">
                <span>{{ texts.toggles.prefers_weekends }}</span>
                <input
                  v-model="newPreference.prefers_weekends"
                  type="checkbox"
                  class="switch-input"
                  @change="enforceWeekendPreferenceExclusivity(newPreference, 'prefers_weekends')"
                />
              </label>
            </div>

            <div class="form-group">
              <label>{{ texts.labels.notes }}</label>
              <textarea v-model="newPreference.notes" class="notes-textarea" :placeholder="texts.placeholders.notes"></textarea>
            </div>

            <div class="profile-actions">
              <button
                type="button"
                class="submit-btn"
                :class="{ loading: savingPreferenceKey === 'new' }"
                :disabled="savingPreferenceKey === 'new'"
                @click="savePreference(newPreference, 'new')"
              >
                {{ savingPreferenceKey === 'new' ? texts.savingPreferences : texts.savePreferences }}
              </button>
            </div>
          </div>
        </article>

        <div v-if="filteredPreferences.length === 0 && !newPreference" class="preferences-empty">
          {{ preferenceSearchQuery.trim() ? texts.noSearchResults : texts.noPreferences }}
        </div>
      </div>
    </section>

    <!-- Modal de confirmação de eliminação de preferência -->
    <div
      v-if="showDeleteModal"
      class="schedule-confirm-overlay"
      role="presentation"
      @click.self="cancelDeletePreference"
    >
      <div class="schedule-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="delete-preference-title">
        <h3 id="delete-preference-title">{{ texts.deletePreference }}</h3>
        <p>{{ texts.deleteConfirm }}</p>
        <div class="schedule-confirm-actions">
          <button
            type="button"
            class="schedule-secondary-button"
            :disabled="!!deletingPreferenceKey"
            @click="cancelDeletePreference"
          >
            {{ texts.cancel }}
          </button>
          <button
            type="button"
            class="login-button schedule-danger-button"
            :disabled="!!deletingPreferenceKey"
            @click="executeDeletePreference"
          >
            {{ deletingPreferenceKey ? texts.deleting : texts.delete }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de confirmação de substituição de preferência existente -->
    <transition name="fade">
      <div v-if="showOverwriteModal" class="profile-modal-overlay" role="presentation" @click.self="cancelOverwrite">
        <div class="profile-modal" role="dialog" aria-modal="true">
          <h3>{{ texts.overwriteTitle }}</h3>
          <p>{{ texts.overwriteConfirm }}</p>
          <div class="profile-modal-actions">
            <button type="button" class="secondary-btn" @click="cancelOverwrite">
              {{ texts.cancel }}
            </button>
            <button type="button" class="submit-btn" @click="confirmOverwrite">
              {{ texts.overwrite }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </main>
</template>
