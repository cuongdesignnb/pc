<script setup lang="ts">
// Fetch site settings (logo, favicon, etc.) on app init
const { fetchSettings, siteFavicon, siteName } = useSettings()
await fetchSettings()

// Dynamic favicon from admin settings
useHead({
  link: computed(() =>
    siteFavicon.value
      ? [{ rel: 'icon', type: 'image/png', href: siteFavicon.value }]
      : []
  ),
  title: computed(() => siteName.value),
})
</script>

<template>
  <UApp :toaster="{ position: 'top-right', expand: true }">
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
