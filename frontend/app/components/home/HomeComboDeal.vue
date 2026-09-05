<script setup lang="ts">
import type { HomepageBanner } from '~/types/homepage'

defineProps<{ banners: HomepageBanner[] }>()
</script>

<template>
  <article v-if="banners.length" class="home-feature-card home-combo-deal">
    <div class="home-feature-card-heading">
      <div><h3>COMBO SIÊU ƯU ĐÃI</h3><p>Mua combo - tiết kiệm hơn</p></div>
      <NuxtLink :to="banners[0]?.link || '/categories/phu-kien'">Xem tất cả <span aria-hidden="true">›</span></NuxtLink>
    </div>
    <NuxtLink :to="banners[0]?.link || '/categories/phu-kien'" class="home-combo-content">
      <img v-if="banners[0]?.image" :src="banners[0].image" :alt="banners[0].title" loading="lazy">
      <div><strong>{{ banners[0]?.title }}</strong><small>{{ banners[0]?.description }}</small></div>
    </NuxtLink>
    <div class="home-feature-tags"><span>PC Gaming</span><span>Màn hình</span><span>Gear</span></div>
  </article>
</template>
