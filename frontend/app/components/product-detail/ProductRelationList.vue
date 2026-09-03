<script setup lang="ts">
import type { ProductCard, RelationType } from '~/types/product-detail'

const props = defineProps<{ slug: string; type: RelationType; title: string; description?: string }>()
const config = useRuntimeConfig()
const products = ref<ProductCard[]>([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    const response = await $fetch<{ products: ProductCard[] }>(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/relations`, { params: { type: props.type, limit: 8 } })
    products.value = response.products
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section v-if="loading || products.length" class="rounded-2xl border border-slate-200 bg-white p-5 lg:p-7">
    <h2 class="text-xl font-bold text-slate-900">{{ title }}</h2>
    <p v-if="description" class="mt-1 text-sm text-slate-600">{{ description }}</p>
    <p v-if="loading" class="mt-4 text-sm text-slate-500">Đang tải sản phẩm…</p>
    <div v-else class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"><ProductCard v-for="product in products" :key="product.id" :product="product" /></div>
  </section>
</template>
