<script setup lang="ts">
import type { HomepageBanner } from '~/types/homepage'
import type { ProductCard } from '~/types/product-detail'

defineProps<{
  comboBanners: HomepageBanner[]
  setupBanners: HomepageBanner[]
  accessories: ProductCard[]
}>()
</script>

<template>
  <section v-if="comboBanners.length || setupBanners.length || accessories.length" class="home-section home-feature-columns-section">
    <div class="pc-container home-feature-columns">
      <HomeComboDeal :banners="comboBanners" />
      <HomeSetupInspiration :banners="setupBanners" />
      <HomeFeaturedAccessories :products="accessories" />
    </div>
  </section>
</template>
