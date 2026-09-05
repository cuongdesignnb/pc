<script setup lang="ts">
import type { HomepageBanner } from '~/types/homepage'

defineProps<{ banners: HomepageBanner[] }>()
</script>

<template>
  <article v-if="banners.length" class="home-feature-card home-setup-inspiration">
    <div class="home-feature-card-heading">
      <div><h3>SETUP GAMING INSPIRATION</h3><p>Gợi ý góc chơi đỉnh cao</p></div>
      <NuxtLink to="/tin-tuc">Xem tất cả <span aria-hidden="true">›</span></NuxtLink>
    </div>
    <div class="home-setup-grid">
      <NuxtLink v-for="banner in banners.slice(0, 4)" :key="banner.id" :to="banner.link || '/tin-tuc'">
        <img v-if="banner.image" :src="banner.image" :alt="banner.title" loading="lazy">
        <span>{{ banner.title }}</span>
      </NuxtLink>
    </div>
  </article>
</template>
