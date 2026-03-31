<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { user, logout } = useAuth()
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
    </div>
  </main>
</template>
