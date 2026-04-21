Edited dashboardAdmin.css
Edited dashboardAdmin.css
Edited dashboard.vue
Viewed dashboard.vue:1-28
Viewed dashboard.vue:100-167
Viewed dashboard.vue:80-100

Para criares todos os ficheiros `.vue` necessários para as rotas que definimos no *dashboard*, deves executar o seguinte comando no terminal. 

Certifica-te que estás dentro da pasta do frontend (`frontend-web`) antes de correr o comando:

```bash
touch app/pages/human-resources.vue app/pages/vacations.vue app/pages/sick-leaves.vue app/pages/shift-types.vue app/pages/statistics.vue app/pages/profile.vue
```

Isto vai criar os 6 ficheiros em branco.

Se quiseres que estas páginas fiquem logo funcionais e protegidas, recomendo que coles este código base (um *boilerplate*) em cada uma delas para começares:

```vue
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
    <div class="dashboard-top-row">
      <!-- Mantém a coerência da navegação caso queiras voltar ao dashboard -->
      <NuxtLink to="/dashboard">
        <img src="~/assets/images/logotipo.png" alt="ShiftCare" class="dashboard-logo-img" style="cursor: pointer;" />
      </NuxtLink>
    </div>

    <section class="dashboard-content">
      <h1>POR DESENVOLVER</h1>
    </section>
  </main>
</template>

<style src="~/assets/css/dashboardAdmin.css"></style>