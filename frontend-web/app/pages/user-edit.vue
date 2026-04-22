<script setup lang="ts">
const config = useRuntimeConfig()
const router = useRouter()
const route = useRoute()
const { token } = useAuth()
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

definePageMeta({
  middleware: 'auth',
})

const userId = route.query.id
const loading = ref(false)
const fetching = ref(true)
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)
const errors = ref<Record<string, string>>({})

const form = ref({
  name: '',
  email: '',
  role: '',
  active: true
})

// Dropdown states
const isRoleOpen = ref(false)
const isStatusOpen = ref(false)

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Editar Utilizador' : 'Edit User',
  subtitle: currentLocale.value === 'pt' ? 'Atualize as informações do profissional' : 'Update the professional\'s information',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  labels: {
    name: currentLocale.value === 'pt' ? 'Nome Completo' : 'Full Name',
    email: currentLocale.value === 'pt' ? 'Email Profissional' : 'Professional Email',
    role: currentLocale.value === 'pt' ? 'Cargo' : 'Role',
    status: currentLocale.value === 'pt' ? 'Estado' : 'Status',
  },
  roles: {
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    head_nurse: currentLocale.value === 'pt' ? 'Enfermeiro Chefe' : 'Head Nurse',
  },
  status: {
    active: currentLocale.value === 'pt' ? 'Ativo' : 'Active',
    inactive: currentLocale.value === 'pt' ? 'Inativo' : 'Inactive',
  },
  save: currentLocale.value === 'pt' ? 'Atualizar Utilizador' : 'Update User',
  saving: currentLocale.value === 'pt' ? 'A atualizar...' : 'Updating...',
  success: currentLocale.value === 'pt' ? 'Utilizador atualizado com sucesso!' : 'User updated successfully!',
  error: currentLocale.value === 'pt' ? 'Erro ao atualizar utilizador.' : 'Error updating user.',
  fetchError: currentLocale.value === 'pt' ? 'Erro ao carregar dados do utilizador.' : 'Error loading user data.',
  validation: {
    required: currentLocale.value === 'pt' ? 'Este campo é obrigatório' : 'This field is required',
    name: currentLocale.value === 'pt' ? 'O nome é obrigatório' : 'Name is required',
    email: currentLocale.value === 'pt' ? 'O email é obrigatório' : 'Email is required',
    role: currentLocale.value === 'pt' ? 'O cargo é obrigatório' : 'Role is required',
    formError: currentLocale.value === 'pt' ? 'Por favor, corrija os erros no formulário.' : 'Please correct the errors in the form.',
    emailInvalid: currentLocale.value === 'pt' ? 'Introduza um email válido' : 'Enter a valid email address',
    emailTaken: currentLocale.value === 'pt' ? 'Este email já está em uso' : 'This email is already taken',
  }
}))

const formatRole = (role: string) => {
  if (role === 'head_nurse') return texts.value.roles.head_nurse
  if (role === 'nurse') return texts.value.roles.nurse
  return role
}

const fetchUser = async () => {
  if (!userId) {
    router.push('/human-resources')
    return
  }

  try {
    const response = await $fetch<{ data: any }>(`${config.public.apiBase}/users/${userId}`, {
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json',
      }
    })
    
    const user = response.data
    form.value = {
      name: user.name,
      email: user.email,
      role: user.role,
      active: !!user.active
    }
  } catch (err) {
    console.error('Fetch User Error:', err)
    showNotification('fetchError', 'error')
  } finally {
    fetching.value = false
  }
}

const handleSubmit = async () => {
  errors.value = {}
  
  if (!form.value.name) errors.value.name = 'name'
  
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!form.value.email) {
    errors.value.email = 'email'
  } else if (!emailRegex.test(form.value.email)) {
    errors.value.email = 'emailInvalid'
  }

  if (!form.value.role) errors.value.role = 'role'

  if (Object.keys(errors.value).length > 0) {
    showNotification('formError', 'error')
    return
  }

  loading.value = true
  try {
    await $fetch(`${config.public.apiBase}/users/${userId}`, {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json',
      },
      body: {
        name: form.value.name,
        email: form.value.email,
        role: form.value.role,
        active: form.value.active,
      }
    })

    showNotification('success', 'success')
    
    setTimeout(() => {
      router.push('/human-resources')
    }, 1500)
    
  } catch (err: any) {
    console.error('Update User Error:', err)
    
    if (err.statusCode === 422 && err.data?.errors) {
      const serverErrors = err.data.errors
      if (serverErrors.email && serverErrors.email[0].includes('taken')) {
        errors.value.email = 'emailTaken'
      } else {
        showNotification('formError', 'error')
      }
    } else {
      showNotification('error', 'error')
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchUser()
})
</script>

<template>
  <main class="dashboard-layout user-create-page">
    <AppNavbar />
    
    <div class="uc-top-bar">
      <div class="uc-title-group">
        <NuxtLink to="/human-resources" class="back-link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="20" height="20">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
          {{ texts.back }}
        </NuxtLink>
        <h1>{{ texts.title }}</h1>
        <p class="uc-subtitle">{{ texts.subtitle }}</p>
      </div>
    </div>

    <div class="uc-card">
      <div v-if="fetching" class="loading-state">
        <div class="spinner"></div>
      </div>
      
      <form v-else @submit.prevent="handleSubmit" class="uc-form" novalidate>
        <div class="form-grid">
          <!-- Name -->
          <div class="form-group">
            <label>{{ texts.labels.name }}</label>
            <input 
              v-model="form.name" 
              type="text" 
              class="uc-input"
              :class="{ 'input-error': errors.name }"
            />
            <transition name="fade">
              <span v-if="errors.name" class="field-error">{{ (texts.validation as any)[errors.name] }}</span>
            </transition>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label>{{ texts.labels.email }}</label>
            <input 
              v-model="form.email" 
              type="email" 
              class="uc-input"
              :class="{ 'input-error': errors.email }"
            />
            <transition name="fade">
              <span v-if="errors.email" class="field-error">{{ (texts.validation as any)[errors.email] }}</span>
            </transition>
          </div>

          <!-- Role Select -->
          <div class="form-group">
            <label>{{ texts.labels.role }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ 'active': isRoleOpen, 'select-error': errors.role }"
                @click="isRoleOpen = !isRoleOpen; isStatusOpen = false"
              >
                <span>{{ form.role ? formatRole(form.role) : texts.roles.nurse }}</span>
                <svg :class="{ 'rotate': isRoleOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="18" height="18">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </div>
              <transition name="fade">
                <div v-if="isRoleOpen" class="uc-options">
                  <div class="uc-option" @click="form.role = 'head_nurse'; isRoleOpen = false">
                    {{ texts.roles.head_nurse }}
                  </div>
                  <div class="uc-option" @click="form.role = 'nurse'; isRoleOpen = false">
                    {{ texts.roles.nurse }}
                  </div>
                </div>
              </transition>
            </div>
            <transition name="fade">
              <span v-if="errors.role" class="field-error">{{ (texts.validation as any)[errors.role] }}</span>
            </transition>
          </div>

          <!-- Status Select -->
          <div class="form-group">
            <label>{{ texts.labels.status }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ 'active': isStatusOpen }"
                @click="isStatusOpen = !isStatusOpen; isRoleOpen = false"
              >
                <span>{{ form.active ? texts.status.active : texts.status.inactive }}</span>
                <svg :class="{ 'rotate': isStatusOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="18" height="18">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </div>
              <transition name="fade">
                <div v-if="isStatusOpen" class="uc-options">
                  <div class="uc-option" @click="form.active = true; isStatusOpen = false">
                    {{ texts.status.active }}
                  </div>
                  <div class="uc-option" @click="form.active = false; isStatusOpen = false">
                    {{ texts.status.inactive }}
                  </div>
                </div>
              </transition>
            </div>
          </div>
        </div>

        <div class="uc-actions">
          <button type="submit" class="submit-btn" :class="{ 'loading': loading }" :disabled="loading">
            {{ loading ? texts.saving : texts.save }}
          </button>
        </div>
      </form>
    </div>

    <!-- Global Toast Notification -->
    <transition name="toast">
      <div v-if="statusMessage" class="global-toast" :class="statusMessage.type">
        <div class="toast-content">
          <svg v-if="statusMessage.type === 'success'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span>{{ (texts as any)[statusMessage.key] || (texts.validation as any)[statusMessage.key] }}</span>
        </div>
      </div>
    </transition>
  </main>
</template>

<style>
@import url('~/assets/css/user-create.css');
</style>

<style scoped>
.loading-state {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 400px;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid rgba(125, 83, 213, 0.1);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.uc-select-trigger svg {
  transition: transform 0.3s ease;
}

.uc-select-trigger svg.rotate {
  transform: rotate(180deg);
}

/* Fix for toast icon size */
.global-toast svg {
  width: 24px !important;
  height: 24px !important;
  min-width: 24px;
}
</style>
