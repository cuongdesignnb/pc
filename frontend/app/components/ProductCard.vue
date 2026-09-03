<script setup lang="ts">
import type { ProductCard } from '~/types/product-detail'

defineProps<{ product: ProductCard }>()
const { formatMoney } = useSettings()
</script>

<template>
  <NuxtLink :to="`/${product.category?.slug || 'san-pham'}/${product.slug}`" class="group flex min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-3 transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
    <div class="relative aspect-square overflow-hidden rounded-lg bg-slate-50">
      <NuxtImg
        v-if="product.images[0]?.url"
        :src="product.images[0].url"
        :alt="product.images[0].alt || product.name"
        width="240"
        height="240"
        sizes="(max-width: 640px) 45vw, 220px"
        loading="lazy"
        class="h-full w-full object-contain transition duration-300 group-hover:scale-105"
      />
    </div>
    <p v-if="product.brand?.name" class="mt-3 text-xs font-medium text-slate-500">{{ product.brand.name }}</p>
    <h3 class="mt-1 line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-slate-800 group-hover:text-blue-700">{{ product.name }}</h3>
    <div class="mt-2 flex flex-wrap items-end gap-x-2 gap-y-1">
      <span class="font-bold text-blue-700">{{ formatMoney(product.pricing.display_price) }}</span>
      <span v-if="product.pricing.sale_price !== null" class="text-xs text-slate-400 line-through">{{ formatMoney(product.pricing.price) }}</span>
    </div>
    <p class="mt-2 text-xs" :class="product.inventory.purchasable ? 'text-emerald-600' : 'text-slate-500'">{{ product.inventory.availability_label }}</p>
  </NuxtLink>
</template>
