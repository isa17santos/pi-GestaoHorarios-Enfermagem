<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

// Represents one draft shift block in the UI before sending data to the API.
type DraftShift = {
  shift_type_id: number | null
  shift_date: string
  user_ids: number[]
}

// Access schedule state/actions from the global schedule composable.
const {
  nurses,
  shiftTypes,
  loadingNurses,
  loadingShiftTypes,
  loadingScheduleCreation,
  loadingShiftCreation,
  errorNurses,
  errorShiftTypes,
  errorScheduleCreation,
  errorShiftCreation,
  fetchNurses,
  fetchShiftTypes,
  createSchedule,
  createShift,
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

// Dynamic list of shift blocks that the head nurse can add/remove before submit.
const draftShifts = ref<DraftShift[]>([
  {
    shift_type_id: null,
    shift_date: '',
    user_ids: [],
  },
])

const localError = ref('')
const localSuccess = ref('')
const isBootstrapping = ref(true)
const isSubmitting = ref(false)

// Add one more shift block to the draft schedule.
const addDraftShift = () => {
  draftShifts.value.push({
    shift_type_id: null,
    shift_date: '',
    user_ids: [],
  })
}

// Remove a shift block, but keep at least one visible for UX simplicity.
const removeDraftShift = (index: number) => {
  if (draftShifts.value.length === 1) return
  draftShifts.value.splice(index, 1)
}

// Toggle nurse assignment in one shift block (checkbox behavior).
const toggleNurseInShift = (shiftIndex: number, nurseId: number) => {
  const selectedUsers = draftShifts.value[shiftIndex].user_ids
  const alreadySelected = selectedUsers.includes(nurseId)

  draftShifts.value[shiftIndex].user_ids = alreadySelected
    ? selectedUsers.filter((id) => id !== nurseId)
    : [...selectedUsers, nurseId]
}

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

  if (draftShifts.value.length === 0) {
    localError.value = 'Adiciona pelo menos um turno antes de submeter.'
    return false
  }

  for (let index = 0; index < draftShifts.value.length; index += 1) {
    const shift = draftShifts.value[index]

    if (!shift.shift_type_id) {
      localError.value = `Seleciona o tipo de turno no bloco ${index + 1}.`
      return false
    }

    if (!shift.shift_date) {
      localError.value = `Seleciona a data no bloco ${index + 1}.`
      return false
    }

    if (shift.user_ids.length === 0) {
      localError.value = `Seleciona pelo menos um enfermeiro no bloco ${index + 1}.`
      return false
    }
  }

  return true
}

// Submit flow: create parent schedule first, then create each shift linked to that schedule.
const handleSubmit = async () => {
  localError.value = ''
  localSuccess.value = ''

  if (!validateForm()) return

  isSubmitting.value = true

  try {
    await createSchedule(form.start_date, form.end_date)

    for (const shift of draftShifts.value) {
      await createShift(
        shift.shift_type_id as number,
        shift.shift_date,
        shift.user_ids
      )
    }

    localSuccess.value = 'Horario criado com sucesso.'

    draftShifts.value = [{
      shift_type_id: null,
      shift_date: '',
      user_ids: [],
    }]
  } catch {
    localError.value =
      errorScheduleCreation.value
      || errorShiftCreation.value
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

    // Load base data required to build schedule drafts in the UI.
    await Promise.all([fetchNurses(), fetchShiftTypes()])
  } catch {
    localError.value =
      errorNurses.value
      || errorShiftTypes.value
      || 'Nao foi possivel carregar os dados iniciais.'
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
        Primeiro define o periodo, depois adiciona os turnos com tipo, data e enfermeiros.
      </p>

      <p v-if="localError" class="form-error">
        {{ localError }}
      </p>

      <p v-if="localSuccess" class="form-success">
        {{ localSuccess }}
      </p>

      <p v-if="errorNurses || errorShiftTypes" class="form-error">
        {{ errorNurses || errorShiftTypes }}
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

        <section
          v-for="(shift, index) in draftShifts"
          :key="`shift-${index}`"
          class="schedule-shift-block"
        >
          <div class="schedule-shift-header">
            <h2>Turno {{ index + 1 }}</h2>
            <button
              type="button"
              class="schedule-remove-button"
              :disabled="draftShifts.length === 1"
              @click="removeDraftShift(index)"
            >
              Remover
            </button>
          </div>

          <div class="schedule-shift-fields">
            <label class="field">
              <span>Tipo de turno</span>
              <select v-model="shift.shift_type_id" class="schedule-select">
                <option :value="null">Selecionar</option>
                <option
                  v-for="shiftType in shiftTypes"
                  :key="shiftType.id"
                  :value="shiftType.id"
                >
                  {{ shiftType.name }} ({{ shiftType.start_time }} - {{ shiftType.end_time }})
                </option>
              </select>
            </label>

            <label class="field">
              <span>Data do turno</span>
              <input v-model="shift.shift_date" type="date" name="shift_date">
            </label>
          </div>

          <fieldset class="schedule-nurse-list">
            <legend>Enfermeiros</legend>

            <p v-if="loadingNurses" class="password-hint">
              A carregar enfermeiros...
            </p>

            <div v-else class="schedule-nurse-grid">
              <label
                v-for="nurse in nurses"
                :key="nurse.id"
                class="schedule-nurse-option"
              >
                <input
                  type="checkbox"
                  :checked="shift.user_ids.includes(nurse.id)"
                  @change="toggleNurseInShift(index, nurse.id)"
                >
                <span>{{ nurse.name }}</span>
              </label>
            </div>
          </fieldset>
        </section>

        <div class="schedule-actions-row">
          <button type="button" class="schedule-secondary-button" @click="addDraftShift">
            Adicionar turno
          </button>

          <button
            type="submit"
            class="login-button"
            :disabled="
              isBootstrapping
              || loadingNurses
              || loadingShiftTypes
              || loadingScheduleCreation
              || loadingShiftCreation
              || isSubmitting
            "
          >
            {{ isSubmitting ? 'A submeter...' : 'Criar horario' }}
          </button>
        </div>
      </form>
    </section>
  </main>
</template>
