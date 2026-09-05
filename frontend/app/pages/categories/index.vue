<script setup lang="ts">
import type { Category } from '~/types'

const config = useRuntimeConfig()
const { siteName } = useSettings()

const { data, status } = await useFetch<Category[]>(`${config.public.apiBase}/categories`, {
  default: () => [],
})

const categories = computed(() => data.value ?? [])
const brokenImages = ref<Set<number>>(new Set())

function markImageBroken(id: number) {
  brokenImages.value = new Set(brokenImages.value).add(id)
}

useSeoMeta({
  title: () => `Danh mục sản phẩm - ${siteName.value}`,
  description: () => `Khám phá PC, laptop, linh kiện và phụ kiện tại ${siteName.value}.`,
})
</script>

<template>
  <div class="category-directory-page">
    <div class="pc-container">
      <nav class="directory-breadcrumb" aria-label="Breadcrumb">
        <NuxtLink to="/">Trang chủ</NuxtLink>
        <span aria-hidden="true">/</span>
        <span>Danh mục sản phẩm</span>
      </nav>

      <div class="directory-heading">
        <div>
          <span class="directory-kicker">{{ siteName }}</span>
          <h1>Danh mục sản phẩm</h1>
          <p>Chọn nhóm sản phẩm để xem cấu hình và ưu đãi phù hợp.</p>
        </div>
      </div>

      <div v-if="status === 'pending'" class="category-directory-grid">
        <div v-for="index in 8" :key="index" class="category-directory-skeleton" />
      </div>

      <div v-else-if="categories.length" class="category-directory-grid">
        <article v-for="category in categories" :key="category.id" class="category-directory-card">
          <NuxtLink :to="`/${category.slug}`" class="category-directory-card-main">
            <div class="category-directory-image">
              <img v-if="category.image && !brokenImages.has(category.id)" :src="category.image" :alt="category.name" loading="lazy" @error="markImageBroken(category.id)">
              <img v-else-if="category.icon && !brokenImages.has(category.id)" :src="category.icon" :alt="category.name" loading="lazy" @error="markImageBroken(category.id)">
              <svg v-else aria-hidden="true" viewBox="0 0 64 64" fill="none" stroke="currentColor">
                <rect x="12" y="16" width="40" height="32" rx="4" stroke-width="2" />
                <path stroke-linecap="round" stroke-width="2" d="M20 24h24M20 32h16M20 40h10" />
              </svg>
            </div>
            <span>
              <strong>{{ category.name }}</strong>
              <small>{{ category.children?.length || 0 }} nhóm sản phẩm</small>
            </span>
            <span class="category-directory-arrow" aria-hidden="true">›</span>
          </NuxtLink>
          <div v-if="category.children?.length" class="category-directory-children">
            <NuxtLink v-for="child in category.children.slice(0, 5)" :key="child.id" :to="`/${child.slug}`">
              {{ child.name }}
            </NuxtLink>
          </div>
        </article>
      </div>

      <div v-else class="directory-empty">
        <h2>Danh mục đang được cập nhật</h2>
        <NuxtLink to="/" class="button button-primary">Về trang chủ</NuxtLink>
      </div>
    </div>
  </div>
</template>
