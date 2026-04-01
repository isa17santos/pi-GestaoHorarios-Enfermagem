<script setup lang="ts">
definePageMeta({
  // Use the default public layout for the login page.
  layout: 'default',
})

import logoUrl from '~/assets/images/logotipo.png'

// Keep track of the timeout used to clear feedback messages
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null

const scheduleFeedbackClear = () => {
  // Avoid multiple overlapping timers when feedback changes quickly
  if (feedbackTimeout) {
    clearTimeout(feedbackTimeout)
  }

  // Clear the visible error message after a short delay
  feedbackTimeout = setTimeout(() => {
    errorMessage.value = ''
  }, 4000)
}

onBeforeUnmount(() => {
  // Clean up the timer when the component is destroyed
  if (feedbackTimeout) {
    clearTimeout(feedbackTimeout)
  }
})


const showPassword = ref(false)

const togglePasswordVisibility = () => {
  // Toggle between hidden and visible password input
  showPassword.value = !showPassword.value
}

// Access the auth helpers needed for login and redirect checks
const { login, isLoggedIn } = useAuth()

// Reactive form model used by the login form inputs
const form = reactive({
  email: '',
  password: '',
})

// Reactive UI state for form feedback and submission status
const errorMessage = ref('')
const isSubmitting = ref(false)


// Store the current language selection globally
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Compute the label shown on the language switch button
const localeLabel = computed(() =>
  currentLocale.value === 'pt' ? 'English' : 'Português'
)

// Compute the language indicator shown on the switch
const localeFlag = computed(() =>
  currentLocale.value === 'pt' ? 'en' : 'pt'
)

// Centralize all UI texts so the page can switch language reactively
const texts = computed(() => ({
  brandTitle:
    currentLocale.value === 'pt'
      ? 'Gestão de horários de enfermagem com clareza, equilíbrio e cuidado.'
      : 'Nursing schedule management with clarity, balance and care.',

  brandText:
    currentLocale.value === 'pt'
      ? 'Centraliza acessos, organiza turnos e simplifica o dia a dia da equipa.'
      : 'Centralizes access, organizes shifts and simplifies the team’s daily routine.',

  brandCardTitle:
    currentLocale.value === 'pt'
      ? 'Planeamento mais simples'
      : 'Simpler planning',

  brandCardText:
    currentLocale.value === 'pt'
      ? 'Uma plataforma pensada para equipas de enfermagem, com foco em rapidez e legibilidade.'
      : 'A platform designed for nursing teams, focused on speed and readability.',

  emailLabel: 'Email',

  emailPlaceholder:
    currentLocale.value === 'pt'
      ? 'nome@hospital.pt'
      : 'name@hospital.com',

  passwordLabel:
    currentLocale.value === 'pt'
      ? 'Palavra-passe'
      : 'Password',

  passwordPlaceholder:
    currentLocale.value === 'pt'
      ? 'Introduz a tua palavra-passe'
      : 'Enter your password',


  validation: {
    emailRequired:
      currentLocale.value === 'pt'
        ? 'Introduz o teu email.'
        : 'Please enter your email.',

    emailInvalid:
      currentLocale.value === 'pt'
        ? 'Introduz um endereço de email válido.'
        : 'Please enter a valid email address.',

    passwordRequired:
      currentLocale.value === 'pt'
        ? 'Introduz a tua palavra-passe.'
        : 'Please enter your password.',

    invalidCredentials:
      currentLocale.value === 'pt'
        ? 'Credenciais inválidas. Verifica os teus dados e tenta novamente.'
        : 'Invalid credentials. Please check your details and try again.',
  },
}))


const toggleLanguage = () => {
  // Toggle the page language between Portuguese and English
  currentLocale.value = currentLocale.value === 'pt' ? 'en' : 'pt'
}

// Redirect already authenticated users away from the login page
if (process.client && isLoggedIn.value) {
  await navigateTo('/dashboard')
}

const isValidEmail = (email: string) => {
  // Basic client-side email format validation
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

const handleLogin = async () => {
  // Reset the previous error before validating the new submission
  errorMessage.value = ''

  if (!form.email.trim()) {
    errorMessage.value = texts.value.validation.emailRequired
    scheduleFeedbackClear()
    return
  }

  if (!isValidEmail(form.email)) {
    errorMessage.value = texts.value.validation.emailInvalid
    scheduleFeedbackClear()
    return
  }

  if (!form.password.trim()) {
    errorMessage.value = texts.value.validation.passwordRequired
    scheduleFeedbackClear()
    return
  }

  isSubmitting.value = true

  try {
    // Attempt to log in through the backend API
    const response = await login(form.email, form.password)

    // If the backend requires a password change, redirect the user
    // to the reset-password page with the token and email expected by that flow
    if (response.must_change_password) {
      await navigateTo({
        path: '/reset-password',
        query: {
          token: response.password_reset_token,
          email: response.email,
        },
      })
      return
    }

    // Otherwise, send the authenticated user to the dashboard
    await navigateTo('/dashboard')
  } catch (error: any) {
    // Show the backend error if available, or fallback to a generic login message
    errorMessage.value =
      error?.data?.message || texts.value.validation.invalidCredentials
    scheduleFeedbackClear()
  } finally {
    // Re-enable the submit button after the request completes
    isSubmitting.value = false
  }
}
</script>


<template>
  <main class="login-shell">
    <img :src="logoUrl" alt="Logotipo ShiftCare" class="page-logo">

    <button class="language-switch" type="button" @click="toggleLanguage">
      <span class="language-switch__flag">{{ localeFlag }}</span>
      <span>{{ localeLabel }}</span>
    </button>

    <section class="brand-panel">
      <div class="brand-panel__content">
        <div class="brand-badge">ShiftCare</div>

        <h1 class="brand-title">
          {{ texts.brandTitle }}
        </h1>

        <p class="brand-text">
          {{ texts.brandText }}
        </p>

        <div class="brand-card">
          <div class="brand-card__icon">+</div>
          <div>
            <strong>{{ texts.brandCardTitle }}</strong>
            <p>{{ texts.brandCardText }}</p>
          </div>
        </div>
      </div>

      <div class="brand-glow brand-glow--one" />
      <div class="brand-glow brand-glow--two" />
      <div class="brand-grid" />
    </section>

    <section class="login-panel">
      <div class="login-card">
        <div class="login-card__header">
          <div class="logo-mark" aria-hidden="true">
            <span class="logo-mark__bar logo-mark__bar--h" />
            <span class="logo-mark__bar logo-mark__bar--v" />
            <span class="logo-mark__dot" />
          </div>

          <div>
            <p class="eyebrow">
              {{ currentLocale === 'pt' ? 'Bem-vindo' : 'Welcome' }}
            </p>

            <h2>
              {{ currentLocale === 'pt' ? 'Iniciar sessão' : 'Sign in' }}
            </h2>
          </div>
        </div>

        <p class="login-subtitle">
          {{
            currentLocale === 'pt'
              ? 'Entra com as tuas credenciais para acederes à plataforma.'
              : 'Sign in with your credentials to access the platform.'
          }}
        </p>

        <form class="login-form" novalidate @submit.prevent="handleLogin">
          <label class="field">
            <span>{{ texts.emailLabel }}</span>
            <input v-model="form.email" type="text" name="email" autocomplete="email"
              :placeholder="texts.emailPlaceholder">
          </label>

          <label class="field">
            <span>{{ texts.passwordLabel }}</span>

            <div class="password-field">
              <input v-model="form.password" :type="showPassword ? 'text' : 'password'" name="password"
                autocomplete="current-password" :placeholder="texts.passwordPlaceholder">

              <button type="button" class="password-toggle" :aria-label="showPassword
                ? (currentLocale === 'pt' ? 'Ocultar palavra-passe' : 'Hide password')
                : (currentLocale === 'pt' ? 'Mostrar palavra-passe' : 'Show password')"
                @click="togglePasswordVisibility">
                <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>

                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 3l18 18" />
                  <path d="M10.6 10.7a2 2 0 0 0 2.8 2.8" />
                  <path d="M9.4 5.2A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a13.7 13.7 0 0 1-3.1 4.2" />
                  <path d="M6.6 6.7C4.1 8.4 2.6 12 2.6 12S6.1 19 12 19c1.7 0 3.2-.4 4.5-1" />
                </svg>
              </button>
            </div>

            <NuxtLink to="/forgot-password" class="field__link field__link--below">
              {{
                currentLocale === 'pt'
                  ? 'Esqueceste-te da palavra-passe?'
                  : 'Forgot your password?'
              }}
            </NuxtLink>

          </label>


          <p v-if="errorMessage" class="form-error">
            {{ errorMessage }}
          </p>

          <button class="login-button" type="submit" :disabled="isSubmitting">
            {{
              isSubmitting
                ? (currentLocale === 'pt' ? 'A entrar...' : 'Signing in...')
                : (currentLocale === 'pt' ? 'Iniciar sessão' : 'Sign in')
            }}
          </button>
        </form>
      </div>
    </section>
  </main>
</template>
