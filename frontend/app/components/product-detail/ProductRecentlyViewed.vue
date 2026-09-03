<script setup lang="ts">
const props = defineProps<{ slug: string }>()
const { products, record, load } = useRecentlyViewedProducts()

onMounted(async () => {
  await load(props.slug)
  record(props.slug)
})
</script>

<template>
  <section v-if="products.length" class="rounded-2xl border border-slate-200 bg-white p-5 lg:p-7">
    <h2 class="text-xl font-bold text-slate-900">Sản phẩm đã xem</h2>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"><ProductCard v-for="product in products" :key="product.id" :product="product" /></div>
  </section>
</template>
