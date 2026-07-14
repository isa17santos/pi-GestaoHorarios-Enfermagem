// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  ssr: false,
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  css: [
    '~/assets/css/main.css',
    '~/assets/css/schedule-create.css',
    '~/assets/css/schedule-create-grid.css',
    '~/assets/css/swaps.css',
    '~/assets/css/swap-create.css',
    '~/assets/css/month-nav.css',
  ],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
    },
  },

  app: {
    head: {
      title: 'ShiftCare',
      link: [
        { rel: 'icon', type: 'image/png', href: '/images/icon_logotipo.png?v=1' }
      ],
      meta: [
        {
          name: 'viewport',
          content: 'width=device-width, initial-scale=1',
        },
        {
          name: 'description',
          content: 'Gestao de horarios de enfermagem com uma experiencia simples e elegante.',
        },
      ],
    },
  },
})
