<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { token, user: currentUser, fetchMe } = useAuth()
const config = useRuntimeConfig()
const router = useRouter()
const route = useRoute()
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const parsedShiftId = Number(route.query.id)
const shiftId = Number.isFinite(parsedShiftId) && parsedShiftId > 0 ? parsedShiftId : null

// Estado do formulário
const form = ref({
  name: '',
  color: '#d9f3ff',
  start_time: '',
  end_time: '',
  min_nurses: 1,
})

const loading = ref(false)
const fetching = ref(false)
const statusMessage = ref<{ key: string; type: 'success' | 'error'; message?: string } | null>(null)
const errors = ref<Record<string, string>>({})

const showNotification = (key: string, type: 'success' | 'error' = 'success', message?: string) => {
  statusMessage.value = { key, type, message }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Editar Tipo de Turno' : 'Edit Shift Type',
  subtitle: currentLocale.value === 'pt' ? 'Atualize os dados do tipo de turno' : 'Update the shift type details',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  labels: {
    name: currentLocale.value === 'pt' ? 'Nome do Turno' : 'Shift Name',
    color: currentLocale.value === 'pt' ? 'Cor de Identificação' : 'Identification Color',
    start_time: currentLocale.value === 'pt' ? 'Hora de Início' : 'Start Time',
    end_time: currentLocale.value === 'pt' ? 'Hora de Fim' : 'End Time',
    min_nurses: currentLocale.value === 'pt' ? 'Mínimo de Enfermeiros' : 'Minimum Nurses',
  },
  placeholders: {
    name: currentLocale.value === 'pt' ? 'Ex: morning, night, dayOff...' : 'e.g. morning, night, dayOff...',
    min_nurses: currentLocale.value === 'pt' ? 'Ex: 2' : 'e.g. 2',
    colorHex: '#d9f3ff',
  },
  save: currentLocale.value === 'pt' ? 'Atualizar Tipo de Turno' : 'Update Shift Type',
  saving: currentLocale.value === 'pt' ? 'A guardar...' : 'Saving...',
  success: currentLocale.value === 'pt' ? 'Tipo de turno atualizado com sucesso!' : 'Shift type updated successfully!',
  error: currentLocale.value === 'pt' ? 'Erro ao atualizar o tipo de turno.' : 'Error updating shift type.',
  fetchError: currentLocale.value === 'pt' ? 'Erro ao carregar os dados do tipo de turno.' : 'Error loading shift type data.',
  idMissing: currentLocale.value === 'pt' ? 'ID do tipo de turno inválido.' : 'Invalid shift type ID.',
  validation: {
    name: currentLocale.value === 'pt' ? 'O nome é obrigatório' : 'Name is required',
    name_taken: currentLocale.value === 'pt' ? 'Este nome de turno já existe' : 'This shift name already exists',
    name_invalid_backend: currentLocale.value === 'pt' ? 'O nome do tipo de turno não foi aceite pela API.' : 'The shift type name was not accepted by the API.',
    start_time: currentLocale.value === 'pt' ? 'A hora de início é obrigatória' : 'Start time is required',
    end_time: currentLocale.value === 'pt' ? 'A hora de fim é obrigatória' : 'End time is required',
    min_nurses: currentLocale.value === 'pt' ? 'O número mínimo de enfermeiros é obrigatório' : 'Minimum nurses is required',
    min_nurses_invalid: currentLocale.value === 'pt' ? 'Deve ser no mínimo 1' : 'Must be at least 1',
    serverValidation: currentLocale.value === 'pt' ? 'Verifique os dados do formulário.' : 'Please verify the form data.',
    formError: currentLocale.value === 'pt' ? 'Por favor, corrija os erros no formulário.' : 'Please correct the errors in the form.',
  },
}))

const toApiTime = (value: string) => {
  const trimmed = value.trim()
  if (/^\d{2}:\d{2}:\d{2}$/.test(trimmed)) return trimmed
  if (/^\d{2}:\d{2}$/.test(trimmed)) return `${trimmed}:00`
  return trimmed
}

const getFirstBackendErrorMessage = (err: any): string | undefined => {
  const rawErrors = err?.data?.errors
  if (rawErrors && typeof rawErrors === 'object') {
    for (const key of Object.keys(rawErrors)) {
      const value = rawErrors[key]
      if (Array.isArray(value) && typeof value[0] === 'string' && value[0]) {
        return value[0]
      }
    }
  }

  if (typeof err?.data?.message === 'string' && err.data.message) {
    return err.data.message
  }

  return undefined
}

// Abre o color picker nativo ao clicar no swatch
const colorInputRef = ref<HTMLInputElement | null>(null)
const openColorPicker = () => {
  colorInputRef.value?.click()
}

// Garante que o hex digitado manualmente é válido antes de aceitar
const onHexInput = (e: Event) => {
  const value = (e.target as HTMLInputElement).value
  if (/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(value)) {
    form.value.color = value
  }
}

const fetchShiftType = async () => {
  if (!shiftId) {
    showNotification('idMissing', 'error')
    setTimeout(() => {
      router.push('/shift-types')
    }, 1200)
    return
  }

  fetching.value = true
  try {
    const response = await $fetch<{
      data: { id: number; name: string; color: string | null; start_time: string; end_time: string; min_nurses: number }
    }>(`${config.public.apiBase}/shift-types/${shiftId}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })

    const item = response.data
    form.value = {
      name: item.name ?? '',
      color: item.color ?? '#d9f3ff',
      start_time: item.start_time ? item.start_time.slice(0, 5) : '',
      end_time: item.end_time ? item.end_time.slice(0, 5) : '',
      min_nurses: item.min_nurses ?? 1,
    }
  } catch (e) {
    console.error('Erro ao carregar tipo de turno:', e)
    showNotification('fetchError', 'error')
  } finally {
    fetching.value = false
  }
}

const handleSubmit = async () => {
  errors.value = {}

  if (!form.value.name.trim()) errors.value.name = 'name'
  if (!form.value.start_time) errors.value.start_time = 'start_time'
  if (!form.value.end_time) errors.value.end_time = 'end_time'
  if (!form.value.min_nurses && form.value.min_nurses !== 0) {
    errors.value.min_nurses = 'min_nurses'
  } else if (Number(form.value.min_nurses) < 1) {
    errors.value.min_nurses = 'min_nurses_invalid'
  }

  if (Object.keys(errors.value).length > 0) {
    showNotification('formError', 'error')
    return
  }

  if (!shiftId) {
    showNotification('idMissing', 'error')
    return
  }

  loading.value = true
  try {
    const body = {
      name: form.value.name.trim(),
      color: form.value.color,
      start_time: toApiTime(form.value.start_time),
      end_time: toApiTime(form.value.end_time),
      min_nurses: Number(form.value.min_nurses),
    }

    await $fetch(`${config.public.apiBase}/shift-types/${shiftId}`, {
      method: 'PATCH',
      headers: { Authorization: `Bearer ${token.value}`, Accept: 'application/json' },
      body,
    })

    showNotification('success', 'success')
    setTimeout(() => {
      router.push('/shift-types')
    }, 1500)
  } catch (err: any) {
    console.error('Erro ao atualizar tipo de turno:', err)

    if (err?.statusCode === 422 && err?.data?.errors) {
      const serverErrors = err.data.errors as Record<string, string[]>

      if (serverErrors.name?.length) {
        const firstNameError = serverErrors.name[0]?.toLowerCase() ?? ''
        errors.value.name = /taken|exists|unique|ja existe|já existe/.test(firstNameError)
          ? 'name_taken'
          : 'name_invalid_backend'
      }
      if (serverErrors.start_time?.length) {
        errors.value.start_time = 'start_time'
      }
      if (serverErrors.end_time?.length) {
        errors.value.end_time = 'end_time'
      }
      if (serverErrors.min_nurses?.length) {
        errors.value.min_nurses = 'min_nurses_invalid'
      }

      showNotification('serverValidation', 'error', getFirstBackendErrorMessage(err))
      return
    }

    showNotification('error', 'error', getFirstBackendErrorMessage(err))
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!currentUser.value) {
    await fetchMe().catch(() => null)
  }

  if (currentUser.value?.role?.toLowerCase().trim() !== 'admin') {
    await navigateTo('/dashboard')
  }

  await fetchShiftType()
})
</script>

<template>
  <main class="dashboard-layout user-create-page">
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
          <span>{{ statusMessage.message || (texts as any)[statusMessage.key] || (texts.validation as any)[statusMessage.key] }}</span>
        </div>
      </div>
    </transition>

    <div class="uc-top-bar">
      <div class="uc-title-group">
        <NuxtLink to="/shift-types" class="back-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          {{ texts.back }}
        </NuxtLink>
        <h1>{{ texts.title }}</h1>
        <p class="uc-subtitle">{{ texts.subtitle }}</p>
      </div>
    </div>

    <div v-if="fetching" class="loading-state">
      <div class="spinner"></div>
    </div>

    <section v-else class="uc-card">
      <form @submit.prevent="handleSubmit" class="uc-form" novalidate>
        <div class="form-grid stc-grid">
          <div class="form-group stc-full-width">
            <label>{{ texts.labels.name }}</label>
            <input
              v-model="form.name"
              type="text"
              :placeholder="texts.placeholders.name"
              class="uc-input"
              :class="{ 'input-error': errors.name }"
            />
            <transition name="fade">
              <span v-if="errors.name" class="field-error">{{ (texts.validation as any)[errors.name] }}</span>
            </transition>
          </div>

          <div class="form-group">
            <label>{{ texts.labels.start_time }}</label>
            <input
              v-model="form.start_time"
              type="time"
              class="uc-input stc-time-input"
              :class="{ 'input-error': errors.start_time }"
            />
            <transition name="fade">
              <span v-if="errors.start_time" class="field-error">{{ (texts.validation as any)[errors.start_time] }}</span>
            </transition>
          </div>

          <div class="form-group">
            <label>{{ texts.labels.end_time }}</label>
            <input
              v-model="form.end_time"
              type="time"
              class="uc-input stc-time-input"
              :class="{ 'input-error': errors.end_time }"
            />
            <transition name="fade">
              <span v-if="errors.end_time" class="field-error">{{ (texts.validation as any)[errors.end_time] }}</span>
            </transition>
          </div>

          <div class="form-group">
            <label>{{ texts.labels.min_nurses }}</label>
            <input
              v-model.number="form.min_nurses"
              type="number"
              min="1"
              :placeholder="texts.placeholders.min_nurses"
              class="uc-input"
              :class="{ 'input-error': errors.min_nurses }"
            />
            <transition name="fade">
              <span v-if="errors.min_nurses" class="field-error">{{ (texts.validation as any)[errors.min_nurses] }}</span>
            </transition>
          </div>

          <div class="form-group">
            <label>{{ texts.labels.color }}</label>
            <div class="stc-color-field">
              <button type="button" class="stc-color-swatch" :style="{ backgroundColor: form.color }" @click="openColorPicker" :title="texts.labels.color">
                <input
                  ref="colorInputRef"
                  type="color"
                  v-model="form.color"
                  class="stc-color-input-hidden"
                  tabindex="-1"
                />
              </button>
              <input
                :value="form.color"
                type="text"
                class="uc-input stc-hex-input"
                :placeholder="texts.placeholders.colorHex"
                maxlength="7"
                @input="onHexInput"
              />
            </div>
          </div>
        </div>

        <div class="uc-actions">
          <button type="submit" class="submit-btn" :class="{ loading }" :disabled="loading">
            {{ loading ? texts.saving : texts.save }}
          </button>
        </div>
      </form>
    </section>
  </main>
</template>

<style scoped>
.stc-full-width {
  grid-column: span 2;
}

.stc-time-input::-webkit-calendar-picker-indicator {
  opacity: 0.5;
  cursor: pointer;
  filter: invert(30%) sepia(60%) saturate(500%) hue-rotate(230deg);
}

.stc-color-field {
  display: flex;
  align-items: center;
  gap: 14px;
}

.stc-color-swatch {
  flex-shrink: 0;
  width: 64px;
  height: 64px;
  border-radius: 20px;
  border: 1px solid var(--line);
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
  box-shadow: 0 6px 16px rgba(102, 67, 155, 0.12);
}

.stc-color-swatch:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(102, 67, 155, 0.2);
}

.stc-color-input-hidden {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
  border: none;
  padding: 0;
}

.stc-hex-input {
  flex: 1;
  font-family: 'Courier New', monospace;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

@media (max-width: 768px) {
  .stc-full-width {
    grid-column: span 1;
  }

  .stc-color-swatch {
    width: 56px;
    height: 56px;
  }
}
</style>
