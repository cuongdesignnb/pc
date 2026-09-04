<script setup lang="ts">
import type { ProductCard } from '~/types/product-detail'

const props = withDefaults(defineProps<{ product: ProductCard; compact?: boolean }>(), {
  compact: false,
})
const { formatMoney } = useSettings()
const discountPercent = computed(() => {
  if (props.product.pricing.sale_price === null || props.product.pricing.price <= 0) return 0
  return Math.max(0, Math.round((1 - props.product.pricing.sale_price / props.product.pricing.price) * 100))
})
</script>

<template>
  <NuxtLink
    :to="`/${product.category?.slug || 'san-pham'}/${product.slug}`"
    class="group flex min-w-0 flex-col border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md"
    :class="compact ? 'pdp-product-card--compact rounded-[8px] p-2' : 'rounded-xl p-3'"
  >
    <div class="relative aspect-square overflow-hidden bg-slate-50" :class="compact ? 'rounded-[6px]' : 'rounded-lg'">
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
    <p v-if="product.brand?.name && !compact" class="mt-3 text-xs font-medium text-slate-500">{{ product.brand.name }}</p>
    <h3 class="line-clamp-2 font-semibold leading-5 text-slate-800 group-hover:text-blue-700" :class="compact ? 'mt-2 min-h-9 text-[11px]' : 'mt-1 min-h-10 text-sm'">{{ product.name }}</h3>
    <div class="flex flex-wrap items-end gap-x-2 gap-y-1" :class="compact ? 'mt-1' : 'mt-2'">
      <span class="font-bold text-blue-700" :class="compact ? 'text-xs' : 'text-sm'">{{ formatMoney(product.pricing.display_price) }}</span>
      <span v-if="product.pricing.sale_price !== null" class="text-xs text-slate-400 line-through">{{ formatMoney(product.pricing.price) }}</span>
      <span v-if="discountPercent > 0 && !compact" class="rounded border border-red-200 px-1 py-0.5 text-[10px] font-semibold text-red-600">-{{ discountPercent }}%</span>
    </div>
    <div v-if="product.rating?.count" class="mt-1 flex items-center gap-1 text-[10px] text-amber-500">
      <span aria-hidden="true">★</span>
      <span class="text-slate-500">{{ product.rating.average }} ({{ product.rating.count }})</span>
    </div>
    <p v-if="!compact" class="mt-2 text-xs" :class="product.inventory.purchasable ? 'text-emerald-600' : 'text-slate-500'">{{ product.inventory.availability_label }}</p>
  </NuxtLink>
</template>
