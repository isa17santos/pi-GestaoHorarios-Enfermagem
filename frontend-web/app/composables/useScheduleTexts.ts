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
      draftsTitle: currentLocale.value === 'pt' ? 'Rascunhos existentes' : 'Existing drafts',
      draftsIntro: currentLocale.value === 'pt'
        ? 'Escolhe um horário em rascunho para continuares as alterações.'
        : 'Choose a draft schedule to continue editing.',
      continueDraft: currentLocale.value === 'pt' ? 'Continuar edição' : 'Continue editing',
      deleteDraft: currentLocale.value === 'pt' ? 'Apagar rascunho' : 'Delete draft',
      deletingDraft: currentLocale.value === 'pt' ? 'A apagar...' : 'Deleting...',
      deleteDraftConfirmation: currentLocale.value === 'pt'
        ? 'Tens a certeza de que queres apagar este rascunho? Esta ação não pode ser anulada.'
        : 'Are you sure you want to delete this draft? This action cannot be undone.',
      loadingDrafts: currentLocale.value === 'pt' ? 'A carregar rascunhos...' : 'Loading drafts...',
      noDrafts: currentLocale.value === 'pt'
        ? 'Não existem horários em rascunho para continuar.'
        : 'There are no draft schedules to continue.',
      periodLabel: currentLocale.value === 'pt' ? 'Período' : 'Period',
      month: currentLocale.value === 'pt' ? 'Mês' : 'Month',
      submitting: currentLocale.value === 'pt' ? 'A criar...' : 'Creating...',
      submit: currentLocale.value === 'pt' ? 'Continuar para a grelha' : 'Continue to grid',
      errors: {
        required: currentLocale.value === 'pt'
          ? 'Seleciona o mês do horário.'
          : 'Please select the schedule month.',
        pastMonth: currentLocale.value === 'pt'
          ? 'Não podes selecionar um mês anterior ao atual.'
          : 'You cannot select a month earlier than the current month.',
        createFailed: currentLocale.value === 'pt'
          ? 'Não foi possível criar o horário. Tenta novamente.'
          : 'Could not create the schedule. Please try again.',
        initialData: currentLocale.value === 'pt'
          ? 'Não foi possível carregar os dados iniciais.'
          : 'Could not load initial data.',
        loadDrafts: currentLocale.value === 'pt'
          ? 'Não foi possível carregar os horários em rascunho.'
          : 'Could not load draft schedules.',
        deleteDraft: currentLocale.value === 'pt'
          ? 'Não foi possível apagar o rascunho.'
          : 'Could not delete draft.',
        deleteDraftPublished: currentLocale.value === 'pt'
          ? 'Um horário publicado não pode ser apagado.'
          : 'A published schedule cannot be deleted.',
        deleteDraftUnauthorized: currentLocale.value === 'pt'
          ? 'Apenas o head nurse pode apagar rascunhos.'
          : 'Only the head nurse can delete drafts.',
      },
    },

    // Schedule edit texts
    edit: {
      pageEyebrow: currentLocale.value === 'pt' ? 'CRIAÇÃO DE HORÁRIO' : 'SCHEDULE CREATION',
      pageTitle: currentLocale.value === 'pt' ? 'Horário Mensal' : 'Monthly Schedule',
      pageSubtitle: currentLocale.value === 'pt'
        ? 'Seleciona o tipo de turno em cada célula para atribuir o enfermeiro nesse dia.'
        : 'Select the shift type in each cell to assign the nurse for that day.',
      activeShiftLabel: currentLocale.value === 'pt' ? 'Turno ativo:' : 'Active shift:',
      nurseHeader: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
      previousMonth: currentLocale.value === 'pt' ? 'Mês anterior' : 'Previous month',
      nextMonth: currentLocale.value === 'pt' ? 'Mês seguinte' : 'Next month',
      deleteDraft: currentLocale.value === 'pt' ? 'Apagar rascunho' : 'Delete draft',
      deletingDraft: currentLocale.value === 'pt' ? 'A apagar...' : 'Deleting...',
      deleteDraftConfirmation: currentLocale.value === 'pt'
        ? 'Tens a certeza de que queres apagar este rascunho? Esta ação não pode ser anulada.'
        : 'Are you sure you want to delete this draft? This action cannot be undone.',
      deleteDraftSuccess: currentLocale.value === 'pt'
        ? 'Rascunho apagado com sucesso.'
        : 'Draft deleted successfully.',
      saveAssignments: currentLocale.value === 'pt' ? 'Guardar atribuições' : 'Save assignments',
      savingAssignments: currentLocale.value === 'pt' ? 'A guardar...' : 'Saving...',
      loadingGrid: currentLocale.value === 'pt' ? 'A carregar grelha...' : 'Loading schedule...',
      noNurses: currentLocale.value === 'pt'
        ? 'Não existem enfermeiros para mostrar.'
        : 'No nurses available to display.',
      publishWarnings: {
        noAssignedShifts: currentLocale.value === 'pt'
          ? 'O horário não tem turnos atribuídos. Preenche a grelha antes de publicar.'
          : 'The schedule has no assigned shifts. Fill in the grid before publishing.',
        insufficientNurses: (count: number) => currentLocale.value === 'pt'
          ? `Um ou mais turnos têm enfermeiros insuficientes. Cada turno precisa de pelo menos ${count} enfermeiros.`
          : `One or more shifts have insufficient nurses. Each shift needs at least ${count} nurses.`,
      },
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