<script setup lang="ts">
const { data: homepage } = await useHomepage()
const {
  siteName,
  seoTitle,
  seoDescription,
  seoKeywords,
  seoOgImage,
  heroAutoplay,
  heroInterval,
} = useSettings()

const payload = computed(() => homepage.value)

useSeoMeta({
  title: () => seoTitle.value || siteName.value,
  description: () => seoDescription.value,
  keywords: () => seoKeywords.value,
  ogTitle: () => seoTitle.value || siteName.value,
  ogDescription: () => seoDescription.value,
  ogImage: () => seoOgImage.value,
})
</script>

<template>
  <div class="homepage">
    <HomeHero
      :hero-banners="payload.hero_banners"
      :sidebar-banners="payload.sidebar_banners"
      :category-sidebar="payload.category_sidebar"
      :autoplay="heroAutoplay"
      :interval="heroInterval"
    />
    <HomeServiceStrip />
    <HomeFeaturedCategories :categories="payload.featured_categories" />
    <HomeFlashSale
      :enabled="payload.flash_sale.enabled"
      :ends-at="payload.flash_sale.ends_at"
      :products="payload.flash_sale.products"
    />
    <HomeTabbedProducts :best-sellers="payload.best_sellers" />
    <HomePcBuilderBanner :banner="payload.pc_builder_banner" />
    <HomeFeatureColumns
      :combo-banners="payload.combo_banners"
      :setup-banners="payload.setup_banners"
      :accessories="payload.featured_accessories"
    />
    <HomeNews :posts="payload.posts" />
    <HomeTestimonials :testimonials="payload.testimonials" />
  </div>
</template>
