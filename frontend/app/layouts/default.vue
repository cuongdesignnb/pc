<script setup lang="ts">
// Default layout for the storefront
const cart = useCart()
const auth = useAuth()

// Fetch cart on mount
onMounted(() => {
  cart.fetchCart()
})
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <!-- Top bar -->
    <div class="bg-gray-900 text-gray-300 text-xs">
      <div class="container mx-auto px-4 flex items-center justify-between h-8">
        <div class="flex items-center gap-4">
          <span class="flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            1900 1234
          </span>
          <span class="hidden sm:flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            support@pcshop.vn
          </span>
        </div>
        <div class="flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
          <span>Miễn phí vận chuyển đơn &gt; 500K</span>
        </div>
      </div>
    </div>

    <!-- Header -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
      <div class="container mx-auto px-4">
        <!-- Row 1: Logo + Search + Actions -->
        <div class="flex items-center justify-between h-14 gap-4">
          <!-- Logo -->
          <NuxtLink to="/" class="flex items-center gap-2 shrink-0">
            <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">PC</div>
            <span class="text-xl font-extrabold text-gray-900 hidden sm:inline">PC Shop</span>
          </NuxtLink>

          <!-- Search bar -->
          <div class="flex-1 max-w-xl hidden md:block">
            <div class="relative">
              <input
                type="text"
                placeholder="Tìm sản phẩm, danh mục, thương hiệu..."
                class="w-full pl-10 pr-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              >
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <!-- Right actions -->
          <div class="flex items-center gap-2">
            <!-- Search mobile -->
            <button class="md:hidden p-2 text-gray-600 hover:text-primary-600 rounded-lg hover:bg-gray-50">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </button>

            <!-- Cart -->
            <NuxtLink to="/cart" class="p-2 text-gray-600 hover:text-primary-600 relative rounded-lg hover:bg-gray-50">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
              </svg>
              <span
                v-if="cart.itemCount.value > 0"
                class="absolute -top-0.5 -right-0.5 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"
              >
                {{ cart.itemCount.value > 99 ? '99+' : cart.itemCount.value }}
              </span>
            </NuxtLink>

            <!-- Account -->
            <NuxtLink
              :to="auth.isAuthenticated.value ? '/account' : '/auth/login'"
              class="p-2 text-gray-600 hover:text-primary-600 flex items-center gap-1.5 rounded-lg hover:bg-gray-50"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              <span v-if="auth.isAuthenticated.value" class="hidden xl:inline text-sm font-medium">{{ auth.user.value?.name }}</span>
            </NuxtLink>

            <!-- Mobile menu trigger (inside MegaMenu) -->
            <MegaMenu class="lg:hidden" />
          </div>
        </div>

        <!-- Row 2: Navigation (Mega Menu - desktop only) -->
        <div class="hidden lg:block -mb-px">
          <MegaMenu />
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="flex-1">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white">
      <div class="container mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <!-- About -->
          <div>
            <h3 class="text-lg font-semibold mb-4">PC Shop</h3>
            <p class="text-gray-400 text-sm">
              Chuyên cung cấp PC, Laptop và linh kiện máy tính chính hãng với giá tốt nhất.
            </p>
          </div>

          <!-- Quick links -->
          <div>
            <h3 class="text-lg font-semibold mb-4">Liên kết nhanh</h3>
            <ul class="space-y-2 text-sm text-gray-400">
              <li><NuxtLink to="/about" class="hover:text-white">Giới thiệu</NuxtLink></li>
              <li><NuxtLink to="/contact" class="hover:text-white">Liên hệ</NuxtLink></li>
              <li><NuxtLink to="/warranty" class="hover:text-white">Chính sách bảo hành</NuxtLink></li>
              <li><NuxtLink to="/shipping" class="hover:text-white">Vận chuyển</NuxtLink></li>
            </ul>
          </div>

          <!-- Categories -->
          <div>
            <h3 class="text-lg font-semibold mb-4">Danh mục</h3>
            <ul class="space-y-2 text-sm text-gray-400">
              <li><NuxtLink to="/categories/pc-gaming" :prefetch="false" class="hover:text-white">PC Gaming</NuxtLink></li>
              <li><NuxtLink to="/categories/laptop-gaming" :prefetch="false" class="hover:text-white">Laptop Gaming</NuxtLink></li>
              <li><NuxtLink to="/categories/linh-kien-pc" :prefetch="false" class="hover:text-white">Linh kiện PC</NuxtLink></li>
              <li><NuxtLink to="/configurator" class="hover:text-white">Xây dựng cấu hình</NuxtLink></li>
            </ul>
          </div>

          <!-- Contact -->
          <div>
            <h3 class="text-lg font-semibold mb-4">Liên hệ</h3>
            <ul class="space-y-2 text-sm text-gray-400">
              <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                123 Đường ABC, Quận 1, TP.HCM
              </li>
              <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                1900 1234
              </li>
              <li class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                support@pcshop.vn
              </li>
            </ul>
          </div>
        </div>

        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-400">
          © 2026 PC Shop. All rights reserved.
        </div>
      </div>
    </footer>
  </div>
</template>
