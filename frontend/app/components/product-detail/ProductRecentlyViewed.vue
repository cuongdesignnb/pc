<script setup lang="ts">
const props = defineProps<{ slug: string }>()
const { products, record, load } = useRecentlyViewedProducts()

onMounted(async () => {
  await load(props.slug)
  record(props.slug)
})
</script>

<template>
  <section v-if="products.length" class="pdp-panel">
    <h2 class="pdp-section-title">Sản phẩm đã xem</h2>
    <div class="pdp-product-carousel mt-3"><ProductCard v-for="product in products" :key="product.id" :product="product" compact /></div>
  </section>
</template>
