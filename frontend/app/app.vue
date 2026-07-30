<script setup lang="ts">
const {
  fetchSettings,
  getString,
  siteFavicon,
  siteName,
  seoTitle,
  seoDescription,
  seoKeywords,
  seoOgImage,
} = useSettings()
await fetchSettings()

// Refresh once in the browser so an ISR-cached page cannot keep stale admin values.
onMounted(() => fetchSettings(true))

const analyticsId = computed(() => {
  const value = getString('google_analytics_id')
  return /^(G|UA)-[A-Z0-9-]+$/i.test(value) ? value : ''
})
const tagManagerId = computed(() => {
  const value = getString('google_tag_manager_id')
  return /^GTM-[A-Z0-9]+$/i.test(value) ? value : ''
})
const facebookPixelId = computed(() => {
  const value = getString('facebook_pixel_id')
  return /^\d+$/.test(value) ? value : ''
})

useHead(() => ({
  title: seoTitle.value || siteName.value,
  link: siteFavicon.value
    ? [{ rel: 'icon', type: 'image/png', href: siteFavicon.value }]
    : [],
  meta: [
    { name: 'description', content: seoDescription.value },
    { name: 'keywords', content: seoKeywords.value },
    { property: 'og:title', content: seoTitle.value || siteName.value },
    { property: 'og:description', content: seoDescription.value },
    { property: 'og:image', content: seoOgImage.value },
  ].filter(item => item.content),
  script: [
    ...(analyticsId.value ? [
      { src: `https://www.googletagmanager.com/gtag/js?id=${analyticsId.value}`, async: true },
      { innerHTML: `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${analyticsId.value}');` },
    ] : []),
    ...(tagManagerId.value ? [
      { innerHTML: `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${tagManagerId.value}');` },
    ] : []),
    ...(facebookPixelId.value ? [
      { innerHTML: `!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','${facebookPixelId.value}');fbq('track','PageView');` },
    ] : []),
  ],
}))
</script>

<template>
  <UApp :toaster="{ position: 'top-right', expand: true }">
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
