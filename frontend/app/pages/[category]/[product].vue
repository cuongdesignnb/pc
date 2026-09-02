<script setup lang="ts">
import type { ProductDetail } from '~/types/product-detail'

const route = useRoute()
const config = useRuntimeConfig()
const cart = useCart()
const toast = useToast()
const { siteName } = useSettings()
const { start: startBuyNow } = useBuyNow()
const { track } = useEcommerceTracking()
const slug = computed(() => String(route.params.product || ''))
const { data, pending, error } = await useProductDetail(slug)
const product = computed<ProductDetail | undefined>(() => data.value?.product)
const selection = useProductSelection(product)
const addingToCart = ref(false)

const selectedPrice = computed(() => selection.pricing.value?.display_price ?? product.value?.pricing.display_price ?? 0)
const selectedQuantity = computed(() => selection.inventory.value?.quantity ?? product.value?.inventory.quantity ?? 0)
const scrollTo = (id: string) => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })

const addToCart = async () => {
  if (!product.value || !selection.purchasable.value || addingToCart.value) return
  addingToCart.value = true
  try {
    const success = await cart.addItem(product.value.id, selection.quantity.value, selection.selectedVariant.value?.id)
    if (!success) throw new Error('CART_ADD_FAILED')
    track('add_to_cart', product.value, selection.quantity.value)
    toast.add({ title: 'Đã thêm vào giỏ hàng', description: `${product.value.name} × ${selection.quantity.value}`, color: 'success' })
  } catch {
    toast.add({ title: 'Không thể thêm vào giỏ', description: 'Vui lòng thử lại sau.', color: 'error' })
  } finally {
    addingToCart.value = false
  }
}

const buyNow = async () => {
  if (!product.value || !selection.purchasable.value) return
  track('begin_checkout', product.value, selection.quantity.value)
  await startBuyNow({ product_id: product.value.id, product_slug: product.value.slug, variant_id: selection.selectedVariant.value?.id ?? null, quantity: selection.quantity.value })
}

const openBuilder = () => {
  if (product.value?.component_type) navigateTo(`/cau-hinh?product=${encodeURIComponent(product.value.slug)}`)
}

useSeoMeta({
  title: () => product.value?.seo.title || (product.value ? `${product.value.name} - ${siteName.value}` : `Sản phẩm - ${siteName.value}`),
  description: () => product.value?.seo.description || product.value?.short_description || undefined,
  ogTitle: () => product.value?.seo.title || product.value?.name || siteName.value,
  ogDescription: () => product.value?.seo.description || product.value?.short_description || undefined,
  ogImage: () => product.value?.images[0]?.url || undefined,
})

useHead(() => {
  if (!product.value) return {}
  const canonical = new URL(route.fullPath, config.public.siteUrl || 'https://hpcomvietnam.vn').toString()
  const productSchema: Record<string, unknown> = {
    '@context': 'https://schema.org', '@type': 'Product', name: product.value.name, sku: product.value.sku,
    image: product.value.images.map(image => image.url).filter(Boolean),
    description: product.value.seo.description || product.value.short_description || undefined,
    brand: product.value.brand ? { '@type': 'Brand', name: product.value.brand.name } : undefined,
    offers: { '@type': 'Offer', priceCurrency: 'VND', price: product.value.pricing.display_price, availability: product.value.inventory.purchasable ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', url: canonical },
  }
  if (product.value.rating.count > 0 && product.value.rating.average !== null) productSchema.aggregateRating = { '@type': 'AggregateRating', ratingValue: product.value.rating.average, reviewCount: product.value.rating.count }
  const breadcrumbSchema = {
    '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Trang chủ', item: new URL('/', canonical).toString() },
      ...(product.value.category ? [{ '@type': 'ListItem', position: 2, name: product.value.category.name, item: new URL(`/${product.value.category.slug}`, canonical).toString() }] : []),
      { '@type': 'ListItem', position: product.value.category ? 3 : 2, name: product.value.name, item: canonical },
    ],
  }
  return { link: [{ rel: 'canonical', href: canonical }], script: [{ type: 'application/ld+json', innerHTML: JSON.stringify(productSchema) }, { type: 'application/ld+json', innerHTML: JSON.stringify(breadcrumbSchema) }] }
})

onMounted(() => { if (product.value) track('view_item', product.value) })
</script>

<template>
  <main class="min-h-screen bg-slate-50 pb-24 lg:pb-12">
    <div class="mx-auto max-w-[1480px] px-4 lg:px-6">
      <div v-if="pending" class="py-24 text-center text-slate-500">Đang tải sản phẩm…</div>
      <div v-else-if="error || !product" class="py-24 text-center"><h1 class="text-xl font-bold text-slate-800">Không tìm thấy sản phẩm</h1><NuxtLink to="/" class="mt-4 inline-block text-blue-700 hover:underline">Quay về trang chủ</NuxtLink></div>
      <template v-else>
        <ProductBreadcrumb :product="product" />
        <section class="grid gap-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:grid-cols-12 lg:p-6">
          <div class="lg:col-span-5"><ProductGallery :product="product" /></div>
          <div class="space-y-5 lg:col-span-4">
            <ProductTitleMeta :product="product" :sku="selection.sku.value" @reviews="scrollTo('danh-gia')" />
            <ProductPrice :pricing="{ ...product.pricing, display_price: selectedPrice }" />
            <ProductHighlights :highlights="product.highlights" :short-description="product.short_description" />
            <ProductVariantSelector :variants="product.variants" :option-groups="selection.optionGroups.value" :selected-variant="selection.selectedVariant.value" :selected-attributes="selection.selectedAttributes.value" :is-option-available="selection.isOptionAvailable" @select-variant="selection.chooseVariant" @select-attribute="selection.chooseAttribute" />
            <div class="text-sm"><span :class="selection.purchasable.value ? 'text-emerald-700' : 'text-slate-600'">{{ selection.purchasable.value ? `Còn hàng${selectedQuantity ? ` · ${selectedQuantity} sản phẩm` : ''}` : product.inventory.availability_label }}</span><span v-if="product.warranty_months" class="ml-3 text-slate-600">Bảo hành {{ product.warranty_months }} tháng</span></div>
            <ProductPurchaseActions :quantity="selection.quantity.value" :max-quantity="Math.max(1, selectedQuantity)" :purchasable="selection.purchasable.value" :adding="addingToCart" :can-build="Boolean(product.component_type)" @update:quantity="selection.quantity.value = $event" @add="addToCart" @buy="buyNow" @build="openBuilder" />
          </div>
          <div class="hidden lg:col-span-3 lg:block"><ProductPurchaseSummary :product="product" :variant="selection.selectedVariant.value" :quantity="selection.quantity.value" :purchasable="selection.purchasable.value" @add="addToCart" @buy="buyNow" /></div>
        </section>
        <ProductServiceBenefits class="mt-5" />
        <ProductAnchorTabs class="mt-6" :has-compatibility="Boolean(product.component_type)" />
        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
          <div class="space-y-6">
            <ProductDescription :description="product.description" :blocks="product.detail_blocks" />
            <ProductSpecificationTable :specifications="product.specifications" />
            <ProductCompatibility v-if="product.component_type" :slug="product.slug" />
            <ProductFrequentlyBought :slug="product.slug" />
            <ProductRecommendations :slug="product.slug" />
            <ProductReviewList :slug="product.slug" :summary="product.rating" />
            <ProductReviewForm :slug="product.slug" />
            <ProductQuestions :slug="product.slug" />
            <ProductRecentlyViewed :slug="product.slug" />
          </div>
          <div class="space-y-6"><ProductDeliveryPreview :subtotal="selectedPrice * selection.quantity.value" /></div>
        </div>
        <ProductMobilePurchaseBar :price="selectedPrice" :purchasable="selection.purchasable.value" @add="addToCart" @buy="buyNow" />
      </template>
    </div>
  </main>
</template>
