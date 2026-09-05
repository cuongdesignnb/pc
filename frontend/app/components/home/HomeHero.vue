<script setup lang="ts">
import type { HomepageBanner, HomepageCategoryCard } from '~/types/homepage'

withDefaults(defineProps<{
  heroBanners: HomepageBanner[]
  sidebarBanners: HomepageBanner[]
  categorySidebar: HomepageCategoryCard[]
  autoplay?: boolean
  interval?: number
}>(), {
  autoplay: true,
  interval: 5000,
})
</script>

<template>
  <section class="home-hero-section">
    <div class="pc-container">
      <div class="home-hero-grid">
        <HomeCategorySidebar :categories="categorySidebar" />
        <HomeHeroSlider :banners="heroBanners.slice(0, 3)" :autoplay="autoplay" :interval="interval" />
        <HomeSidePromotions :banners="sidebarBanners.slice(0, 3)" />
      </div>
    </div>
  </section>
</template>
