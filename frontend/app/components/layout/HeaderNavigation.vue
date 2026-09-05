<script setup lang="ts">
import type { MenuItemData } from '~/types/menu'

const props = defineProps<{ items: MenuItemData[] }>()

interface NavigationTarget {
  key: string
  title: string
  icon: string
  fallback: string
  matches: string[]
}

interface ResolvedNavigationItem extends NavigationTarget {
  item: MenuItemData | null
  path: string
}

const targets: NavigationTarget[] = [
  { key: 'pc-gaming', title: 'PC Gaming', icon: '▦', fallback: '/categories/pc-gaming', matches: ['pc gaming'] },
  { key: 'pc-graphics', title: 'PC Đồ Họa', icon: '◈', fallback: '/categories/pc-do-hoa-render', matches: ['pc đồ họa', 'pc đồ hoạ', 'render'] },
  { key: 'laptop', title: 'Laptop', icon: '▱', fallback: '/categories/laptop', matches: ['laptop'] },
  { key: 'components', title: 'Linh kiện', icon: '⌘', fallback: '/categories/linh-kien-pc', matches: ['linh kiện'] },
  { key: 'monitor', title: 'Màn hình', icon: '▣', fallback: '/categories/man-hinh', matches: ['màn hình'] },
  { key: 'network', title: 'Thiết bị mạng', icon: '◉', fallback: '/products?search=router', matches: ['thiết bị mạng', 'mạng'] },
  { key: 'accessories', title: 'Phụ kiện', icon: '♧', fallback: '/categories/phu-kien', matches: ['phụ kiện'] },
  { key: 'sale', title: 'Khuyến mãi', icon: '%', fallback: '/categories/linh-kien-pc', matches: ['khuyến mãi', 'flash sale', 'sale'] },
  { key: 'news', title: 'Tin tức', icon: '▤', fallback: '/tin-tuc', matches: ['tin tức', 'blog'] },
  { key: 'builder', title: 'PC Builder', icon: '⚙', fallback: '/cau-hinh', matches: ['build pc', 'pc builder', 'cấu hình'] },
]

function flatten(items: MenuItemData[]): MenuItemData[] {
  return items.flatMap(item => [item, ...flatten(item.children || [])])
}

function cleanPath(path: string | null | undefined, fallback: string): string {
  if (!path || path === '#') return fallback
  if (path === '/configurator') return '/cau-hinh'
  if (path === '/blog') return '/tin-tuc'
  if (path === '/about') return '/gioi-thieu'
  if (path === '/contact') return '/lien-he'
  if (path === '/warranty') return '/bao-hanh'
  if (path === '/shipping') return '/van-chuyen'
  return path
}

function resolveUrl(item: MenuItemData | null, fallback: string): string {
  if (!item) return fallback
  if (item.type === 'category' && item.category?.slug) return `/categories/${item.category.slug}`
  return cleanPath(item.url, fallback)
}

const navigationItems = computed<ResolvedNavigationItem[]>(() => {
  const allItems = flatten(props.items)
  return targets.map(target => {
    const exact = allItems.find(candidate => target.matches.includes(candidate.title.trim().toLocaleLowerCase()))
    const item = exact || allItems.find(candidate => {
      const title = candidate.title.trim().toLocaleLowerCase()
      return target.matches.some(match => title === match || title.includes(match))
    }) || null
    return { ...target, item, path: resolveUrl(item, target.fallback) }
  })
})
</script>

<template>
  <nav class="header-navigation" aria-label="Điều hướng chính">
    <ul class="header-navigation-list">
      <li v-for="item in navigationItems" :key="item.key" class="header-navigation-item">
        <NuxtLink
          :to="item.path"
          :target="item.item?.target === '_blank' ? '_blank' : undefined"
          :rel="item.item?.target === '_blank' ? 'noopener noreferrer' : undefined"
          class="header-navigation-link"
        >
          <span class="header-nav-icon" aria-hidden="true">{{ item.icon }}</span>
          <span>{{ item.title }}</span>
          <span v-if="item.item?.badge_text" class="header-nav-badge">{{ item.item.badge_text }}</span>
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
