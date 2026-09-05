<script setup lang="ts">
import type { ProductCard } from '~/types/product-detail'

const config = useRuntimeConfig()
const { siteName } = useSettings()
const wishlist = useWishlist()
const products = ref<ProductCard[]>([])
const loading = ref(false)
const loadError = ref(false)
let requestVersion = 0

async function loadProducts() {
  const version = ++requestVersion
  wishlist.hydrate()
  const ids = [...wishlist.ids.value]
  loadError.value = false
  if (!wishlist.ids.value.length) {
    products.value = []
    loading.value = false
    return
  }

  loading.value = true
  try {
    const batches: number[][] = []
    for (let index = 0; index < ids.length; index += 12) batches.push(ids.slice(index, index + 12))
    const responses = await Promise.all(batches.map(batch => $fetch<{ products: ProductCard[] }>(`${config.public.apiBase}/products/cards`, {
      method: 'POST',
      body: { ids: batch },
    })))
    if (version !== requestVersion) return
    const byId = new Map(responses.flatMap(response => response.products).map(product => [product.id, product]))
    products.value = ids.map(id => byId.get(id)).filter((product): product is ProductCard => Boolean(product))
  } catch {
    if (version === requestVersion) loadError.value = true
  } finally {
    if (version === requestVersion) loading.value = false
  }
}

onMounted(loadProducts)
watch(() => wishlist.ids.value.slice(), loadProducts)

useSeoMeta({ title: 'Sản phẩm yêu thích' })
</script>

<template>
  <div class="pc-container wishlist-page">
    <div class="wishlist-page-heading">
      <div><span class="eyebrow">{{ siteName }}</span><h1>Sản phẩm yêu thích</h1></div>
      <NuxtLink to="/categories" class="button button-blue">Tiếp tục mua sắm</NuxtLink>
    </div>
    <p v-if="loading" class="wishlist-page-status">Đang tải danh sách...</p>
    <div v-else-if="loadError" role="alert"><p>Không tải được danh sách yêu thích.</p><button type="button" class="button button-blue" @click="loadProducts">Thử lại</button></div>
    <div v-else-if="products.length" class="wishlist-product-grid">
      <ProductCard v-for="product in products" :key="product.id" :product="product" variant="homepage" />
    </div>
    <div v-else class="wishlist-empty">
      <span aria-hidden="true">♡</span>
      <h2>Chưa có sản phẩm yêu thích</h2>
      <p>Nhấn biểu tượng trái tim trên sản phẩm để lưu lại những lựa chọn của bạn.</p>
      <NuxtLink to="/categories" class="button button-orange">Khám phá sản phẩm</NuxtLink>
    </div>
  </div>
</template>
