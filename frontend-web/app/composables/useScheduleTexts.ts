// Shared composable for schedule-related text translations
export const useScheduleTexts = () => {
  const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

  const texts = computed(() => ({
    // Common texts
    backButton: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
    loading: currentLocale.value === 'pt' ? 'A carregar...' : 'Loading...',

    // Schedule creation texts
    create: {
      pageEyebrow: currentLocale.value === 'pt' ? 'CRIAÇÃO DE HORÁRIO' : 'SCHEDULE CREATION',
      pageTitle: currentLocale.value === 'pt' ? 'Novo horário' : 'New Schedule',
      intro: currentLocale.value === 'pt'
        ? 'Define o período do horário. A atribuição de turnos será feita na página seguinte.'
        : 'Define the schedule period. Shift assignments will be done on the next page.',
      startDate: currentLocale.value === 'pt' ? 'Data de início' : 'Start date',
      endDate: currentLocale.value === 'pt' ? 'Data de fim' : 'End date',
      submitting: currentLocale.value === 'pt' ? 'A criar...' : 'Creating...',
      submit: currentLocale.value === 'pt' ? 'Continuar para a grelha' : 'Continue to grid',
      errors: {
        required: currentLocale.value === 'pt'
          ? 'Seleciona as datas de início e fim do horário.'
          : 'Please select the start and end dates.',
        endBeforeStart: currentLocale.value === 'pt'
          ? 'A data de fim não pode ser anterior à data de início.'
          : 'The end date cannot be before the start date.',
        createFailed: currentLocale.value === 'pt'
          ? 'Não foi possível criar o horário. Tenta novamente.'
          : 'Could not create the schedule. Please try again.',
        initialData: currentLocale.value === 'pt'
          ? 'Não foi possível carregar os dados iniciais.'
          : 'Could not load initial data.',
      },
    },

    // Schedule edit texts
    edit: {
      pageEyebrow: currentLocale.value === 'pt' ? 'EDIÇÃO DE HORÁRIO' : 'SCHEDULE EDITOR',
      pageTitle: currentLocale.value === 'pt' ? 'Edição de Horário' : 'Schedule Editor',
      pageSubtitle: currentLocale.value === 'pt'
        ? 'Seleciona o tipo de turno em cada célula para atribuir o enfermeiro nesse dia.'
        : 'Select the shift type in each cell to assign the nurse for that day.',
      activeShiftLabel: currentLocale.value === 'pt' ? 'Turno ativo:' : 'Active shift:',
      nurseHeader: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
      previousMonth: currentLocale.value === 'pt' ? 'Mês anterior' : 'Previous month',
      nextMonth: currentLocale.value === 'pt' ? 'Mês seguinte' : 'Next month',
      saveAssignments: currentLocale.value === 'pt' ? 'Guardar atribuições' : 'Save assignments',
      savingAssignments: currentLocale.value === 'pt' ? 'A guardar...' : 'Saving...',
      loadingGrid: currentLocale.value === 'pt' ? 'A carregar grelha...' : 'Loading schedule...',
      noNurses: currentLocale.value === 'pt'
        ? 'Não existem enfermeiros para mostrar.'
        : 'No nurses available to display.',
      noPreferences: currentLocale.value === 'pt'
        ? 'Sem preferências definidas'
        : 'No preferences defined',
      restWarning: currentLocale.value === 'pt'
        ? 'Menos de 11 horas de descanso'
        : 'Less than 11 hours of rest',
      assignmentsAgainstPreferences: (count: number) =>
        currentLocale.value === 'pt'
          ? `${count} turnos atribuídos contra as preferências`
          : `${count} assignments against preferences`,
      preferLabel: currentLocale.value === 'pt' ? 'Prefere:' : 'Prefers:',
      avoidLabel: currentLocale.value === 'pt' ? 'Evita:' : 'Avoids:',
      weekends: currentLocale.value === 'pt' ? 'Fins de semana' : 'Weekends',
      shiftNames: {
        morning: currentLocale.value === 'pt' ? 'Manhã' : 'Morning',
        manhã: currentLocale.value === 'pt' ? 'Manhã' : 'Morning',
        afternoon: currentLocale.value === 'pt' ? 'Tarde' : 'Afternoon',
        tarde: currentLocale.value === 'pt' ? 'Tarde' : 'Afternoon',
        night: currentLocale.value === 'pt' ? 'Noite' : 'Night',
        noite: currentLocale.value === 'pt' ? 'Noite' : 'Night',
        dayoff: currentLocale.value === 'pt' ? 'Folga' : 'Day Off',
        'day off': currentLocale.value === 'pt' ? 'Folga' : 'Day Off',
        holidays: currentLocale.value === 'pt' ? 'Férias' : 'Holidays',
        'sick leave': currentLocale.value === 'pt' ? 'Baixa Médica' : 'Sick Leave',
        'parental leave': currentLocale.value === 'pt' ? 'Licença Parental' : 'Parental Leave',
      },
    },
  }))

  const toggleLanguage = () => {
    currentLocale.value = currentLocale.value === 'pt' ? 'en' : 'pt'
  }

  const localeLabel = computed(() =>
    currentLocale.value === 'pt' ? 'English' : 'Português'
  )

  const localeFlag = computed(() =>
    currentLocale.value === 'pt' ? 'en' : 'pt'
  )

  return {
    currentLocale,
    texts,
    toggleLanguage,
    localeLabel,
    localeFlag,
  }
}