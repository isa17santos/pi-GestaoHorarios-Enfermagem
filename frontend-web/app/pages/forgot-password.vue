<script setup lang="ts">
definePageMeta({
  // Use the default public layout for the forgot-password page
  layout: 'default',
})

import logoUrl from '~/assets/images/logotipo.png'

const config = useRuntimeConfig()
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Keep track of the timeout used to clear feedback messages
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null

const scheduleFeedbackClear = () => {
  // Avoid overlapping timers when the feedback message changes
  if (feedbackTimeout) {
    clearTimeout(feedbackTimeout)
  }

  // Clear both success and error messages after a short delay
  feedbackTimeout = setTimeout(() => {
    errorMessage.value = ''
    successMessage.value = ''
  }, 4000)
}

onBeforeUnmount(() => {
  // Clean up the timer when the component is destroyed
  if (feedbackTimeout) {
    clearTimeout(feedbackTimeout)
  }
})


// Reactive form model used by the forgot-password form
const form = reactive({
  email: '',
})

// Reactive UI state for request feedback and loading
const errorMessage = ref('')
const successMessage = ref('')
const isSubmitting = ref(false)

// Compute the label shown on the language switch button
const localeLabel = computed(() =>
  currentLocale.value === 'pt' ? 'English' : 'Português'
)

// Compute the language indicator shown on the switch
const localeFlag = computed(() =>
  currentLocale.value === 'pt' ? 'en' : 'pt'
)

// Centralize all UI strings for reactive language switching
const texts = computed(() => ({
  eyebrow:
    currentLocale.value === 'pt'
      ? 'Recuperação de acesso'
      : 'Access recovery',

  title:
    currentLocale.value === 'pt'
      ? 'Redefinição da palavra-passe'
      : 'Reset Password',

  subtitle:
    currentLocale.value === 'pt'
      ? 'Introduz o teu email e enviaremos um link para redefinires a tua palavra-passe.'
      : 'Enter your email and we will send you a link to reset your password.',

  emailLabel: 'Email',

  emailPlaceholder:
    currentLocale.value === 'pt'
      ? 'nome@hospital.pt'
      : 'name@hospital.com',

  submit:
    currentLocale.value === 'pt'
      ? 'Enviar email'
      : 'Send email',

  submitting:
    currentLocale.value === 'pt'
      ? 'A enviar...'
      : 'Sending...',

  back:
    currentLocale.value === 'pt'
      ? 'Voltar ao login'
      : 'Back to sign in',

  success:
    currentLocale.value === 'pt'
      ? 'Se o email existir, será enviado um link de recuperação.'
      : 'If the email exists, a recovery link will be sent.',

  validation: {
    emailRequired:
      currentLocale.value === 'pt'
        ? 'Introduz o teu email.'
        : 'Please enter your email.',

    emailInvalid:
      currentLocale.value === 'pt'
        ? 'Introduz um endereço de email válido.'
        : 'Please enter a valid email address.',
  },

  genericError:
    currentLocale.value === 'pt'
      ? 'Não foi possível enviar o email de recuperação. Tenta novamente.'
      : 'Could not send the recovery email. Please try again.',
}))

const toggleLanguage = () => {
  // Toggle the page language between Portuguese and English
  currentLocale.value = currentLocale.value === 'pt' ? 'en' : 'pt'
}

const isValidEmail = (email: string) => {
  // Basic client-side email format validation
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

const handleSubmit = async () => {
  // Reset previous feedback before validating a new submission
  errorMessage.value = ''
  successMessage.value = ''

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

  isSubmitting.value = true

  try {
    // Request a password recovery email from the backend
    const response = await $fetch<{ message: string }>(
      `${config.public.apiBase}/password-recovery/email`,
      {
        method: 'POST',
        body: {
          email: form.email,
        },
      }
    )

    // Show the backend message or fallback success text
    successMessage.value = response.message || texts.value.success
    scheduleFeedbackClear()

    // Clear the email field after a successful submission
    form.email = ''
  } catch (error: any) {
    // Show a field-level backend error if available, otherwise use generic feedback
    errorMessage.value =
      error?.data?.errors?.email?.[0]
      || error?.data?.message
      || texts.value.genericError
  } finally {
    // Re-enable the submit button after the request completes
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="dashboard-page reset-password-page">
    <img :src="logoUrl" alt="Logotipo ShiftCare" class="page-logo">

    <button class="language-switch" type="button" @click="toggleLanguage">
      <span class="language-switch__flag">{{ localeFlag }}</span>
      <span>{{ localeLabel }}</span>
    </button>

    <section class="login-card reset-password-card">
      <div class="login-card__header">
        <div class="logo-mark" aria-hidden="true">
          <span class="logo-mark__bar logo-mark__bar--h" />
          <span class="logo-mark__bar logo-mark__bar--v" />
          <span class="logo-mark__dot" />
        </div>

        <div>
          <p class="eyebrow">{{ texts.eyebrow }}</p>
          <h2>{{ texts.title }}</h2>
        </div>
      </div>

      <p class="login-subtitle">
        {{ texts.subtitle }}
      </p>

      <form class="login-form" novalidate @submit.prevent="handleSubmit">
        <label class="field">
          <span>{{ texts.emailLabel }}</span>
          <input
            v-model="form.email"
            type="email"
            name="email"
            autocomplete="email"
            :placeholder="texts.emailPlaceholder"
          >
        </label>

        <p v-if="errorMessage" class="form-error">
          {{ errorMessage }}
        </p>

        <p v-if="successMessage" class="form-success">
          {{ successMessage }}
        </p>

        <button class="login-button" type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? texts.submitting : texts.submit }}
        </button>

        <NuxtLink to="/" class="field__link reset-password__back-link">
          {{ texts.back }}
        </NuxtLink>
      </form>
    </section>
  </main>
</template>
