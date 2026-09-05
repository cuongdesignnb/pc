<script setup lang="ts">
import type { HomepageCategoryCard } from '~/types/homepage'

defineProps<{ categories: HomepageCategoryCard[] }>()
const brokenImages = ref<Set<number>>(new Set())

function markImageBroken(id: number) {
  brokenImages.value = new Set(brokenImages.value).add(id)
}
</script>

<template>
  <section v-if="categories.length" class="home-section home-featured-categories">
    <div class="pc-container">
      <div class="home-section-heading">
        <h2>Danh mục nổi bật</h2>
        <NuxtLink to="/categories">Xem tất cả <span aria-hidden="true">›</span></NuxtLink>
      </div>
      <div class="home-featured-category-row">
        <NuxtLink v-for="category in categories.slice(0, 9)" :key="category.id" :to="`/categories/${category.slug}`" class="home-featured-category-card">
          <div class="home-featured-category-image">
            <img v-if="category.image && !brokenImages.has(category.id)" :src="category.image" :alt="category.name" loading="lazy" @error="markImageBroken(category.id)">
            <img v-else-if="category.icon && !brokenImages.has(category.id)" :src="category.icon" :alt="category.name" loading="lazy" @error="markImageBroken(category.id)">
            <svg v-else aria-hidden="true" viewBox="0 0 64 64" fill="none" stroke="currentColor"><rect x="12" y="17" width="40" height="30" rx="3" stroke-width="2" /><path stroke-linecap="round" stroke-width="2" d="M21 25h22M21 32h22M21 39h12" /></svg>
          </div>
          <span>{{ category.name }}</span>
        </NuxtLink>
      </div>
    </div>
  </section>
</template>
