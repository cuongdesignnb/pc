<script setup lang="ts">
import type { HomepageCategoryCard } from '~/types/homepage'

defineProps<{ categories: HomepageCategoryCard[] }>()
const brokenIcons = ref<Set<number>>(new Set())

function markIconBroken(id: number) {
  brokenIcons.value = new Set(brokenIcons.value).add(id)
}
</script>

<template>
  <aside class="home-category-sidebar" aria-label="Danh mục nổi bật">
    <div class="home-category-sidebar-title">Danh mục nổi bật</div>
    <nav class="home-category-sidebar-list">
      <NuxtLink v-for="category in categories.slice(0, 13)" :key="category.id" :to="`/categories/${category.slug}`" class="home-category-sidebar-link">
        <span class="home-category-sidebar-icon">
          <img v-if="category.icon && !brokenIcons.has(category.id)" :src="category.icon" :alt="category.name" @error="markIconBroken(category.id)">
          <span v-else aria-hidden="true">▦</span>
        </span>
        <span class="home-category-sidebar-name">{{ category.name }}</span>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m9 5 7 7-7 7" /></svg>
      </NuxtLink>
    </nav>
    <NuxtLink to="/categories" class="home-category-sidebar-all">Xem tất cả danh mục <span aria-hidden="true">›</span></NuxtLink>
  </aside>
</template>
