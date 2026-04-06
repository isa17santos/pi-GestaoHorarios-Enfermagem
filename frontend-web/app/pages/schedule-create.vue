<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

// Access schedule state/actions from the global schedule composable.
const {
  loadingScheduleCreation,
  errorScheduleCreation,
  createSchedule,
  setSelectedPeriod,
} = useSchedule()

// Access authenticated user state to enforce role-based access in this page too.
const { user, fetchMe } = useAuth()

// Protect this page at component level: only admin/head nurse can use schedule creation.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})

// Schedule period form model.
const form = reactive({
  start_date: '',
  end_date: '',
})

const localError = ref('')
const isBootstrapping = ref(true)
const isSubmitting = ref(false)

// Validate all mandatory fields before any API request.
const validateForm = () => {
  localError.value = ''

  if (!form.start_date || !form.end_date) {
    localError.value = 'Seleciona as datas de inicio e fim do horario.'
    return false
  }

  if (form.end_date < form.start_date) {
    localError.value = 'A data de fim nao pode ser anterior a data de inicio.'
    return false
  }

  return true
}

// Submit flow: create the schedule period and continue to the grid editor page.
const handleSubmit = async () => {
  localError.value = ''

  if (!validateForm()) return

  isSubmitting.value = true

  try {
    const createdSchedule = await createSchedule(form.start_date, form.end_date)

    await navigateTo({
      path: '/schedule-edit',
      query: {
        scheduleId: String(createdSchedule.id),
      },
    })
  } catch {
    localError.value =
      errorScheduleCreation.value
      || 'Nao foi possivel criar o horario. Tenta novamente.'
  } finally {
    isSubmitting.value = false
  }
}

// Keep the composable selected month/year in sync with the chosen start date.
watch(
  () => form.start_date,
  (value) => {
    if (!value) return

    const selectedDate = new Date(value)
    if (Number.isNaN(selectedDate.getTime())) return

    setSelectedPeriod(selectedDate.getMonth() + 1, selectedDate.getFullYear())
  }
)

onMounted(async () => {
  isBootstrapping.value = true
  localError.value = ''

  try {
    // Ensure user profile is available after refresh before role validation.
    if (!user.value) {
      await fetchMe().catch(() => null)
    }

    // Hard guard: even with direct URL access, only admin/head nurse can proceed.
    if (!canCreateSchedule.value) {
      await navigateTo('/dashboard')
      return
    }
  } catch {
    localError.value =
      'Nao foi possivel carregar os dados iniciais.'
  } finally {
    isBootstrapping.value = false
  }
})
</script>

<template>
  <main class="dashboard-page schedule-page">
    <section class="dashboard-card schedule-card">
      <p class="eyebrow">Criacao de horario</p>
      <h1>Novo horario</h1>

      <button type="button" class="schedule-secondary-button" @click="navigateTo('/dashboard')">
        Voltar
      </button>

      <p class="schedule-intro">
        Define o periodo do horario. A atribuicao de turnos sera feita na pagina seguinte.
      </p>

      <p v-if="localError" class="form-error">
        {{ localError }}
      </p>

      <form class="login-form" novalidate @submit.prevent="handleSubmit">
        <div class="schedule-period">
          <label class="field">
            <span>Data de inicio</span>
            <input v-model="form.start_date" type="date" name="start_date">
          </label>

          <label class="field">
            <span>Data de fim</span>
            <input v-model="form.end_date" type="date" name="end_date">
          </label>
        </div>

        <div class="schedule-actions-row">
          <button
            type="submit"
            class="login-button"
            :disabled="
              isBootstrapping
              || loadingScheduleCreation
              || isSubmitting
            "
          >
            {{ isSubmitting ? 'A criar...' : 'Continuar para a grelha' }}
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
