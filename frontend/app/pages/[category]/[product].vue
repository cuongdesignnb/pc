<script setup lang="ts">
import type { ProductCard, ProductDetail } from '~/types/product-detail'

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
const { data: alternativeData } = await useFetch<{ products: ProductCard[] }>(() => `${config.public.apiBase}/products/${encodeURIComponent(slug.value)}/relations`, {
  params: { type: 'alternative', limit: 4 },
})
const alternativeProducts = computed(() => alternativeData.value?.products || [])
const selection = useProductSelection(product)
const addingToCart = ref(false)

const selectedPrice = computed(() => selection.pricing.value?.display_price ?? product.value?.pricing.display_price ?? 0)
const selectedQuantity = computed(() => selection.inventory.value?.quantity ?? product.value?.inventory.quantity ?? 0)
const selectedPricing = computed<ProductDetail['pricing']>(() => {
  const base = product.value?.pricing || { price: 0, sale_price: null, display_price: 0, discount_percent: 0, saving: 0 }
  const current = selection.pricing.value
  const price = current ? current.price : base.price
  const salePrice = current ? current.sale_price : base.sale_price
  const displayPrice = current ? current.display_price : base.display_price
  return {
    price,
    sale_price: salePrice,
    display_price: displayPrice,
    discount_percent: salePrice !== null && price > 0 ? Math.max(0, Math.round((1 - salePrice / price) * 100)) : 0,
    saving: salePrice !== null ? Math.max(0, price - salePrice) : 0,
  }
})

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
  <main class="pdp-reference min-h-screen bg-slate-50 pb-24 lg:pb-12">
    <div class="pdp-container">
      <div v-if="pending" class="py-24 text-center text-sm text-slate-500">Đang tải sản phẩm…</div>
      <div v-else-if="error || !product" class="py-24 text-center"><h1 class="text-xl font-bold text-slate-800">Không tìm thấy sản phẩm</h1><NuxtLink to="/" class="mt-4 inline-block text-sm text-blue-700 hover:underline">Quay về trang chủ</NuxtLink></div>
      <template v-else>
        <ProductBreadcrumb :product="product" />

        <div class="pdp-layout">
          <section class="pdp-area-hero pdp-hero-card">
            <div class="pdp-gallery-column"><ProductGallery :product="product" /></div>
            <div class="pdp-info-column">
              <ProductTitleMeta :product="product" :sku="selection.sku.value" @reviews="scrollTo('danh-gia')" />
              <ProductHighlights :highlights="product.highlights" :short-description="product.short_description" />
              <ProductPrice :pricing="selectedPricing" />
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                <span :class="selection.purchasable.value ? 'font-semibold text-emerald-600' : 'text-slate-500'"><span aria-hidden="true">●</span> {{ selection.purchasable.value ? 'Còn hàng' : product.inventory.availability_label }}</span>
                <span v-if="selection.purchasable.value && selectedQuantity" class="text-slate-400">{{ selectedQuantity }} sản phẩm</span>
                <span v-if="selection.sku.value" class="text-slate-400">SKU: {{ selection.sku.value }}</span>
                <span v-if="product.warranty_months" class="text-slate-500">Bảo hành: {{ product.warranty_months }} tháng</span>
              </div>
              <ProductVariantSelector :product="product" :variants="product.variants" :option-groups="selection.optionGroups.value" :selected-variant="selection.selectedVariant.value" :selected-attributes="selection.selectedAttributes.value" :alternative-products="alternativeProducts" :is-option-available="selection.isOptionAvailable" @select-variant="selection.chooseVariant" @select-attribute="selection.chooseAttribute" />
              <ProductPurchaseActions :quantity="selection.quantity.value" :max-quantity="Math.max(1, selectedQuantity)" :purchasable="selection.purchasable.value" :adding="addingToCart" :can-build="Boolean(product.component_type)" @update:quantity="selection.quantity.value = $event" @add="addToCart" @buy="buyNow" @build="openBuilder" />
            </div>
          </section>

          <div class="pdp-area-summary"><ProductPurchaseSummary :product="product" :variant="selection.selectedVariant.value" :quantity="selection.quantity.value" :purchasable="selection.purchasable.value" @add="addToCart" @buy="buyNow" /></div>
          <div class="pdp-area-benefits"><ProductServiceBenefits :warranty-months="product.warranty_months" /></div>
          <div class="pdp-area-delivery"><ProductDeliveryPreview :subtotal="selectedPrice * selection.quantity.value" /></div>
          <div class="pdp-area-tabs"><ProductAnchorTabs :has-compatibility="Boolean(product.component_type)" :rating-count="product.rating.count" :questions-count="product.questions_count" /></div>

          <div class="pdp-area-content pdp-content-main">
            <ProductDescription :description="product.description" :blocks="product.detail_blocks" />
            <div class="pdp-spec-compat-grid">
              <ProductSpecificationTable :specifications="product.specifications" />
              <ProductCompatibility v-if="product.component_type" :slug="product.slug" />
            </div>
          </div>

          <aside class="pdp-area-relations pdp-relations-rail">
            <ProductRelationList :slug="product.slug" type="frequently_bought" title="Mua kèm ưu đãi" mode="compact-selectable" :limit="6" />
            <ProductRelationList :slug="product.slug" type="accessory" title="Phụ kiện nên mua" mode="compact-selectable" :limit="6" />
          </aside>

          <div class="pdp-area-related"><ProductRecommendations :slug="product.slug" /></div>
          <div class="pdp-area-reviews pdp-review-zone"><ProductReviewList :slug="product.slug" :summary="product.rating" /><ProductReviewForm :slug="product.slug" /></div>
          <div class="pdp-area-bottom pdp-bottom-row"><ProductQuestions :slug="product.slug" /><ProductRecentlyViewed :slug="product.slug" /></div>
        </div>

        <ProductMobilePurchaseBar :price="selectedPrice" :purchasable="selection.purchasable.value" @add="addToCart" @buy="buyNow" />
      </template>
    </div>
  </main>
</template>

<style scoped>
.pdp-reference {
  --pdp-blue: #1264d8;
  --pdp-blue-dark: #0b55bd;
  --pdp-orange: #ff6a00;
  --pdp-red: #ef2f2f;
  --pdp-green: #16a34a;
  --pdp-border: #e5e7eb;
  --pdp-muted: #6b7280;
  --pdp-surface: #ffffff;
  --pdp-soft: #f7f8fa;
  color: #1f2937;
}

.pdp-container {
  width: min(100% - 32px, 1480px);
  margin-inline: auto;
}

.pdp-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  grid-template-areas:
    'hero summary'
    'benefits summary'
    'tabs delivery'
    'content relations'
    'related related'
    'reviews reviews'
    'bottom bottom';
  gap: 16px;
  align-items: start;
  padding-top: 12px;
}

.pdp-area-hero { grid-area: hero; }
.pdp-area-summary { grid-area: summary; }
.pdp-area-benefits { grid-area: benefits; }
.pdp-area-delivery { grid-area: delivery; }
.pdp-area-tabs { grid-area: tabs; }
.pdp-area-content { grid-area: content; }
.pdp-area-relations { grid-area: relations; }
.pdp-area-related { grid-area: related; }
.pdp-area-reviews { grid-area: reviews; }
.pdp-area-bottom { grid-area: bottom; }

.pdp-hero-card {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px;
  padding: 14px;
  border: 1px solid var(--pdp-border);
  border-radius: 8px;
  background: var(--pdp-surface);
  box-shadow: 0 1px 4px rgb(15 23 42 / 3%);
}

.pdp-info-column { min-width: 0; }
.pdp-info-column > * + * { margin-top: 10px; }
.pdp-content-main > * + *, .pdp-review-zone > * + * { margin-top: 16px; }

.pdp-reference :deep(.pdp-panel) {
  border: 1px solid var(--pdp-border);
  border-radius: 8px;
  background: var(--pdp-surface);
  padding: 14px;
  box-shadow: 0 1px 4px rgb(15 23 42 / 3%);
}

.pdp-reference :deep(.pdp-section-title) {
  color: #111827;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.25;
}

.pdp-spec-compat-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.pdp-relations-rail { display: flex; flex-direction: column; gap: 16px; }
.pdp-relations-rail :deep(.pdp-panel) { padding: 12px; }

.pdp-reference :deep(.pdp-benefit-strip) {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  min-height: 78px;
  border: 1px solid var(--pdp-border);
  border-radius: 8px;
  background: var(--pdp-surface);
  box-shadow: 0 1px 4px rgb(15 23 42 / 3%);
}

.pdp-reference :deep(.pdp-benefit-item) {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding: 10px;
  border-right: 1px solid #f1f3f5;
}
.pdp-reference :deep(.pdp-benefit-item:last-child) { border-right: 0; }
.pdp-reference :deep(.pdp-benefit-icon) {
  display: inline-flex;
  height: 30px;
  width: 30px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: #eff6ff;
  color: var(--pdp-blue);
  font-size: 14px;
}

.pdp-reference :deep(.pdp-option-row) { display: grid; grid-template-columns: 126px minmax(0, 1fr); align-items: center; gap: 8px; }
.pdp-reference :deep(.pdp-option-label) { color: #6b7280; font-size: 11px; }
.pdp-reference :deep(.pdp-option-chip) { display: inline-flex; min-height: 28px; align-items: center; justify-content: center; border: 1px solid #d1d5db; border-radius: 5px; background: #fff; padding: 4px 9px; color: #4b5563; font-size: 11px; line-height: 1.2; }
.pdp-reference :deep(.pdp-option-chip--selected) { border-color: #60a5fa; background: #eff6ff; color: #1264d8; font-weight: 600; }

.pdp-reference :deep(.pdp-anchor-tabs) { position: sticky; top: 80px; z-index: 20; display: flex; min-height: 42px; overflow-x: auto; border-bottom: 1px solid var(--pdp-border); background: rgb(255 255 255 / 96%); backdrop-filter: blur(8px); }
.pdp-reference :deep(.pdp-anchor-tab) { display: inline-flex; min-height: 42px; flex: 0 0 auto; align-items: center; border-bottom: 2px solid transparent; padding: 0 16px; color: #6b7280; font-size: 11px; font-weight: 700; white-space: nowrap; }
.pdp-reference :deep(.pdp-anchor-tab:hover), .pdp-reference :deep(.pdp-anchor-tab--active) { border-color: var(--pdp-blue); color: var(--pdp-blue); }

.pdp-reference :deep(.pdp-feature-overview) { display: grid; grid-template-columns: minmax(220px, .8fr) minmax(0, 1.2fr); gap: 14px; }
.pdp-reference :deep(.pdp-marketing-hero) { overflow: hidden; border-radius: 7px; background: #050b12; color: white; }
.pdp-reference :deep(.pdp-marketing-copy) { padding: 14px 14px 0; }
.pdp-reference :deep(.pdp-benchmark-card), .pdp-reference :deep(.pdp-use-case-card) { min-width: 0; border: 1px solid #e5e7eb; border-radius: 7px; background: #fff; padding: 9px; }
.pdp-reference :deep(.pdp-use-case-card) { min-height: 82px; }

.pdp-reference :deep(.pdp-review-summary) { min-width: 0; padding: 2px 4px; }
.pdp-reference :deep(.pdp-review-card) { min-width: 0; min-height: 148px; border: 1px solid #e5e7eb; border-radius: 7px; background: #fff; padding: 10px; }
.pdp-reference :deep(.pdp-product-carousel) { display: grid; grid-auto-columns: minmax(150px, 1fr); grid-auto-flow: column; grid-template-rows: 1fr; gap: 10px; overflow-x: auto; padding-bottom: 3px; scroll-snap-type: x proximity; }
.pdp-reference :deep(.pdp-product-carousel > *) { width: 100%; min-width: 0; scroll-snap-align: start; }
.pdp-reference :deep(.pdp-relations-rail .pdp-panel) { box-shadow: none; }

.pdp-bottom-row { display: grid; grid-template-columns: minmax(0, 28%) minmax(0, 1fr); gap: 16px; align-items: start; }

@media (max-width: 1100px) {
  .pdp-layout { grid-template-columns: minmax(0, 1fr) 310px; }
  .pdp-reference :deep(.pdp-benefit-item) { padding-inline: 7px; }
  .pdp-reference :deep(.pdp-benefit-icon) { height: 26px; width: 26px; font-size: 12px; }
}

@media (max-width: 900px) {
  .pdp-layout {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      'hero'
      'benefits'
      'delivery'
      'tabs'
      'content'
      'relations'
      'related'
      'reviews'
      'bottom';
  }
  .pdp-area-summary { display: none; }
  .pdp-hero-card { grid-template-columns: minmax(0, 1fr); }
  .pdp-info-column { display: flex; flex-direction: column; }
  .pdp-info-column > * + * { margin-top: 10px; }
  .pdp-spec-compat-grid, .pdp-bottom-row { grid-template-columns: minmax(0, 1fr); }
  .pdp-relations-rail { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
  .pdp-reference :deep(.pdp-benefit-strip) { grid-template-columns: repeat(5, minmax(150px, 1fr)); overflow-x: auto; }
  .pdp-reference :deep(.pdp-benefit-item) { min-width: 150px; }
  .pdp-reference :deep(.pdp-anchor-tabs) { top: 56px; }
}

@media (max-width: 600px) {
  .pdp-container { width: min(100% - 20px, 1480px); }
  .pdp-layout { gap: 12px; padding-top: 8px; }
  .pdp-hero-card { gap: 12px; padding: 10px; }
  .pdp-gallery :deep(.pdp-gallery) { min-width: 0; }
  .pdp-reference :deep(.pdp-feature-overview) { grid-template-columns: minmax(0, 1fr); }
  .pdp-reference :deep(.pdp-option-row) { grid-template-columns: 104px minmax(0, 1fr); }
  .pdp-reference :deep(.pdp-relations-rail) { display: block; }
  .pdp-reference :deep(.pdp-relations-rail .pdp-panel + .pdp-panel) { margin-top: 12px; }
  .pdp-reference :deep(.pdp-review-card) { min-height: 0; }
}
</style>
