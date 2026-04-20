<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { user, logout } = useAuth()

// Only users with admin/head nurse profiles should see schedule creation actions.
const canCreateSchedule = computed(() => {
  const normalizedRole = user.value?.role?.trim().toLowerCase() || ''
  return normalizedRole === 'admin' || normalizedRole === 'head nurse' || normalizedRole === 'head_nurse'
})
</script>

<template>
  <main class="dashboard-page">
    <div class="dashboard-card">
      <p class="eyebrow">Sessao iniciada</p>
      <h1>Dashboard</h1>

      <p v-if="user">
        Bem-vindo, <strong>{{ user.name }}</strong>.
      </p>

      <p v-if="user">
        Email: {{ user.email }}<br>
        Perfil: {{ user.role }}
      </p>

      <button class="login-button dashboard-button" @click="logout">
        Terminar sessao
      </button>

      <NuxtLink
        v-if="canCreateSchedule"
        to="/schedule-create"
        class="login-button dashboard-button dashboard-link-button"
      >
        Criar horario
      </NuxtLink>
    </div>
  </main>
</template>
