  <script setup lang="ts">
import type { Product, Category, Brand, Post } from '~/types'

interface HomepageSection {
  category: {
    id: number
    name: string
    slug: string
    description: string | null
    image: string | null
  }
  children: { id: number; name: string; slug: string }[]
  product_count: number
  products: Product[]
}

interface BannerSlide {
  id: number
  title: string
  description: string | null
  badge: string | null
  image: string
  link: string | null
  position: string
  sort_order: number
  metadata: {
    gradient?: string
    cta_label?: string
    cta_link?: string
    cta2_label?: string
    cta2_link?: string
    glow_a?: string
    glow_b?: string
  } | null
}

const config = useRuntimeConfig()
const fmt = (n: number) => new Intl.NumberFormat('vi-VN').format(n)

const { data: sections } = await useFetch<HomepageSection[]>(
  `${config.public.apiBase}/categories/homepage-sections`,
  { default: () => [] }
)

const { data: categories } = await useFetch<Category[]>(
  `${config.public.apiBase}/categories`,
  { default: () => [] }
)

const { data: brands } = await useFetch<Brand[]>(
  `${config.public.apiBase}/brands`,
  { default: () => [] }
)

const { data: featuredPosts } = await useFetch<Post[]>(
  `${config.public.apiBase}/blog/featured`,
  { default: () => [] }
)

// Fetch hero banners from API
const { data: heroBanners } = await useFetch<BannerSlide[]>(
  `${config.public.apiBase}/banners`,
  { params: { position: 'hero' }, default: () => [] }
)

// Fetch sidebar banners from API
const { data: sidebarBanners } = await useFetch<BannerSlide[]>(
  `${config.public.apiBase}/banners`,
  { params: { position: 'sidebar' }, default: () => [] }
)

// Map API banners to slide format with fallback gradients
const defaultGradients = [
  { gradient: 'from-indigo-600 via-purple-600 to-pink-500', glowA: 'bg-white', glowB: 'bg-yellow-300' },
  { gradient: 'from-red-600 via-orange-600 to-amber-500', glowA: 'bg-orange-300', glowB: 'bg-red-400' },
  { gradient: 'from-emerald-600 via-teal-600 to-cyan-500', glowA: 'bg-cyan-300', glowB: 'bg-emerald-300' },
  { gradient: 'from-pink-600 via-rose-600 to-red-500', glowA: 'bg-pink-300', glowB: 'bg-rose-400' },
]

const mainSlides = computed(() => {
  const banners = heroBanners.value ?? []
  if (banners.length === 0) {
    // Fallback if no banners in the DB
    return [{
      badge: 'PC Shop',
      title: 'Xây dựng PC<br>trong mơ của bạn',
      desc: 'Công cụ build cấu hình thông minh — kiểm tra tương thích tự động, xem TDP & giá ngay lập tức.',
      cta: { label: 'Build PC ngay', to: '/configurator' },
      cta2: { label: 'Xem sản phẩm', to: '/products' },
      gradient: 'from-indigo-600 via-purple-600 to-pink-500',
      glowA: 'bg-white', glowB: 'bg-yellow-300',
      image: '',
    }]
  }
  return banners.map((b, i) => {
    const dg = defaultGradients[i % defaultGradients.length]!
    const meta = b.metadata || {}
    return {
      badge: b.badge || '',
      title: b.title || '',
      desc: b.description || '',
      cta: { label: meta.cta_label || 'Xem ngay', to: meta.cta_link || b.link || '/products' },
      cta2: { label: meta.cta2_label || '', to: meta.cta2_link || '' },
      gradient: meta.gradient || dg.gradient,
      glowA: meta.glow_a || dg.glowA,
      glowB: meta.glow_b || dg.glowB,
      image: b.image || '',
    }
  })
})

const allSubcats = computed(() =>
  (categories.value ?? []).flatMap(c => c.children ?? [])
)

// Color palette — professional accent colors for sections
const sectionColors = [
  { accent: 'border-blue-600', bg: 'bg-blue-600', bgLight: 'bg-blue-50', text: 'text-blue-700', gradient: 'from-blue-600 to-indigo-700' },
  { accent: 'border-orange-500', bg: 'bg-orange-500', bgLight: 'bg-orange-50', text: 'text-orange-700', gradient: 'from-orange-500 to-red-600' },
  { accent: 'border-emerald-600', bg: 'bg-emerald-600', bgLight: 'bg-emerald-50', text: 'text-emerald-700', gradient: 'from-emerald-500 to-teal-600' },
  { accent: 'border-purple-600', bg: 'bg-purple-600', bgLight: 'bg-purple-50', text: 'text-purple-700', gradient: 'from-purple-500 to-violet-600' },
  { accent: 'border-cyan-600', bg: 'bg-cyan-600', bgLight: 'bg-cyan-50', text: 'text-cyan-700', gradient: 'from-cyan-500 to-sky-600' },
  { accent: 'border-rose-600', bg: 'bg-rose-600', bgLight: 'bg-rose-50', text: 'text-rose-700', gradient: 'from-rose-500 to-pink-600' },
]

// Subcategory grid colors
const catColors = [
  'bg-blue-600', 'bg-orange-500', 'bg-emerald-600', 'bg-purple-600',
  'bg-cyan-600', 'bg-rose-500', 'bg-amber-600', 'bg-indigo-600',
  'bg-teal-600', 'bg-red-600',
]

function getColor(index: number) {
  return sectionColors[index % sectionColors.length]!
}

const getDiscount = (p: Product) => {
  if (!p.sale_price) return 0
  return Math.round((1 - p.sale_price / p.price) * 100)
}

const activeChildFilter = ref<Record<number, string | null>>({})

function setChildFilter(sectionIdx: number, childSlug: string | null) {
  activeChildFilter.value[sectionIdx] = childSlug
}

// Only show child tabs that have at least 1 product in the sample
function availableChildren(section: HomepageSection) {
  const productCatIds = new Set(section.products.map(p => p.category?.id))
  return section.children.filter(c => productCatIds.has(c.id))
}

function filteredProducts(section: HomepageSection, sectionIdx: number): Product[] {
  const filter = activeChildFilter.value[sectionIdx]
  if (!filter) return section.products.slice(0, 8)

  const child = section.children.find(c => c.slug === filter)
  if (!child) return section.products.slice(0, 8)

  return section.products.filter(p => p.category?.id === child.id).slice(0, 8)
}

useSeoMeta({
  title: 'PC Shop - Bán PC, Laptop & Linh kiện máy tính',
  description: 'Chuyên cung cấp PC Gaming, Laptop, linh kiện máy tính chính hãng. Xây dựng cấu hình PC thông minh với công cụ kiểm tra tương thích.',
})

// ===== MAIN BANNER SLIDES =====
const currentSlide = ref(0)
const slideDirection = ref<'next' | 'prev'>('next')
const isSlideTransitioning = ref(false)
let mainSlideTimer: ReturnType<typeof setInterval> | null = null

function goToSlide(idx: number) {
  if (idx === currentSlide.value || isSlideTransitioning.value) return
  slideDirection.value = idx > currentSlide.value ? 'next' : 'prev'
  isSlideTransitioning.value = true
  currentSlide.value = idx
  resetMainTimer()
  setTimeout(() => { isSlideTransitioning.value = false }, 600)
}

function nextSlide() {
  goToSlide((currentSlide.value + 1) % mainSlides.value.length)
}

function prevSlide() {
  goToSlide((currentSlide.value - 1 + mainSlides.value.length) % mainSlides.value.length)
}

function resetMainTimer() {
  if (mainSlideTimer) clearInterval(mainSlideTimer)
  mainSlideTimer = setInterval(nextSlide, 5000)
}

// ===== SIDE BANNERS VERTICAL TICKER =====
const sideSlideIndex = ref(0)
let sideSlideTimer: ReturnType<typeof setInterval> | null = null

// We show 3 items at a time, cycling through all sections
const sideSections = computed(() => sections.value ?? [])
const visibleSideSections = computed(() => {
  const all = sideSections.value
  if (all.length <= 3) return all
  const result: HomepageSection[] = []
  for (let i = 0; i < 3; i++) {
    const item = all[(sideSlideIndex.value + i) % all.length]
    if (item) result.push(item)
  }
  return result
})

function nextSideSlide() {
  if (sideSections.value.length <= 3) return
  sideSlideIndex.value = (sideSlideIndex.value + 1) % sideSections.value.length
}

onMounted(() => {
  resetMainTimer()
  sideSlideTimer = setInterval(nextSideSlide, 3000)
})

onUnmounted(() => {
  if (mainSlideTimer) clearInterval(mainSlideTimer)
  if (sideSlideTimer) clearInterval(sideSlideTimer)
})

// Scroll-reveal refs
const catGridRef = useStaggerReveal({ stagger: 40 })
const ctaRef = useReveal()
const whyRef = useStaggerReveal({ stagger: 120 })

// Per-section reveal refs (dynamic)
const sectionRefs = ref<(HTMLElement | null)[]>([])
const revealedSections = reactive(new Set<number>())

onMounted(() => {
  if (typeof IntersectionObserver === 'undefined') return

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const el = entry.target as HTMLElement
          // Find section index
          const idx = sectionRefs.value.indexOf(el)
          if (idx !== -1) revealedSections.add(idx)
          // Reveal header
          el.querySelector('[data-section-header]')?.classList.add('is-visible')
          // Stagger product cards
          const cards = el.querySelectorAll('[data-product-card]')
          cards.forEach((card, i) => {
            setTimeout(() => card.classList.add('is-visible'), i * 80)
          })
          observer.unobserve(el)
        }
      })
    },
    { threshold: 0.08 }
  )

  // Wait for next tick to ensure all section refs are populated
  nextTick(() => {
    sectionRefs.value.forEach((el) => { if (el) observer.observe(el) })
  })

  onUnmounted(() => observer.disconnect())
})
</script>

<template>
  <div>
    <!-- ============ HERO BANNER ============ -->
    <section class="bg-gray-900 pt-4 pb-6">
      <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 h-auto lg:h-[420px]">

          <!-- ===== MAIN BANNER CAROUSEL ===== -->
          <div class="lg:col-span-2 relative rounded-2xl overflow-hidden min-h-[260px]">
            <!-- Slides -->
            <TransitionGroup
              :name="slideDirection === 'next' ? 'slide-next' : 'slide-prev'"
              tag="div"
              class="relative w-full h-full"
            >
              <div
                v-for="(slide, si) in mainSlides"
                v-show="si === currentSlide"
                :key="si"
                class="absolute inset-0 p-8 md:p-12 flex flex-col justify-between"
              >
                <!-- Background image + gradient overlay -->
                <div v-if="slide.image" class="absolute inset-0">
                  <img
                    :src="slide.image"
                    :alt="slide.badge"
                    class="w-full h-full object-cover"
                  />
                  <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/40 to-transparent"></div>
                </div>
                <!-- Solid gradient fallback -->
                <div v-else class="absolute inset-0 bg-gradient-to-br" :class="slide.gradient"></div>

                <!-- Background glow -->
                <div class="absolute inset-0 opacity-10 pointer-events-none">
                  <div class="absolute top-10 right-10 w-64 h-64 rounded-full blur-3xl float-glow" :class="slide.glowA"></div>
                  <div class="absolute bottom-0 left-0 w-48 h-48 rounded-full blur-3xl float-glow-alt" :class="slide.glowB"></div>
                </div>
                <!-- Content -->
                <div class="relative z-10">
                  <span class="inline-block bg-white/20 backdrop-blur-sm text-white text-sm font-medium px-3 py-1 rounded-full mb-4">
                    {{ slide.badge }} — Tháng {{ new Date().getMonth() + 1 }}
                  </span>
                  <h1 class="text-3xl md:text-4xl lg:text-5xl font-extrabold text-white mb-3 leading-tight" v-html="slide.title" />
                  <p class="text-white/80 text-lg mb-6 max-w-lg">{{ slide.desc }}</p>
                </div>
                <div class="relative z-10 flex flex-wrap gap-3">
                  <NuxtLink :to="slide.cta.to" :prefetch="false">
                    <UButton size="lg" class="bg-white text-gray-900 hover:bg-gray-100 font-semibold shadow-lg">
                      {{ slide.cta.label }}
                    </UButton>
                  </NuxtLink>
                  <NuxtLink :to="slide.cta2.to" :prefetch="false">
                    <UButton size="lg" variant="outline" class="border-white/50 text-white hover:bg-white/10 font-semibold">
                      {{ slide.cta2.label }}
                    </UButton>
                  </NuxtLink>
                </div>
              </div>
            </TransitionGroup>

            <!-- Navigation arrows -->
            <button
              @click="prevSlide"
              class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-black/30 hover:bg-black/50 backdrop-blur-sm text-white flex items-center justify-center transition-colors"
              aria-label="Previous"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button
              @click="nextSlide"
              class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-black/30 hover:bg-black/50 backdrop-blur-sm text-white flex items-center justify-center transition-colors"
              aria-label="Next"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <!-- Dots -->
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 flex gap-2">
              <button
                v-for="(_, di) in mainSlides"
                :key="di"
                @click="goToSlide(di)"
                class="h-2 rounded-full transition-all duration-300"
                :class="di === currentSlide ? 'w-6 bg-white' : 'w-2 bg-white/50 hover:bg-white/70'"
                :aria-label="`Slide ${di + 1}`"
              />
            </div>
          </div>

          <!-- ===== SIDE BANNERS — VERTICAL TICKER ===== -->
          <div class="relative overflow-hidden rounded-2xl lg:rounded-none flex flex-col gap-4 lg:gap-0 lg:h-full">
            <!-- Static layout for ≤3 sections -->
            <template v-if="sideSections.length <= 3">
              <NuxtLink
                v-for="(sec, i) in sideSections"
                :key="sec.category.id"
                :to="`/${sec.category.slug}`"
                :prefetch="false"
                class="flex-1 relative rounded-2xl overflow-hidden p-6 flex flex-col justify-end min-h-[120px] group hover:shadow-xl transition-all bg-gradient-to-br"
                :class="[getColor(i).gradient, `hero-side-${i + 1}`]"
              >
                <span class="text-white/80 text-sm font-medium">{{ sec.product_count }} sản phẩm</span>
                <h3 class="text-white text-xl font-bold">{{ sec.category.name }}</h3>
                <p v-if="sec.children.length" class="text-white/70 text-sm">
                  {{ sec.children.slice(0, 3).map(c => c.name).join(', ') }}
                </p>
              </NuxtLink>
            </template>

            <!-- Vertical ticker for >3 sections -->
            <template v-else>
              <div class="flex flex-col gap-4 lg:gap-0 lg:h-full side-ticker">
                <TransitionGroup name="side-slide" tag="div" class="flex flex-col gap-4 lg:gap-0 lg:h-full relative">
                  <NuxtLink
                    v-for="(sec, i) in visibleSideSections"
                    :key="sec.category.id"
                    :to="`/${sec.category.slug}`"
                    :prefetch="false"
                    class="flex-1 relative rounded-2xl lg:rounded-none overflow-hidden p-6 flex flex-col justify-end min-h-[120px] group hover:shadow-xl transition-all bg-gradient-to-br lg:first:rounded-t-2xl lg:last:rounded-b-2xl"
                    :class="getColor((sideSlideIndex + i) % sectionColors.length).gradient"
                  >
                    <span class="text-white/80 text-sm font-medium">{{ sec.product_count }} sản phẩm</span>
                    <h3 class="text-white text-xl font-bold">{{ sec.category.name }}</h3>
                    <p v-if="sec.children.length" class="text-white/70 text-sm">
                      {{ sec.children.slice(0, 3).map(c => c.name).join(', ') }}
                    </p>
                  </NuxtLink>
                </TransitionGroup>
              </div>
              <!-- Side dots -->
              <div class="absolute right-3 top-1/2 -translate-y-1/2 z-20 flex flex-col gap-1.5 lg:flex hidden">
                <span
                  v-for="(_, di) in sideSections"
                  :key="di"
                  class="w-1.5 rounded-full transition-all duration-300"
                  :class="di === sideSlideIndex ? 'h-4 bg-white' : 'h-1.5 bg-white/40'"
                />
              </div>
            </template>

            <!-- Fallback: if no sections -->
            <NuxtLink v-if="sideSections.length === 0" to="/cau-hinh"
              class="flex-1 relative rounded-2xl overflow-hidden bg-gradient-to-br from-amber-400 to-yellow-500 p-6 flex flex-col justify-end min-h-[100px] group hover:shadow-xl transition-shadow"
            >
              <h3 class="text-gray-900 text-xl font-bold">Build PC Online</h3>
              <p class="text-gray-700 text-sm">Tự chọn linh kiện, check tương thích</p>
            </NuxtLink>
          </div>

        </div>
      </div>
    </section>

    <!-- ============ QUICK CATEGORY GRID ============ -->
    <section v-if="allSubcats.length" class="py-8 bg-white border-b border-gray-100">
      <div :ref="(el: any) => catGridRef = el" class="container mx-auto px-4">
        <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-8 lg:grid-cols-10 gap-3">
          <NuxtLink
            v-for="(cat, ci) in allSubcats.slice(0, 20)"
            :key="cat.id"
            :to="`/${cat.slug}`"
            :prefetch="false"
            data-reveal="scale"
            class="flex flex-col items-center gap-1.5 p-3 rounded-xl hover:bg-gray-50 transition-colors group"
          >
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold overflow-hidden"
              :class="cat.icon ? 'bg-gray-100' : catColors[ci % catColors.length]"
            >
              <img v-if="cat.icon" :src="cat.icon" :alt="cat.name" class="w-full h-full object-contain p-1.5">
              <span v-else>{{ cat.name.charAt(0).toUpperCase() }}</span>
            </div>
            <span class="text-xs font-medium text-gray-700 text-center leading-tight group-hover:text-primary-600 transition-colors">{{ cat.name }}</span>
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- ============ DYNAMIC CATEGORY SECTIONS ============ -->
    <template v-for="(section, sIdx) in sections" :key="section.category.id">
      <section
        :ref="(el: any) => { if (el?.$el || el) sectionRefs[sIdx] = (el.$el || el) as HTMLElement }"
        :class="sIdx % 2 === 0 ? 'bg-gray-50' : 'bg-white'"
        class="py-10"
      >
        <div class="container mx-auto px-4">
          <!-- Section Header -->
          <div data-section-header class="reveal-up flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
            <div class="flex items-center gap-3">
              <div class="w-1 h-8 rounded-full" :class="getColor(sIdx).bg" />
              <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ section.category.name }}</h2>
                <p class="text-sm text-gray-500">{{ section.product_count }} sản phẩm</p>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <button
                @click="setChildFilter(sIdx, null)"
                :class="[
                  'px-3 py-1.5 rounded-full text-xs font-medium transition-colors border',
                  !activeChildFilter[sIdx]
                    ? `${getColor(sIdx).bgLight} ${getColor(sIdx).text} ${getColor(sIdx).accent}`
                    : 'text-gray-500 hover:text-gray-700 border-transparent hover:bg-gray-100'
                ]"
              >
                Tất cả
              </button>
              <button
                v-for="child in availableChildren(section)"
                :key="child.id"
                @click="setChildFilter(sIdx, child.slug)"
                :class="[
                  'px-3 py-1.5 rounded-full text-xs font-medium transition-colors border',
                  activeChildFilter[sIdx] === child.slug
                    ? `${getColor(sIdx).bgLight} ${getColor(sIdx).text} ${getColor(sIdx).accent}`
                    : 'text-gray-500 hover:text-gray-700 border-transparent hover:bg-gray-100'
                ]"
              >
                {{ child.name }}
              </button>
              <NuxtLink
                :to="`/${section.category.slug}`"
                :prefetch="false"
                class="text-primary-600 hover:text-primary-700 font-medium text-sm flex items-center gap-1 ml-auto"
              >
                Xem tất cả
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </NuxtLink>
            </div>
          </div>

          <!-- Product Grid -->
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <NuxtLink
              v-for="product in filteredProducts(section, sIdx)"
              :key="product.id"
              :to="`/${section.category.slug}/${product.slug}`"
              :prefetch="false"
              data-product-card
              :class="['card-hover bg-white rounded-xl overflow-hidden border border-gray-100 hover:border-primary-200 transition-all group', revealedSections.has(sIdx) ? '' : 'reveal-up']"
            >
              <div class="relative aspect-square bg-gray-100 flex items-center justify-center overflow-hidden">
                <img
                  v-if="product.images?.[0]"
                  :src="product.images[0].url"
                  :alt="product.name"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                >
                <div v-else class="w-16 h-16 rounded-xl flex items-center justify-center text-2xl font-bold text-white" :class="getColor(sIdx).bg">
                  {{ product.name.charAt(0) }}
                </div>
                <div v-if="getDiscount(product)" class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-lg">
                  -{{ getDiscount(product) }}%
                </div>
                <div v-if="product.is_featured" class="absolute top-2 right-2 bg-amber-400 text-gray-900 text-[10px] font-bold px-1.5 py-0.5 rounded">
                  HOT
                </div>
              </div>
              <div class="p-4">
                <p class="text-xs text-gray-400 mb-1 uppercase tracking-wide">{{ product.brand?.name ?? section.category.name }}</p>
                <h3 class="font-semibold text-sm text-gray-900 line-clamp-2 mb-1 group-hover:text-primary-600 transition-colors">
                  {{ product.name }}
                </h3>
                <p class="text-xs text-gray-500 line-clamp-1 mb-3">{{ product.short_description }}</p>
                <div class="flex items-end gap-2">
                  <span class="text-lg font-bold text-red-600">{{ fmt(product.sale_price || product.price) }}₫</span>
                  <span v-if="product.sale_price" class="text-xs text-gray-400 line-through mb-0.5">{{ fmt(product.price) }}₫</span>
                </div>
              </div>
            </NuxtLink>
          </div>

          <div v-if="filteredProducts(section, sIdx).length === 0" class="text-center py-8 text-gray-400">
            <p>Chưa có sản phẩm trong danh mục con này.</p>
            <NuxtLink :to="`/${section.category.slug}`" class="text-primary-600 hover:underline text-sm mt-2 inline-block">
              Xem tất cả {{ section.category.name }}
            </NuxtLink>
          </div>
        </div>
      </section>
    </template>

    <!-- ============ CTA - BUILD PC ============ -->
    <section class="py-14 bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 overflow-hidden">
      <div :ref="(el: any) => ctaRef = el" class="container mx-auto px-4 text-center reveal-up">
        <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">Tự build PC theo ý bạn</h2>
        <p class="text-gray-400 text-lg mb-8 max-w-2xl mx-auto">
          Chọn từng linh kiện, kiểm tra tương thích tự động, so sánh giá và TDP.
          Hoàn toàn miễn phí, không cần đăng ký.
        </p>
        <NuxtLink to="/cau-hinh">
          <UButton size="xl" class="bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-bold px-8 shadow-xl shadow-purple-500/25">
            Bắt đầu build ngay
          </UButton>
        </NuxtLink>
      </div>
    </section>

    <!-- ============ WHY CHOOSE US ============ -->
    <section class="py-14 bg-white">
      <div :ref="(el: any) => whyRef = el" class="container mx-auto px-4">
        <h2 class="text-2xl font-bold text-center text-gray-900 mb-10">Tại sao chọn PC Shop?</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div data-reveal="up" class="text-center p-4">
            <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1 text-sm">Hàng chính hãng 100%</h3>
            <p class="text-gray-500 text-xs leading-relaxed">Cam kết chính hãng, bảo hành đầy đủ theo nhà sản xuất</p>
          </div>
          <div data-reveal="up" class="text-center p-4">
            <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1 text-sm">Giao hàng toàn quốc</h3>
            <p class="text-gray-500 text-xs leading-relaxed">Miễn phí nội thành, giao nhanh 2-4h tại HCM & HN</p>
          </div>
          <div data-reveal="up" class="text-center p-4">
            <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1 text-sm">Thanh toán linh hoạt</h3>
            <p class="text-gray-500 text-xs leading-relaxed">QR Pay, chuyển khoản, COD — nhanh chóng & an toàn</p>
          </div>
          <div data-reveal="up" class="text-center p-4">
            <div class="w-14 h-14 bg-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <h3 class="font-semibold text-gray-900 mb-1 text-sm">Hỗ trợ kỹ thuật 24/7</h3>
            <p class="text-gray-500 text-xs leading-relaxed">Đội ngũ kỹ thuật viên tư vấn & hỗ trợ mọi lúc</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ BRAND CAROUSEL ============ -->
    <section class="py-8 bg-gray-50 border-t border-gray-100">
      <div class="container mx-auto px-4 mb-4">
        <h3 class="text-center text-sm font-semibold text-gray-500 uppercase tracking-wider">Thương hiệu hàng đầu</h3>
      </div>
      <div class="relative overflow-hidden">
        <div class="brand-scroll flex gap-8 items-center">
          <div v-for="(brand, idx) in [...(brands || []), ...(brands || [])]" :key="`brand-${idx}`" class="brand-item flex-shrink-0 px-4">
            <div class="flex items-center justify-center h-16 w-32 grayscale hover:grayscale-0 opacity-60 hover:opacity-100 transition-all duration-300">
              <img v-if="brand.logo" :src="brand.logo" :alt="brand.name" class="max-h-full max-w-full object-contain">
              <span v-else class="text-gray-600 font-semibold text-lg">{{ brand.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ FEATURED POSTS ============ -->
    <section v-if="featuredPosts && featuredPosts.length > 0" class="py-12 bg-white">
      <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900">Tin tức nổi bật</h2>
            <p class="text-gray-500 text-sm mt-1">Cập nhật tin tức công nghệ mới nhất</p>
          </div>
          <NuxtLink to="/tin-tuc" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm flex items-center gap-1">
            Xem tất cả
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          </NuxtLink>
        </div>
        <div class="relative overflow-hidden">
          <div class="post-scroll flex gap-6 pb-4">
            <NuxtLink
              v-for="post in [...(featuredPosts || []), ...(featuredPosts || [])]" 
              :key="post.id" 
              :to="`/tin-tuc/${post.slug}`"
              class="post-card flex-shrink-0 group"
            >
              <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-300">
                <div class="relative h-48 bg-gray-100 overflow-hidden">
                  <img 
                    v-if="post.featured_image" 
                    :src="post.featured_image" 
                    :alt="post.title"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  >
                  <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600">
                    <svg class="w-16 h-16 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                    </svg>
                  </div>
                  <div class="absolute top-3 right-3 bg-indigo-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                    Nổi bật
                  </div>
                </div>
                <div class="p-4">
                  <div class="flex items-center gap-2 mb-2">
                    <span v-if="post.category" class="text-xs text-indigo-600 font-semibold uppercase">{{ post.category.name }}</span>
                    <span class="text-xs text-gray-400">•</span>
                    <span class="text-xs text-gray-500">{{ new Date(post.published_at || post.created_at || new Date()).toLocaleDateString('vi-VN') }}</span>
                  </div>
                  <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-indigo-600 transition-colors">
                    {{ post.title }}
                  </h3>
                  <p v-if="post.excerpt" class="text-sm text-gray-600 line-clamp-2">{{ post.excerpt }}</p>
                  <div class="flex items-center gap-4 mt-3 text-xs text-gray-500">
                    <span class="flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                      {{ post.view_count || 0 }} lượt xem
                    </span>
                  </div>
                </div>
              </div>
            </NuxtLink>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
@keyframes scroll {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(-50%);
  }
}

.brand-scroll {
  animation: scroll 40s linear infinite;
  width: fit-content;
}

.brand-scroll:hover {
  animation-play-state: paused;
}

.post-scroll {
  animation: scroll 60s linear infinite;
  width: fit-content;
}

.post-scroll:hover {
  animation-play-state: paused;
}

.post-card {
  width: 320px;
}
</style>