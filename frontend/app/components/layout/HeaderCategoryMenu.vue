<script setup lang="ts">
import type { MenuItemData } from '~/types/menu'

const props = defineProps<{ items: MenuItemData[] }>()
const open = ref(false)
const activeId = ref<number | null>(null)
const route = useRoute()
watch(() => route.fullPath, close)

function cleanPath(path: string | null | undefined): string {
  const replacements: Record<string, string> = {
    '/configurator': '/cau-hinh',
    '/blog': '/tin-tuc',
    '/about': '/gioi-thieu',
    '/contact': '/lien-he',
    '/warranty': '/bao-hanh',
    '/shipping': '/van-chuyen',
  }
  return (path && path !== '#') ? (replacements[path] || path) : '/'
}

function resolveUrl(item: MenuItemData): string {
  if (item.type === 'category' && item.category?.slug) return `/categories/${item.category.slug}`
  return cleanPath(item.url)
}

function toggle() {
  open.value = !open.value
  if (!open.value) activeId.value = null
}

function close() {
  open.value = false
  activeId.value = null
}

function selectItem(item: MenuItemData) {
  activeId.value = item.children?.length ? item.id : null
}
</script>

<template>
  <div class="header-category-menu" @mouseleave="close" @keydown.esc="close">
    <button
      type="button"
      class="header-category-trigger"
      :aria-expanded="open"
      aria-controls="header-category-panel"
      @click="toggle"
    >
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
      <span>Danh mục sản phẩm</span>
      <svg class="header-category-chevron" :class="{ 'is-open': open }" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
      </svg>
    </button>

    <div v-if="open" id="header-category-panel" class="header-category-panel" @mouseenter="open = true">
      <div class="header-category-panel-list">
        <div v-for="item in props.items" :key="item.id" class="header-category-panel-item" @mouseenter="selectItem(item)">
          <NuxtLink :to="resolveUrl(item)" class="header-category-panel-link" @focus="selectItem(item)" @click="close">
            <span>{{ item.title }}</span>
            <svg v-if="item.children?.length" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 5 7 7-7 7" />
            </svg>
          </NuxtLink>
        </div>
      </div>
      <div v-if="activeId" class="header-category-subpanel">
        <template v-for="item in props.items.filter(candidate => candidate.id === activeId)" :key="item.id">
          <p class="header-category-subtitle">{{ item.title }}</p>
          <NuxtLink v-for="child in item.children" :key="child.id" :to="resolveUrl(child)" @click="close">
            {{ child.title }}
          </NuxtLink>
        </template>
      </div>
    </div>
  </div>
</template>
