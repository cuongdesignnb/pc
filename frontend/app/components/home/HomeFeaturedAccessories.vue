<script setup lang="ts">
import type { ProductCard } from '~/types/product-detail'

defineProps<{ products: ProductCard[] }>()
const { formatMoney } = useSettings()
</script>

<template>
  <article v-if="products.length" class="home-feature-card home-featured-accessories">
    <div class="home-feature-card-heading">
      <div><h3>PHỤ KIỆN NỔI BẬT</h3><p>Gear xịn - Trải nghiệm đỉnh</p></div>
      <NuxtLink to="/categories/phu-kien">Xem tất cả <span aria-hidden="true">›</span></NuxtLink>
    </div>
    <div class="home-accessory-list">
      <NuxtLink v-for="product in products.slice(0, 3)" :key="product.id" :to="product.category?.slug ? `/${product.category.slug}/${product.slug}` : `/products/${product.slug}`" class="home-accessory-item">
        <span class="home-accessory-image">
          <NuxtImg v-if="product.images?.[0]?.url" :src="product.images[0].url" :alt="product.images[0].alt || product.name" width="92" height="92" loading="lazy" />
          <span v-else aria-hidden="true">PC</span>
        </span>
        <span><strong>{{ product.name }}</strong><b>{{ formatMoney(product.pricing.display_price) }}</b></span>
      </NuxtLink>
    </div>
  </article>
</template>
