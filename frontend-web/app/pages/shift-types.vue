<script setup lang="ts">
// Protege a página para que apenas utilizadores autenticados tenham acesso
definePageMeta({
  middleware: 'auth',
})

// Opcional: Vai buscar a função de logout e os dados do utilizador
const { user, logout } = useAuth()
</script>

<template>
  <main class="dashboard-layout">
    <AppNavbar />

    <section class="dashboard-content">
      <h1>POR DESENVOLVER</h1>
    </section>
  </main>
</template>
