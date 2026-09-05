<script setup lang="ts">
import type { HomepageBanner } from '~/types/homepage'

defineProps<{ banner: HomepageBanner | null }>()

function linkFor(banner: HomepageBanner, secondary = false): string {
  if (secondary) return banner.metadata?.cta2_link || '/tin-tuc'
  return banner.metadata?.cta_link || banner.link || '/cau-hinh'
}
</script>

<template>
  <section v-if="banner" class="home-section home-pc-builder-section">
    <div class="pc-container">
      <div class="home-pc-builder-banner">
        <img v-if="banner.image" :src="banner.image" :alt="banner.title" loading="lazy">
        <div class="home-pc-builder-overlay" />
        <div class="home-pc-builder-copy">
          <span>{{ banner.badge || 'PC BUILDER' }}</span>
          <h2>{{ banner.title }}</h2>
          <p>{{ banner.description }}</p>
        </div>
        <div class="home-pc-builder-actions">
          <NuxtLink :to="linkFor(banner)" class="button button-orange">{{ banner.metadata?.cta_label || 'Bắt đầu build PC ngay' }}</NuxtLink>
          <NuxtLink :to="linkFor(banner, true)" class="button button-light">{{ banner.metadata?.cta2_label || 'Hướng dẫn build PC' }}</NuxtLink>
        </div>
      </div>
    </div>
  </section>
</template>
