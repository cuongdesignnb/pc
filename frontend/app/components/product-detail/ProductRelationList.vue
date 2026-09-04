<script setup lang="ts">
import type { ProductCard, RelationType } from '~/types/product-detail'

type RelationListMode = 'grid' | 'compact-selectable' | 'horizontal-carousel'

const props = withDefaults(defineProps<{
  slug: string
  type: RelationType
  title: string
  description?: string
  mode?: RelationListMode
  limit?: number
}>(), {
  mode: 'grid',
  limit: 8,
})

const config = useRuntimeConfig()
const cart = useCart()
const toast = useToast()
const { formatMoney } = useSettings()
const products = ref<ProductCard[]>([])
const loading = ref(false)
const addingAll = ref(false)
const selectedIds = ref<number[]>([])
const carousel = ref<HTMLElement | null>(null)

const selectedProducts = computed(() => products.value.filter(product => selectedIds.value.includes(product.id)))
const displayTotal = computed(() => selectedProducts.value.reduce((total, product) => total + product.pricing.display_price, 0))
const originalTotal = computed(() => selectedProducts.value.reduce((total, product) => total + product.pricing.price, 0))
const productPath = (product: ProductCard) => `/${product.category?.slug || 'san-pham'}/${product.slug}`

const load = async () => {
  loading.value = true
  try {
    const response = await $fetch<{ products: ProductCard[] }>(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/relations`, {
      params: { type: props.type, limit: props.limit },
    })
    products.value = response.products || []
    selectedIds.value = products.value.map(product => product.id)
  } catch {
    products.value = []
  } finally {
    loading.value = false
  }
}

const addAll = async () => {
  if (!selectedProducts.value.length || addingAll.value) return
  addingAll.value = true
  let added = 0
  for (const product of selectedProducts.value) {
    if (await cart.addItem(product.id, 1)) added += 1
  }
  addingAll.value = false
  toast.add({
    title: added ? 'Đã thêm sản phẩm mua kèm' : 'Không thể thêm sản phẩm',
    description: added ? `${added}/${selectedProducts.value.length} sản phẩm đã vào giỏ hàng.` : 'Vui lòng kiểm tra lại tồn kho.',
    color: added ? 'success' : 'error',
  })
}

const scrollCarousel = (direction: number) => {
  carousel.value?.scrollBy({ left: direction * 320, behavior: 'smooth' })
}

onMounted(load)
</script>

<template>
  <section v-if="loading || products.length" class="pdp-panel pdp-relation-panel">
    <div class="flex items-start justify-between gap-3">
      <div><h2 class="pdp-section-title">{{ title }}</h2><p v-if="description" class="mt-1 text-[10px] text-slate-500">{{ description }}</p></div>
      <div v-if="mode === 'horizontal-carousel' && products.length > 5" class="flex gap-1">
        <button type="button" class="h-7 w-7 rounded border border-slate-200 text-slate-500 hover:border-blue-300 hover:text-blue-700" aria-label="Sản phẩm trước" @click="scrollCarousel(-1)">‹</button>
        <button type="button" class="h-7 w-7 rounded border border-slate-200 text-slate-500 hover:border-blue-300 hover:text-blue-700" aria-label="Sản phẩm sau" @click="scrollCarousel(1)">›</button>
      </div>
    </div>
    <p v-if="loading" class="mt-3 text-xs text-slate-500">Đang tải sản phẩm…</p>

    <div v-else-if="mode === 'compact-selectable'" class="mt-3 space-y-2">
      <label v-for="product in products" :key="product.id" class="flex cursor-pointer items-center gap-2 border-b border-slate-100 pb-2 last:border-0 last:pb-0">
        <input v-model="selectedIds" type="checkbox" :value="product.id" class="h-3.5 w-3.5 accent-blue-600">
        <NuxtLink :to="productPath(product)" class="flex min-w-0 flex-1 items-center gap-2" @click.stop>
          <NuxtImg v-if="product.images[0]?.url" :src="product.images[0].url" :alt="product.images[0].alt || product.name" width="46" height="46" class="h-11 w-11 shrink-0 object-contain" />
          <span class="min-w-0"><strong class="line-clamp-2 block text-[10px] font-medium leading-4 text-slate-700">{{ product.name }}</strong><span class="mt-0.5 block text-[10px] font-bold text-red-600">{{ formatMoney(product.pricing.display_price) }}</span></span>
        </NuxtLink>
      </label>
      <div class="border-t border-slate-100 pt-2 text-xs">
        <div class="flex justify-between gap-2"><span class="text-slate-500">Tổng tiền</span><strong class="text-blue-700">{{ formatMoney(displayTotal) }}</strong></div>
        <div v-if="originalTotal > displayTotal" class="mt-0.5 text-right text-[10px] text-slate-400 line-through">{{ formatMoney(originalTotal) }}</div>
        <button type="button" :disabled="!selectedProducts.length || addingAll" class="mt-2 h-8 w-full rounded-[6px] border border-blue-500 text-[10px] font-bold uppercase text-blue-700 hover:bg-blue-50 disabled:opacity-45" @click="addAll">{{ addingAll ? 'Đang thêm…' : 'Thêm tất cả vào giỏ' }}</button>
      </div>
    </div>

    <div v-else-if="mode === 'horizontal-carousel'" ref="carousel" class="pdp-product-carousel mt-3">
      <ProductCard v-for="product in products" :key="product.id" :product="product" compact />
    </div>
    <div v-else class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
      <ProductCard v-for="product in products" :key="product.id" :product="product" compact />
    </div>
  </section>
</template>
