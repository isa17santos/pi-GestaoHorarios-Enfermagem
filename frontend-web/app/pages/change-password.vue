<script setup lang="ts">
definePageMeta({
    layout: 'default',
    middleware: 'auth',
})
const config = useRuntimeConfig()
const currentLocale = useState<'pt' | 'en'>('locale', () => 'pt')

// Reactive form model used by the change-password form
const form = reactive({
    current_password: '',
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

const showCurrentPassword = ref(false)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const toggleCurrentPasswordVisibility = () => {
    // Toggle visibility of the current password field
    showCurrentPassword.value = !showCurrentPassword.value
}

const togglePasswordVisibility = () => {
    // Toggle visibility of the new password field
    showPassword.value = !showPassword.value
}

const togglePasswordConfirmationVisibility = () => {
    // Toggle visibility of the confirmation password field
    showPasswordConfirmation.value = !showPasswordConfirmation.value
}

// Centralize all UI strings for reactive language switching
const texts = computed(() => ({
    eyebrow:
        currentLocale.value === 'pt'
            ? 'Definições de conta'
            : 'Account settings',

    title:
        currentLocale.value === 'pt'
            ? 'Alterar palavra-passe'
            : 'Change password',

    subtitle:
        currentLocale.value === 'pt'
            ? 'Introduz a tua palavra-passe actual e define uma nova.'
            : 'Enter your current password and set a new one.',

    currentPasswordLabel:
        currentLocale.value === 'pt'
            ? 'Palavra-passe actual'
            : 'Current password',

    currentPasswordPlaceholder:
        currentLocale.value === 'pt'
            ? 'Introduz a tua palavra-passe actual'
            : 'Enter your current password',

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
            ? 'Guardar alterações'
            : 'Save changes',

    submitting:
        currentLocale.value === 'pt'
            ? 'A guardar...'
            : 'Saving...',

    back:
        currentLocale.value === 'pt'
            ? 'Voltar'
            : 'Back',

    success:
        currentLocale.value === 'pt'
            ? 'Palavra-passe alterada com sucesso.'
            : 'Password changed successfully.',

    passwordHint:
        currentLocale.value === 'pt'
            ? 'A palavra-passe deve ter pelo menos 8 caracteres, uma maiúscula, uma minúscula e um carácter especial.'
            : 'Password must be at least 8 characters long and include uppercase, lowercase and a special character.',

    validation: {
        currentPasswordRequired:
            currentLocale.value === 'pt'
                ? 'Introduz a palavra-passe actual.'
                : 'Please enter your current password.',

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

const handleSubmit = async () => {
    // Reset previous feedback before validating a new submission
    errorMessage.value = ''
    successMessage.value = ''

    if (!form.current_password.trim()) {
        errorMessage.value = texts.value.validation.currentPasswordRequired
        scheduleFeedbackClear()
        return
    }

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
        // Submit the current password together with the new password and confirmation
        const response = await $fetch<{ message: string }>(
            `${config.public.apiBase}/profile/change-password`,
            {
                method: 'POST',
                body: {
                    current_password: form.current_password,
                    password: form.password,
                    password_confirmation: form.password_confirmation,
                },
            }
        )

        // Show the success message returned by the backend
        successMessage.value = response.message || texts.value.success

        // Clear sensitive password fields after a successful change
        form.current_password = ''
        form.password = ''
        form.password_confirmation = ''

        scheduleFeedbackClear()
    } catch (error: any) {
        errorMessage.value =
            error?.data?.errors?.current_password?.[0]
            || error?.data?.errors?.password?.[0]
            || error?.data?.message
        scheduleFeedbackClear()
    } finally {
        // Re-enable the submit button after the request completes
        isSubmitting.value = false
    }
}
</script>

<template>
    <main class="dashboard-layout change-password-page">
        <AppNavbar />

        <div class="change-password-body">
            <NuxtLink to="/profile" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 19 5 12 12 5" />
                </svg>
                {{ texts.back }}
            </NuxtLink>

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
                    <span>{{ texts.currentPasswordLabel }}</span>

                    <div class="password-field">
                        <input v-model="form.current_password" :type="showCurrentPassword ? 'text' : 'password'"
                            name="current_password" autocomplete="current-password"
                            :placeholder="texts.currentPasswordPlaceholder">

                        <button type="button" class="password-toggle" :aria-label="showCurrentPassword
                            ? (currentLocale === 'pt' ? 'Ocultar palavra-passe' : 'Hide password')
                            : (currentLocale === 'pt' ? 'Mostrar palavra-passe' : 'Show password')"
                            @click="toggleCurrentPasswordVisibility">
                            <svg v-if="!showCurrentPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor"
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
            </form>
            </section>
        </div>
    </main>
</template>
