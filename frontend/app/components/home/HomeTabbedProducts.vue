<script setup lang="ts">
import type { ProductCard } from '~/types/product-detail'

const props = defineProps<{
  bestSellers: {
    laptop: ProductCard[]
    pc_gaming: ProductCard[]
    components: ProductCard[]
  }
}>()

const activeTab = ref<'laptop' | 'pc_gaming' | 'components'>('laptop')
const tabs = [
  { key: 'laptop' as const, label: 'Laptop nổi bật' },
  { key: 'pc_gaming' as const, label: 'PC Gaming bán chạy' },
  { key: 'components' as const, label: 'Linh kiện hot' },
]

const products = computed(() => props.bestSellers[activeTab.value])
</script>

<template>
  <section v-if="bestSellers.laptop.length || bestSellers.pc_gaming.length || bestSellers.components.length" class="home-section home-best-sellers">
    <div class="pc-container">
      <div class="home-section-heading home-tabbed-heading">
        <div class="home-tabbed-title-row">
          <h2>SẢN PHẨM BÁN CHẠY</h2>
          <div class="home-tabs" role="tablist" aria-label="Nhóm sản phẩm bán chạy">
            <button v-for="tab in tabs" :key="tab.key" type="button" role="tab" :aria-selected="activeTab === tab.key" :class="{ 'is-active': activeTab === tab.key }" @click="activeTab = tab.key">
              {{ tab.label }}
            </button>
          </div>
        </div>
        <NuxtLink to="/categories">Xem tất cả <span aria-hidden="true">›</span></NuxtLink>
      </div>
      <HomeProductCarousel :key="activeTab" :products="products" variant="homepage" />
    </div>
  </section>
</template>
