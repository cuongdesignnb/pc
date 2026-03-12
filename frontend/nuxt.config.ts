// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // Modules
  modules: [
    '@nuxt/ui',
    '@nuxt/image',
  ],

  // CSS
  css: ['~/assets/css/main.css'],

  // Runtime config
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api/v1',
      appName: 'PC Shop',
    }
  },

  // Nitro server config
  nitro: {
    routeRules: {
      '/api/**': { proxy: (process.env.NUXT_API_PROXY_TARGET || 'http://nginx') + '/api/**' },
    },
  },

  // App config
  app: {
    head: {
      title: 'PC Shop - Bán PC & Laptop',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Website bán PC, Laptop và linh kiện máy tính với tính năng xây dựng cấu hình thông minh' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' }
      ]
    }
  },

  // Route rules for rendering strategy
  routeRules: {
    '/': { isr: 300 },
    '/products/**': { isr: 60 },
    '/categories/**': { isr: 300 },
    '/blog/**': { swr: 3600 },
    '/configurator/**': { ssr: false },
    '/cart': { ssr: false },
    '/checkout/**': { ssr: false },
    '/account/**': { ssr: false },
  },

  // Disable automatic prefetch on NuxtLinks (too many links → payload storm)
  experimental: {
    defaults: {
      nuxtLink: {
        prefetch: false,
      },
    },
  },

  // TypeScript
  typescript: {
    strict: true
  }
})

