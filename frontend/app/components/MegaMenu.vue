<script setup lang="ts">
interface MenuItemData {
  id: number
  title: string
  url: string | null
  type: string
  icon: string | null
  badge_text: string | null
  badge_color: string | null
  css_class: string | null
  target: string
  is_mega: boolean
  mega_columns: number
  description: string | null
  image: string | null
  children: MenuItemData[]
  category?: { slug: string } | null
}

interface MenuResponse {
  menu: { id: number; name: string; slug: string }
  items: MenuItemData[]
}

const config = useRuntimeConfig()

const { data: menuData } = await useFetch<MenuResponse>(`${config.public.apiBase}/menus/header`, {
  default: () => ({ menu: { id: 0, name: '', slug: '' }, items: [] })
})

const items = computed(() => menuData.value?.items ?? [])

const activeMenu = ref<number | null>(null)
let closeTimer: ReturnType<typeof setTimeout> | null = null

function openMenu(id: number) {
  if (closeTimer) clearTimeout(closeTimer)
  activeMenu.value = id
}

function scheduleClose() {
  closeTimer = setTimeout(() => {
    activeMenu.value = null
  }, 200)
}

function cancelClose() {
  if (closeTimer) clearTimeout(closeTimer)
}

function resolveUrl(item: MenuItemData): string {
  if (item.type === 'category' && item.category) {
    return '/' + item.category.slug
  }
  return item.url ?? '#'
}

const badgeStyles: Record<string, string> = {
  red: 'bg-red-500 text-white',
  green: 'bg-emerald-500 text-white',
  blue: 'bg-blue-500 text-white',
  orange: 'bg-orange-500 text-white',
}

// Mobile menu
const mobileOpen = ref(false)
const mobileExpanded = ref<number | null>(null)

function toggleMobileSubmenu(id: number) {
  mobileExpanded.value = mobileExpanded.value === id ? null : id
}
</script>

<template>
  <div>
    <!-- ===== DESKTOP NAV ===== -->
    <nav class="hidden lg:block relative">
      <ul class="flex items-center gap-1">
        <li
          v-for="item in items"
          :key="item.id"
          class="static"
          @mouseenter="item.is_mega ? openMenu(item.id) : undefined"
          @mouseleave="item.is_mega ? scheduleClose() : undefined"
        >
          <NuxtLink
            :to="resolveUrl(item)"
            :target="item.target"
            :class="[
              'relative flex items-center gap-1 px-3 py-2.5 text-[13px] font-semibold tracking-wide uppercase transition-colors',
              activeMenu === item.id
                ? 'text-primary-600'
                : 'text-gray-700 hover:text-primary-600',
            ]"
          >
            <span>{{ item.title }}</span>
            <span
              v-if="item.badge_text"
              :class="[
                'text-[9px] font-bold px-1.5 py-0.5 rounded leading-none ml-0.5',
                badgeStyles[item.badge_color ?? 'red'] ?? 'bg-red-500 text-white',
              ]"
            >{{ item.badge_text }}</span>
            <svg
              v-if="item.is_mega && item.children.length"
              class="w-3 h-3 opacity-40 transition-transform"
              :class="{ 'rotate-180': activeMenu === item.id }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
            </svg>
            <!-- active indicator -->
            <span
              v-if="activeMenu === item.id"
              class="absolute bottom-0 left-3 right-3 h-0.5 bg-primary-600 rounded-full"
            />
          </NuxtLink>

          <!-- Mega Dropdown — positioned relative to <nav> -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
          >
            <div
              v-if="item.is_mega && activeMenu === item.id && item.children.length"
              class="absolute top-full left-0 right-0 pt-1 z-50"
              @mouseenter="cancelClose"
              @mouseleave="scheduleClose"
            >
              <div class="bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden">
                <!-- Mega columns -->
                <div
                  class="p-6 grid gap-x-8 gap-y-4"
                  :style="{ gridTemplateColumns: `repeat(${Math.min(item.children.length, item.mega_columns)}, minmax(0, 1fr))` }"
                >
                  <div v-for="col in item.children" :key="col.id">
                    <!-- Column header -->
                    <NuxtLink
                      :to="resolveUrl(col)"
                      class="flex items-center gap-1.5 mb-3 pb-2 border-b-2 border-gray-100 group/col"
                    >
                      <span class="font-bold text-sm text-gray-900 group-hover/col:text-primary-600 transition-colors">
                        {{ col.title }}
                      </span>
                      <span
                        v-if="col.badge_text"
                        :class="[
                          'text-[9px] font-bold px-1.5 py-0.5 rounded leading-none',
                          badgeStyles[col.badge_color ?? 'red'] ?? 'bg-red-500 text-white',
                        ]"
                      >{{ col.badge_text }}</span>
                    </NuxtLink>

                    <!-- Column items (level 3) -->
                    <ul class="space-y-0.5">
                      <li v-for="child in col.children" :key="child.id">
                        <NuxtLink
                          :to="resolveUrl(child)"
                          class="flex items-center justify-between text-[13px] text-gray-600 hover:text-primary-600 hover:bg-gray-50 px-2 py-1.5 rounded transition-colors"
                        >
                          <span>{{ child.title }}</span>
                          <span v-if="child.description" class="text-[11px] text-gray-400">{{ child.description }}</span>
                        </NuxtLink>

                        <!-- Level 4 items -->
                        <ul v-if="child.children?.length" class="ml-3 mt-0.5 space-y-0 border-l border-gray-100 pl-2">
                          <li v-for="deep in child.children" :key="deep.id">
                            <NuxtLink
                              :to="resolveUrl(deep)"
                              class="block text-xs text-gray-500 hover:text-primary-600 px-2 py-1 rounded transition-colors"
                            >
                              {{ deep.title }}
                            </NuxtLink>
                          </li>
                        </ul>
                      </li>
                    </ul>
                  </div>
                </div>

                <!-- Footer: View all link -->
                <div class="bg-gray-50 px-6 py-2.5 border-t border-gray-100 flex justify-end">
                  <NuxtLink
                    :to="resolveUrl(item)"
                    class="text-xs text-primary-600 hover:text-primary-700 font-semibold flex items-center gap-1"
                  >
                    Xem tất cả {{ item.title }}
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </NuxtLink>
                </div>
              </div>
            </div>
          </Transition>
        </li>
      </ul>
    </nav>

    <!-- ===== MOBILE HAMBURGER ===== -->
    <button
      class="lg:hidden p-2 text-gray-600 hover:text-gray-900"
      @click="mobileOpen = !mobileOpen"
      aria-label="Menu"
    >
      <svg v-if="!mobileOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
      <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <!-- Mobile Overlay -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="mobileOpen" class="fixed inset-0 bg-black/50 z-[100] lg:hidden" @click="mobileOpen = false" />
      </Transition>

      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="-translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="-translate-x-full"
      >
        <div v-if="mobileOpen" class="fixed top-0 left-0 bottom-0 w-80 bg-white z-[101] overflow-y-auto lg:hidden">
          <!-- Mobile header -->
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <NuxtLink to="/" class="flex items-center gap-2" @click="mobileOpen = false">
              <div class="w-7 h-7 bg-primary-600 rounded-md flex items-center justify-center text-white font-bold text-xs">PC</div>
              <span class="text-lg font-bold text-gray-900">PC Shop</span>
            </NuxtLink>
            <button @click="mobileOpen = false" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-md hover:bg-gray-100">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Mobile menu items -->
          <ul class="py-1">
            <li v-for="item in items" :key="item.id">
              <div class="flex items-center">
                <NuxtLink
                  :to="resolveUrl(item)"
                  class="flex-1 flex items-center gap-2 px-5 py-3 text-gray-800 hover:text-primary-600 hover:bg-gray-50 transition-colors"
                  @click="mobileOpen = false"
                >
                  <span class="font-medium text-sm">{{ item.title }}</span>
                  <span
                    v-if="item.badge_text"
                    :class="[
                      'text-[9px] font-bold px-1.5 py-0.5 rounded leading-none',
                      badgeStyles[item.badge_color ?? 'red'] ?? 'bg-red-500 text-white',
                    ]"
                  >{{ item.badge_text }}</span>
                </NuxtLink>
                <button
                  v-if="item.children?.length"
                  class="px-4 py-3 text-gray-400 hover:text-primary-600"
                  @click="toggleMobileSubmenu(item.id)"
                >
                  <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': mobileExpanded === item.id }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
              </div>

              <!-- Mobile submenu -->
              <div v-if="mobileExpanded === item.id && item.children?.length" class="bg-gray-50 border-y border-gray-100">
                <div v-for="col in item.children" :key="col.id" class="px-5 py-2">
                  <NuxtLink
                    :to="resolveUrl(col)"
                    class="flex items-center gap-1.5 py-2 text-sm font-semibold text-gray-800 hover:text-primary-600"
                    @click="mobileOpen = false"
                  >
                    {{ col.title }}
                    <span
                      v-if="col.badge_text"
                      :class="[
                        'text-[8px] font-bold px-1 py-0.5 rounded leading-none',
                        badgeStyles[col.badge_color ?? 'red'] ?? 'bg-red-500 text-white',
                      ]"
                    >{{ col.badge_text }}</span>
                  </NuxtLink>
                  <ul class="ml-3 border-l border-gray-200 pl-3 space-y-0">
                    <li v-for="child in col.children" :key="child.id">
                      <NuxtLink
                        :to="resolveUrl(child)"
                        class="block text-[13px] text-gray-600 hover:text-primary-600 py-1.5"
                        @click="mobileOpen = false"
                      >
                        {{ child.title }}
                      </NuxtLink>
                    </li>
                  </ul>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
