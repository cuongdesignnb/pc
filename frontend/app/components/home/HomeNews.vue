<script setup lang="ts">
import type { HomepagePost } from '~/types/homepage'

defineProps<{ posts: HomepagePost[] }>()
const brokenImages = ref<Set<number>>(new Set())

function markImageBroken(id: number) {
  brokenImages.value = new Set(brokenImages.value).add(id)
}

function postDate(value: string | null): string {
  if (!value) return ''
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit' }).format(date)
}
</script>

<template>
  <section v-if="posts.length" class="home-section home-news-section">
    <div class="pc-container">
      <div class="home-section-heading"><h2>TIN TỨC - CÔNG NGHỆ</h2><NuxtLink to="/tin-tuc">Xem tất cả <span aria-hidden="true">›</span></NuxtLink></div>
      <div class="home-news-grid">
        <NuxtLink v-for="post in posts.slice(0, 4)" :key="post.id" :to="`/tin-tuc/${post.slug}`" class="home-news-card">
          <span class="home-news-image">
            <img v-if="post.featured_image && !brokenImages.has(post.id)" :src="post.featured_image" :alt="post.title" loading="lazy" @error="markImageBroken(post.id)">
            <svg v-else aria-hidden="true" viewBox="0 0 80 52" fill="none" stroke="currentColor"><rect x="7" y="7" width="66" height="38" rx="3" stroke-width="2" /><path stroke-linecap="round" stroke-width="2" d="m16 36 13-12 9 8 8-7 18 11" /></svg>
          </span>
          <span class="home-news-copy"><strong>{{ post.title }}</strong><small>{{ postDate(post.published_at) }} <span v-if="post.view_count"> · {{ post.view_count }} lượt xem</span></small></span>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>
