<script setup lang="ts">
// Only a authenticated user can access this page
definePageMeta({
  middleware: 'auth',
})

const { token, user: currentUser, fetchMe } = useAuth()
const config = useRuntimeConfig()
const router = useRouter()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Form State
const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
  active: true
})

const loading = ref(false)
const statusMessage = ref<{ key: string, type: 'success' | 'error' } | null>(null)
const errors = ref<Record<string, string>>({})

// Dropdown states
const isRoleOpen = ref(false)
const isStatusOpen = ref(false)

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const showNotification = (key: string, type: 'success' | 'error' = 'success') => {
  statusMessage.value = { key, type }
  setTimeout(() => {
    statusMessage.value = null
  }, 4000)
}

const texts = computed(() => ({
  title: currentLocale.value === 'pt' ? 'Criar Novo Utilizador' : 'Create New User',
  subtitle: currentLocale.value === 'pt' ? 'Preencha os dados do novo profissional' : 'Fill in the new professional details',
  back: currentLocale.value === 'pt' ? 'Voltar' : 'Back',
  labels: {
    name: currentLocale.value === 'pt' ? 'Nome Completo' : 'Full Name',
    email: currentLocale.value === 'pt' ? 'Email Profissional' : 'Professional Email',
    password: currentLocale.value === 'pt' ? 'Palavra-passe' : 'Password',
    password_confirmation: currentLocale.value === 'pt' ? 'Confirmar Palavra-passe' : 'Confirm Password',
    role: currentLocale.value === 'pt' ? 'Cargo' : 'Role',
    status: currentLocale.value === 'pt' ? 'Estado' : 'Status',
  },
  placeholders: {
    name: currentLocale.value === 'pt' ? 'Ex: Maria Silva' : 'e.g. Mary Johnson',
    email: currentLocale.value === 'pt' ? 'maria.silva@example.pt' : 'mary.johnson@example.pt',
    password: currentLocale.value === 'pt' ? 'Mínimo 8 caracteres, 1 maiuscula e 1 caracter especial' : 'Minimum 8 characters, 1 uppercase letter and 1 special character',
    password_confirmation: currentLocale.value === 'pt' ? 'Repita a palavra-passe' : 'Repeat the password',
    selectRole: currentLocale.value === 'pt' ? 'Selecione um cargo' : 'Select a role',
  },
  roles: {
    nurse: currentLocale.value === 'pt' ? 'Enfermeiro' : 'Nurse',
    head_nurse: currentLocale.value === 'pt' ? 'Enfermeiro Chefe' : 'Head Nurse',
  },
  status: {
    active: currentLocale.value === 'pt' ? 'Ativo' : 'Active',
    inactive: currentLocale.value === 'pt' ? 'Inativo' : 'Inactive',
  },
  save: currentLocale.value === 'pt' ? 'Criar Utilizador' : 'Create User',
  saving: currentLocale.value === 'pt' ? 'A guardar...' : 'Saving...',
  success: currentLocale.value === 'pt' ? 'Utilizador criado com sucesso!' : 'User created successfully!',
  error: currentLocale.value === 'pt' ? 'Erro ao criar utilizador.' : 'Error creating user.',
  validation: {
    required: currentLocale.value === 'pt' ? 'Este campo é obrigatório' : 'This field is required',
    name: currentLocale.value === 'pt' ? 'O nome é obrigatório' : 'Name is required',
    email: currentLocale.value === 'pt' ? 'O email é obrigatório' : 'Email is required',
    password: currentLocale.value === 'pt' ? 'A palavra-passe é obrigatória' : 'Password is required',
    password_confirmation: currentLocale.value === 'pt' ? 'A confirmação é obrigatória' : 'Confirmation is required',
    passwordMismatch: currentLocale.value === 'pt' ? 'As palavras-passe não coincidem' : 'Passwords do not match',
    role: currentLocale.value === 'pt' ? 'O cargo é obrigatório' : 'Role is required',
    formError: currentLocale.value === 'pt' ? 'Por favor, corrija os erros no formulário.' : 'Please correct the errors in the form.',
    emailInvalid: currentLocale.value === 'pt' ? 'Introduza um email válido' : 'Enter a valid email address',
    passwordWeak: currentLocale.value === 'pt' ? 'Mínimo 8 caracteres, 1 maiúscula e 1 símbolo' : 'Min 8 chars, 1 uppercase and 1 symbol',
    emailTaken: currentLocale.value === 'pt' ? 'Este email já está em uso' : 'This email is already taken',
  }
}))

const handleSubmit = async () => {
  errors.value = {}
  
  // Basic Required Fields
  if (!form.value.name) errors.value.name = 'name'
  
  // Email Validation with Regex
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!form.value.email) {
    errors.value.email = 'email'
  } else if (!emailRegex.test(form.value.email)) {
    errors.value.email = 'emailInvalid'
  }

  // Password Validation (Min 8, 1 Uppercase, 1 Special)
  const passwordRegex = /^(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]).{8,}$/
  if (!form.value.password) {
    errors.value.password = 'password'
  } else if (!passwordRegex.test(form.value.password)) {
    errors.value.password = 'passwordWeak'
  }

  // Password Confirmation Check
  if (!form.value.password_confirmation) {
    errors.value.password_confirmation = 'password_confirmation'
  } else if (form.value.password !== form.value.password_confirmation) {
    errors.value.password_confirmation = 'passwordMismatch'
  }

  if (!form.value.role) errors.value.role = 'role'

  if (Object.keys(errors.value).length > 0) {
    showNotification('formError', 'error')
    return
  }

  loading.value = true
  try {
    await $fetch(`${config.public.apiBase}/users`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token.value}`,
        'Accept': 'application/json',
      },
      body: {
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
        password_confirmation: form.value.password_confirmation,
        role: form.value.role,
        active: form.value.active,
      }
    })

    showNotification('success', 'success')
    
    setTimeout(() => {
      router.push('/human-resources')
    }, 1500)
    
  } catch (err: any) {
    console.error('Create User Error:', err)
    
    // Handle Laravel Validation Errors (422)
    if (err.statusCode === 422 && err.data?.errors) {
      const serverErrors = err.data.errors
      if (serverErrors.email && serverErrors.email[0].includes('taken')) {
        errors.value.email = 'emailTaken'
      } else {
        // Fallback for other server errors
        showNotification('formError', 'error')
      }
    } else {
      showNotification('error', 'error')
    }
  } finally {
    loading.value = false
  }
}

// Close dropdowns on click outside
if (process.client) {
  window.addEventListener('click', (e) => {
    const target = e.target as HTMLElement
    if (!target.closest('.uc-select-wrapper')) {
      isRoleOpen.value = false
      isStatusOpen.value = false
    }
  })
}

onMounted(async () => {
  if (!currentUser.value) {
    await fetchMe().catch(() => null)
  }
  
  // Auth check: only admin
  if (currentUser.value?.role?.toLowerCase().trim() !== 'admin') {
    await navigateTo('/dashboard')
  }
})
</script>

<template>
  <main class="dashboard-layout user-create-page">
    <AppNavbar />

    <!-- General Notifications (Centered at top) -->
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

    <div class="uc-top-bar">
      <div class="uc-title-group">
        <NuxtLink to="/human-resources" class="back-link">
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

    <section class="uc-card">
      <form @submit.prevent="handleSubmit" class="uc-form" novalidate>
        <div class="form-grid">
          <!-- Name -->
          <div class="form-group">
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

          <!-- Email -->
          <div class="form-group">
            <label>{{ texts.labels.email }}</label>
            <input 
              v-model="form.email" 
              type="email" 
              :placeholder="texts.placeholders.email"
              class="uc-input"
              :class="{ 'input-error': errors.email }"
            />
            <transition name="fade">
              <span v-if="errors.email" class="field-error">{{ (texts.validation as any)[errors.email] }}</span>
            </transition>
          </div>

          <!-- Password -->
          <div class="form-group">
            <label>{{ texts.labels.password }}</label>
            <div class="password-field">
              <input 
                v-model="form.password" 
                :type="showPassword ? 'text' : 'password'" 
                :placeholder="texts.placeholders.password"
                class="uc-input"
                :class="{ 'input-error': errors.password }"
              />
              <button type="button" class="password-toggle" @click="showPassword = !showPassword">
                <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
              </button>
            </div>
            <transition name="fade">
              <span v-if="errors.password" class="field-error">{{ (texts.validation as any)[errors.password] }}</span>
            </transition>
          </div>

          <!-- Password Confirmation -->
          <div class="form-group">
            <label>{{ texts.labels.password_confirmation }}</label>
            <div class="password-field">
              <input 
                v-model="form.password_confirmation" 
                :type="showPasswordConfirmation ? 'text' : 'password'" 
                :placeholder="texts.placeholders.password_confirmation"
                class="uc-input"
                :class="{ 'input-error': errors.password_confirmation }"
              />
              <button type="button" class="password-toggle" @click="showPasswordConfirmation = !showPasswordConfirmation">
                <svg v-if="!showPasswordConfirmation" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
              </button>
            </div>
            <transition name="fade">
              <span v-if="errors.password_confirmation" class="field-error">{{ (texts.validation as any)[errors.password_confirmation] }}</span>
            </transition>
          </div>

          <!-- Role Select -->
          <div class="form-group">
            <label>{{ texts.labels.role }}</label>
            <div class="uc-select-wrapper">
              <div 
                class="uc-select-trigger" 
                :class="{ active: isRoleOpen, 'select-error': errors.role }"
                @click.stop="isRoleOpen = !isRoleOpen; isStatusOpen = false"
              >
                <span>{{ form.role ? (form.role === 'nurse' ? texts.roles.nurse : texts.roles.head_nurse) : texts.placeholders.selectRole }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </div>
              <transition name="fade-slide">
                <div v-if="isRoleOpen" class="uc-options">
                  <div class="uc-option" @click="form.role = 'nurse'; isRoleOpen = false">
                    {{ texts.roles.nurse }}
                  </div>
                  <div class="uc-option" @click="form.role = 'head_nurse'; isRoleOpen = false">
                    {{ texts.roles.head_nurse }}
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
                :class="{ active: isStatusOpen }"
                @click.stop="isStatusOpen = !isStatusOpen; isRoleOpen = false"
              >
                <span>{{ form.active ? texts.status.active : texts.status.inactive }}</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </div>
              <transition name="fade-slide">
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
          <button type="submit" class="submit-btn" :class="{ loading }" :disabled="loading">
            {{ loading ? texts.saving : texts.save }}
          </button>
        </div>
      </form>
    </section>
  </main>
</template>

