<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})
</script>

<template>
  <main class="dashboard-layout">
    <AppNavbar />

    <section class="dashboard-content">
      <div class="dashboard-greeting" style="display: flex; justify-content: center; align-items: center; min-height: 50vh;">
        <h1 style="font-weight: 800; opacity: 0.8; letter-spacing: -0.02em; color: #4b2c85;">
          POR DESENVOLVER
        </h1>
      </div>
    </section>
  </main>
</template>

<style scoped>
.dashboard-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  padding: 24px 40px;
  max-width: 1400px;
  margin: 0 auto;
}

.dashboard-content {
  flex: 1;
}
</style>
