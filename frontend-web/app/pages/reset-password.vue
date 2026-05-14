<script setup lang="ts">
definePageMeta({
    // Use the default public layout for the reset-password page
    layout: 'default',
})

import logoUrl from '~/assets/images/logotipo.png'

const config = useRuntimeConfig()
const route = useRoute()
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Reactive form model used by the reset-password form
const form = reactive({
    email: '',
    token: '',
    password: '',
    password_confirmation: '',
})


// Keep track of the timeout used to clear feedback messages
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null

const scheduleFeedbackClear = () => {
    // Avoid overlapping timers when feedback changes
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout)
    }

    // Clear both error and success messages after a short delay
    feedbackTimeout = setTimeout(() => {
        errorMessage.value = ''
        successMessage.value = ''
    }, 4000)
}

// Reactive UI state used by the page
const errorMessage = ref('')
const successMessage = ref('')
const isSubmitting = ref(false)
const isValidToken = ref(false)
const isCheckingToken = ref(true)

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const togglePasswordVisibility = () => {
    // Toggle visibility of the new password field
    showPassword.value = !showPassword.value
}

const togglePasswordConfirmationVisibility = () => {
    // Toggle visibility of the confirmation password field
    showPasswordConfirmation.value = !showPasswordConfirmation.value
}

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
            ? 'Alterar palavra-passe'
            : 'Change password',

    subtitle:
        currentLocale.value === 'pt'
            ? 'Introduz a nova palavra-passe para concluir o processo de alteração da palavra-passe.'
            : 'Enter your new password to complete the changing password process.',

    passwordLabel:
        currentLocale.value === 'pt'
            ? 'Nova palavra-passe'
            : 'New password',

    passwordConfirmationLabel:
        currentLocale.value === 'pt'
            ? 'Confirmar palavra-passe'
            : 'Confirm password',

    passwordPlaceholder:
        currentLocale.value === 'pt'
            ? 'Introduz a nova palavra-passe'
            : 'Enter your new password',

    passwordConfirmationPlaceholder:
        currentLocale.value === 'pt'
            ? 'Confirma a nova palavra-passe'
            : 'Confirm your new password',

    submit:
        currentLocale.value === 'pt'
            ? 'Guardar nova palavra-passe'
            : 'Save new password',

    submitting:
        currentLocale.value === 'pt'
            ? 'A guardar...'
            : 'Saving...',

    back:
        currentLocale.value === 'pt'
            ? 'Voltar ao login'
            : 'Back to sign in',

    success:
        currentLocale.value === 'pt'
            ? 'Password redefinida com sucesso. Vais ser redirecionado para o login.'
            : 'Password reset successfully. You will be redirected to sign in.',

    tokenInvalid:
        currentLocale.value === 'pt'
            ? 'O link é inválido ou expirou.'
            : 'The link is invalid or has expired.',

    passwordHint:
        currentLocale.value === 'pt'
            ? 'A palavra-passe deve ter pelo menos 8 caracteres, uma maiúscula, uma minúscula e um carácter especial.'
            : 'Password must be at least 8 characters long and include uppercase, lowercase and a special character.',

    validation: {
        passwordRequired:
            currentLocale.value === 'pt'
                ? 'Introduz a nova palavra-passe.'
                : 'Please enter your new password.',

        passwordConfirmationRequired:
            currentLocale.value === 'pt'
                ? 'Confirma a nova palavra-passe.'
                : 'Please confirm your new password.',

        passwordMismatch:
            currentLocale.value === 'pt'
                ? 'A confirmação da palavra-passe não corresponde.'
                : 'Password confirmation does not match.',
    },
}))

const toggleLanguage = () => {
    // Toggle the page language between Portuguese and English
    currentLocale.value = currentLocale.value === 'pt' ? 'en' : 'pt'
}

const validateToken = async () => {
    errorMessage.value = ''
    isCheckingToken.value = true

    // Read the token and email from the query string
    const token = String(route.query.token || '')
    const email = String(route.query.email || '')

    // Mirror the query values into the reactive form model
    form.token = token
    form.email = email

    // The page cannot proceed without both token and email
    if (!token || !email) {
        isValidToken.value = false
        errorMessage.value = texts.value.tokenInvalid
        isCheckingToken.value = false
        scheduleFeedbackClear()
        return
    }

    try {
        // Ask the backend to validate the recovery token before showing the form
        await $fetch(`${config.public.apiBase}/password-recovery/validate-token`, {
            method: 'GET',
            query: {
                token,
                email,
            },
        })

        isValidToken.value = true
    } catch (error: any) {
        // Mark the token as invalid and show the most relevant backend message
        isValidToken.value = false
        errorMessage.value =
            error?.data?.errors?.token?.[0]
            || error?.data?.message
            || texts.value.tokenInvalid
        scheduleFeedbackClear()
    } finally {
        // Stop the loading state after validation completes
        isCheckingToken.value = false
    }
}

const handleSubmit = async () => {
    // Reset previous feedback before validating a new submission
    errorMessage.value = ''
    successMessage.value = ''

    if (!form.password.trim()) {
        errorMessage.value = texts.value.validation.passwordRequired
        scheduleFeedbackClear()
        return
    }

    if (!form.password_confirmation.trim()) {
        errorMessage.value = texts.value.validation.passwordConfirmationRequired
        scheduleFeedbackClear()
        return
    }

    if (form.password !== form.password_confirmation) {
        errorMessage.value = texts.value.validation.passwordMismatch
        scheduleFeedbackClear()
        return
    }

    isSubmitting.value = true

    try {
        // Submit the new password together with the validated token and email
        const response = await $fetch<{ message: string }>(
            `${config.public.apiBase}/password-recovery/reset`,
            {
                method: 'POST',
                body: {
                    token: form.token,
                    email: form.email,
                    password: form.password,
                    password_confirmation: form.password_confirmation,
                },
            }
        )

        // Show the success message returned by the backend
        successMessage.value = response.message || texts.value.success

        // Clear sensitive password fields after a successful reset
        form.password = ''
        form.password_confirmation = ''

        scheduleFeedbackClear()

        // Redirect the user back to the login page after a short delay
        setTimeout(() => {
            navigateTo('/')
        }, 2000)
    } catch (error: any) {
        // Keep this specific backend password complexity error aligned with the current UI language.
        const passwordError = error?.data?.errors?.password?.[0]
        const backendMessage = error?.data?.message
        const isPortuguesePasswordComplexity =
            passwordError === 'A password deve ter pelo menos 8 caracteres, uma letra maiúscula, uma letra minúscula e um caracter especial.'
            || backendMessage === 'A password deve ter pelo menos 8 caracteres, uma letra maiúscula, uma letra minúscula e um caracter especial.'

        errorMessage.value =
            isPortuguesePasswordComplexity
                ? texts.value.passwordHint
                : passwordError
                || error?.data?.errors?.token?.[0]
                || backendMessage
                || texts.value.tokenInvalid
        scheduleFeedbackClear()
    } finally {
        // Re-enable the submit button after the request completes
        isSubmitting.value = false
    }
}

onMounted(() => {
    // Validate the token as soon as the page loads
    validateToken()
})
</script>

<template>
    <main class="dashboard-page reset-password-page">
        <NuxtLink to="/">
            <img :src="logoUrl" alt="Logotipo ShiftCare" class="page-logo">
        </NuxtLink>

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

            <p v-if="isCheckingToken" class="password-hint">
                {{ currentLocale === 'pt' ? 'A validar o link...' : 'Validating link...' }}
            </p>

            <form v-else-if="isValidToken" class="login-form" novalidate @submit.prevent="handleSubmit">
                <label class="field">
                    <span>{{ texts.passwordLabel }}</span>

                    <div class="password-field">
                        <input v-model="form.password" :type="showPassword ? 'text' : 'password'" name="password"
                            autocomplete="new-password" :placeholder="texts.passwordPlaceholder">

                        <button type="button" class="password-toggle" :aria-label="showPassword
                            ? (currentLocale === 'pt' ? 'Ocultar palavra-passe' : 'Hide password')
                            : (currentLocale === 'pt' ? 'Mostrar palavra-passe' : 'Show password')"
                            @click="togglePasswordVisibility">
                            <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
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
                </label>


                <label class="field">
                    <span>{{ texts.passwordConfirmationLabel }}</span>

                    <div class="password-field">
                        <input v-model="form.password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'" name="password_confirmation"
                            autocomplete="new-password" :placeholder="texts.passwordConfirmationPlaceholder">

                        <button type="button" class="password-toggle" :aria-label="showPasswordConfirmation
                            ? (currentLocale === 'pt' ? 'Ocultar palavra-passe' : 'Hide password')
                            : (currentLocale === 'pt' ? 'Mostrar palavra-passe' : 'Show password')"
                            @click="togglePasswordConfirmationVisibility">
                            <svg v-if="!showPasswordConfirmation" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
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
                </label>


                <p class="password-hint">
                    {{ texts.passwordHint }}
                </p>

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

            <div v-else class="login-form">
                <p v-if="errorMessage" class="form-error">
                    {{ errorMessage }}
                </p>

                <NuxtLink to="/" class="field__link reset-password__back-link">
                    {{ texts.back }}
                </NuxtLink>
            </div>
        </section>
    </main>
</template>
