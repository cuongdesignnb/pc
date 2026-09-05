<script setup lang="ts">
import type { MenuItemData } from '~/types/menu'

const props = defineProps<{ open: boolean; items: MenuItemData[] }>()
const emit = defineEmits<{ close: [] }>()
const expanded = ref<number[]>([])
const route = useRoute()
const drawer = ref<HTMLElement | null>(null)
let trigger: HTMLElement | null = null
let previousOverflow = ''

watch(() => props.open, async open => {
  if (!import.meta.client) return
  if (open) {
    trigger = document.activeElement as HTMLElement | null
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    drawer.value?.querySelector<HTMLButtonElement>('button')?.focus()
  } else {
    document.body.style.overflow = previousOverflow
    trigger?.focus()
  }
})

function trapFocus(event: KeyboardEvent) {
  if (event.key === 'Escape') { emit('close'); return }
  if (event.key !== 'Tab') return
  const elements = drawer.value?.querySelectorAll<HTMLElement>('a[href], button:not([disabled])')
  const first = elements?.[0]
  const last = elements?.[elements.length - 1]
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
}

onBeforeUnmount(() => {
  if (import.meta.client && props.open) document.body.style.overflow = previousOverflow
})

watch(() => route.fullPath, () => emit('close'))

function toggle(id: number) {
  expanded.value = expanded.value.includes(id)
    ? expanded.value.filter(itemId => itemId !== id)
    : [...expanded.value, id]
}

function cleanPath(path: string | null | undefined): string {
  const replacements: Record<string, string> = {
    '/configurator': '/cau-hinh',
    '/blog': '/tin-tuc',
    '/about': '/gioi-thieu',
    '/contact': '/lien-he',
    '/warranty': '/bao-hanh',
    '/shipping': '/van-chuyen',
  }
  return path && path !== '#' ? (replacements[path] || path) : '/'
}

function resolveUrl(item: MenuItemData): string {
  if (item.type === 'category' && item.category?.slug) return `/categories/${item.category.slug}`
  return cleanPath(item.url)
}
</script>

<template>
  <Teleport to="body">
    <Transition name="mobile-drawer-fade">
      <div v-if="props.open" class="mobile-drawer-backdrop" @click="emit('close')" />
    </Transition>
    <Transition name="mobile-drawer-slide">
      <aside v-if="props.open" ref="drawer" class="mobile-drawer" role="dialog" aria-modal="true" aria-label="Danh mục sản phẩm" @keydown="trapFocus">
        <div class="mobile-drawer-header">
          <span>Danh mục sản phẩm</span>
          <button type="button" aria-label="Đóng danh mục" @click="emit('close')">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6 6 18" />
            </svg>
          </button>
        </div>
        <nav class="mobile-drawer-nav">
          <div v-for="item in props.items" :key="item.id" class="mobile-drawer-item">
            <div class="mobile-drawer-item-row">
              <NuxtLink :to="resolveUrl(item)" @click="emit('close')">{{ item.title }}</NuxtLink>
              <button v-if="item.children?.length" type="button" :aria-expanded="expanded.includes(item.id)" :aria-label="`Mở ${item.title}`" @click="toggle(item.id)">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" :class="{ 'is-open': expanded.includes(item.id) }">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
                </svg>
              </button>
            </div>
            <div v-if="item.children?.length && expanded.includes(item.id)" class="mobile-drawer-children">
              <NuxtLink v-for="child in item.children" :key="child.id" :to="resolveUrl(child)" @click="emit('close')">
                {{ child.title }}
              </NuxtLink>
            </div>
          </div>
        </nav>
      </aside>
    </Transition>
  </Teleport>
</template>
