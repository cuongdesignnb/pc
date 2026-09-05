<script setup lang="ts">
import type { ProductCard as ProductCardData } from '~/types/product-detail'

const props = withDefaults(defineProps<{
  product: ProductCardData
  variant?: 'default' | 'homepage' | 'compact'
  compact?: boolean
}>(), {
  variant: 'default',
  compact: false,
})

const { formatMoney } = useSettings()
const wishlist = useWishlist()

const cardVariant = computed(() => props.compact ? 'compact' : props.variant)
const isCompact = computed(() => cardVariant.value === 'compact')
const isHomepage = computed(() => cardVariant.value === 'homepage')
const discountPercent = computed(() => {
  const price = Number(props.product.pricing.price)
  const salePrice = props.product.pricing.sale_price
  if (salePrice === null || price <= 0 || salePrice >= price) return 0
  return Math.round((1 - salePrice / price) * 100)
})
const isWishlisted = computed(() => wishlist.ids.value.includes(props.product.id))
const productUrl = computed(() => props.product.category?.slug
  ? `/${props.product.category.slug}/${props.product.slug}`
  : `/products/${props.product.slug}`)

function toggleWishlist() {
  wishlist.toggle(props.product.id)
}
</script>

<template>
  <article class="product-card" :class="[`product-card--${cardVariant}`, { 'product-card--wishlisted': isWishlisted }]">
    <NuxtLink :to="productUrl" class="product-card-link">
      <div class="product-card-image">
        <span v-if="discountPercent > 0" class="product-card-discount">-{{ discountPercent }}%</span>
        <NuxtImg
          v-if="product.images?.[0]?.url"
          :src="product.images[0].url"
          :alt="product.images[0].alt || product.name"
          width="240"
          height="240"
          sizes="(max-width: 640px) 45vw, 220px"
          :loading="isHomepage ? 'lazy' : 'lazy'"
          class="product-card-image-asset"
        />
        <span v-else class="product-card-image-fallback" aria-hidden="true">PC</span>
      </div>

      <span v-if="product.brand?.name && !isCompact" class="product-card-brand">{{ product.brand.name }}</span>
      <h3 class="product-card-name">{{ product.name }}</h3>

      <div class="product-card-pricing">
        <strong>{{ formatMoney(product.pricing.display_price) }}</strong>
        <span v-if="product.pricing.sale_price !== null" class="product-card-old-price">{{ formatMoney(product.pricing.price) }}</span>
      </div>

      <div v-if="product.rating?.count || (product.sold_count ?? 0) > 0" class="product-card-meta">
        <span v-if="product.rating?.count" class="product-card-rating"><span aria-hidden="true">★</span> {{ product.rating.average?.toFixed(1) }} ({{ product.rating.count }})</span>
        <span v-if="(product.sold_count ?? 0) > 0">Đã bán {{ product.sold_count }}</span>
      </div>
      <p v-if="!isCompact && !isHomepage" class="product-card-availability" :class="product.inventory.purchasable ? 'is-available' : ''">
        {{ product.inventory.availability_label }}
      </p>
    </NuxtLink>

    <button
      type="button"
      class="product-card-wishlist"
      :class="{ 'is-active': isWishlisted }"
      :aria-label="isWishlisted ? `Bỏ ${product.name} khỏi yêu thích` : `Thêm ${product.name} vào yêu thích`"
      :aria-pressed="isWishlisted"
      @click="toggleWishlist"
    >
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M20.8 8.7c0 5.1-8.8 10.2-8.8 10.2S3.2 13.8 3.2 8.7A4.7 4.7 0 0 1 12 6.1a4.7 4.7 0 0 1 8.8 2.6Z" />
      </svg>
    </button>
  </article>
</template>
