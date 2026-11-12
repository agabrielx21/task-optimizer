import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2026-11-01',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  vite: {
    plugins: [tailwindcss()],
  },
  // Server-only (not `public`); overridden at runtime by NUXT_BACKEND_URL / NUXT_USE_MOCK.
  runtimeConfig: {
    backendUrl: 'http://127.0.0.1:8000',
    useMock: false,
  },
  app: {
    head: {
      title: 'Task Assignment Optimizer',
      htmlAttrs: { lang: 'en' },
    },
  },
  typescript: {
    strict: true,
  },
})
