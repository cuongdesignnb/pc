<script setup lang="ts">
import type { MenuItemData } from '~/types/menu'

interface MenuResponse {
  menu?: { id: number; name: string; slug: string }
  items?: MenuItemData[]
}

const config = useRuntimeConfig()
const cart = useCart()
const mobileOpen = ref(false)

const { data: menuData } = await useFetch<MenuResponse>(`${config.public.apiBase}/menus/header`, {
  default: () => ({ menu: { id: 0, name: '', slug: '' }, items: [] }),
  key: 'storefront-header-menu',
})

const items = computed(() => menuData.value?.items ?? [])

onMounted(() => {
  void cart.fetchCart()
})
</script>

<template>
  <header class="site-header sticky top-0 z-50 bg-white">
    <HeaderMainRow @open-mobile-menu="mobileOpen = true" />

    <div class="header-navigation-shell hidden lg:block">
      <div class="pc-container flex min-h-11 items-stretch">
        <HeaderCategoryMenu :items="items" />
        <HeaderNavigation :items="items" />
      </div>
    </div>

    <HeaderMobileDrawer :open="mobileOpen" :items="items" @close="mobileOpen = false" />
  </header>
</template>
