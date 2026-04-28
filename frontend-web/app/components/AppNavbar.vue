<script setup lang="ts">
import logoUrl from '~/assets/images/logotipo.png'

const { user, logout } = useAuth()

const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

const localeLabel = computed(() =>
  currentLocale.value === 'pt' ? 'English' : 'Português'
)

const localeFlag = computed(() =>
  currentLocale.value === 'pt' ? 'en' : 'pt'
)

const toggleLanguage = () => {
  currentLocale.value = currentLocale.value === 'pt' ? 'en' : 'pt'
}

const goToDashboard = () => {
  navigateTo('/dashboard')
}

const translatedRole = computed(() => {
  if (!user.value?.role) return ''
  
  const role = user.value.role.trim().toLowerCase()
  const isPt = currentLocale.value === 'pt'
  
  if (role === 'admin') {
    return isPt ? 'Administrador' : 'Admin'
  }
  if (role === 'head nurse' || role === 'head_nurse') {
    return isPt ? 'Enfermeiro Chefe' : 'Head Nurse'
  }
  if (role === 'nurse') {
    return isPt ? 'Enfermeiro' : 'Nurse'
  }
  
  return user.value.role 
})


const texts = computed(() => ({
  logout: currentLocale.value === 'pt' ? 'Terminar sessão' : 'Sign out',
  myProfile: currentLocale.value === 'pt' ? 'Meu Perfil' : 'My Profile',
}))
</script>

<template>
  <div class="dashboard-top-row">
    <!-- Logo -->
    <NuxtLink to="/dashboard" class="dashboard-logo-link">
      <img src="~/assets/images/logotipo.png" alt="ShiftCare" class="dashboard-logo-img" />
    </NuxtLink>

    <!-- Nav bar and Language Switch aligned together -->
    <div class="dashboard-actions-group">
      <header class="dashboard-header">
        <div class="header-right">
          <div class="header-user" v-if="user">
            <span class="user-name">{{ user.name }}</span>
            <span class="user-role">{{ translatedRole }}</span>
          </div>

          <NuxtLink to="/profile" class="header-action-btn" :title="texts.myProfile">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </NuxtLink>

          <button class="header-action-btn header-action-btn--danger" @click="logout" :title="texts.logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round" width="18" height="18">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
          </button>
        </div>
      </header>

      <button class="language-switch dashboard-lang-override" type="button" @click="toggleLanguage">
        <span class="language-switch__flag">{{ localeFlag }}</span>
        <span>{{ localeLabel }}</span>
      </button>
    </div>
  </div>
</template>
