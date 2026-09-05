<script setup lang="ts">
import type { ProductCard } from '~/types/product-detail'

const props = withDefaults(defineProps<{
  products: ProductCard[]
  variant?: 'homepage' | 'compact'
}>(), {
  variant: 'homepage',
})

const track = ref<HTMLElement | null>(null)

function scroll(direction: number) {
  const element = track.value
  if (!element) return
  element.scrollBy({ left: direction * Math.max(element.clientWidth * 0.82, 320), behavior: 'smooth' })
}
</script>

<template>
  <div class="home-product-carousel">
    <button v-if="products.length > 4" type="button" class="home-carousel-arrow home-carousel-arrow--previous" aria-label="Xem sản phẩm trước" @click="scroll(-1)">
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 5-7 7 7 7" /></svg>
    </button>
    <div ref="track" class="home-product-track" tabindex="0" :aria-label="`Danh sách ${products.length} sản phẩm`">
      <ProductCard v-for="product in props.products" :key="product.id" :product="product" :variant="variant" />
    </div>
    <button v-if="products.length > 4" type="button" class="home-carousel-arrow home-carousel-arrow--next" aria-label="Xem sản phẩm tiếp theo" @click="scroll(1)">
      <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
    </button>
  </div>
</template>
