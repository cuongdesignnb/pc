<script setup lang="ts">
const emit = defineEmits<{ openMobileMenu: [] }>()

const auth = useAuth()
const cart = useCart()
const wishlist = useWishlist()
const { siteLogo, siteName, siteTagline, siteHotline } = useSettings()

const accountName = computed(() => {
  if (!auth.isAuthenticated.value) return 'Đăng nhập'
  return auth.user.value?.name?.split(' ')[0] || 'Tài khoản'
})

const phoneHref = computed(() => `tel:${siteHotline.value.replace(/\s/g, '')}`)
</script>

<template>
  <div class="header-main-row border-b border-slate-100">
    <div class="pc-container header-main-inner">
      <button
        type="button"
        class="header-mobile-menu-button"
        aria-label="Mở danh mục sản phẩm"
        @click="emit('openMobileMenu')"
      >
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <NuxtLink to="/" class="site-brand" :aria-label="`Trang chủ ${siteName}`">
        <img v-if="siteLogo" :src="siteLogo" :alt="siteName" class="site-brand-image">
        <span v-else class="site-brand-mark" aria-hidden="true">PC</span>
        <span class="site-brand-copy">
          <span class="site-brand-name">{{ siteName }}</span>
          <span v-if="siteTagline" class="site-brand-tagline">{{ siteTagline }}</span>
          <span v-else class="site-brand-tagline">Build Your Dream</span>
        </span>
      </NuxtLink>

      <div class="header-search desktop-only">
        <SearchBox />
      </div>

      <div class="header-actions">
        <a v-if="siteHotline" :href="phoneHref" class="header-action header-hotline">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5 4h3l1.5 4-2 1.5a14 14 0 0 0 7 7l1.5-2 4 1.5v3a2 2 0 0 1-2 2C10.4 21 3 13.6 3 4a2 2 0 0 1 2-2Z" />
          </svg>
          <span><small>Hotline mua hàng</small><strong>{{ siteHotline }}</strong></span>
        </a>

        <NuxtLink :to="auth.isAuthenticated.value ? '/tai-khoan' : '/dang-nhap'" class="header-action">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="8" r="3.5" stroke-width="1.7" />
            <path stroke-linecap="round" stroke-width="1.7" d="M5 20a7 7 0 0 1 14 0" />
          </svg>
          <span><small>Tài khoản</small><strong>{{ accountName }}</strong></span>
        </NuxtLink>

        <NuxtLink to="/yeu-thich" class="header-action header-action-icon" aria-label="Yêu thích">
          <span class="header-icon-wrap">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M20.8 8.7c0 5.1-8.8 10.2-8.8 10.2S3.2 13.8 3.2 8.7A4.7 4.7 0 0 1 12 6.1a4.7 4.7 0 0 1 8.8 2.6Z" />
            </svg>
            <span v-if="wishlist.count.value > 0" class="header-count">{{ wishlist.count.value > 99 ? '99+' : wishlist.count.value }}</span>
          </span>
          <span><small>Yêu thích</small><strong>{{ wishlist.count.value || '' }}</strong></span>
        </NuxtLink>

        <NuxtLink to="/gio-hang" class="header-action header-action-icon" aria-label="Giỏ hàng">
          <span class="header-icon-wrap">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 4h2l1.4 10.2a2 2 0 0 0 2 1.8h8.8a2 2 0 0 0 1.9-1.4L21 7H6" />
              <circle cx="9" cy="20" r="1.2" stroke-width="1.5" />
              <circle cx="18" cy="20" r="1.2" stroke-width="1.5" />
            </svg>
            <span v-if="cart.itemCount.value > 0" class="header-count">{{ cart.itemCount.value > 99 ? '99+' : cart.itemCount.value }}</span>
          </span>
          <span><small>Giỏ hàng</small><strong>{{ cart.itemCount.value || '' }}</strong></span>
        </NuxtLink>
      </div>
    </div>
  </div>

  <div class="mobile-search-row">
    <div class="pc-container"><SearchBox /></div>
  </div>
</template>
