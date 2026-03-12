<script setup lang="ts">
import type { Category, Product, Brand } from '~/types'

interface SpecFilter {
  key_id: number
  label: string
  unit: string | null
  type: string
  values: string[]
}

interface FiltersData {
  brands: Brand[]
  price_range: { min: number; max: number }
  specs: SpecFilter[]
}

interface CategoryResponse {
  category: Category & { parent?: Category }
  products: {
    data: Product[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters: FiltersData
}

const config = useRuntimeConfig()
const route = useRoute()
const router = useRouter()

const slug = route.params.slug as string
const fmt = (n: number) => new Intl.NumberFormat('vi-VN').format(n)

// ---- Reactive filter state ----
const page = ref(Number(route.query.page) || 1)
const sort = ref((route.query.sort as string) || 'newest')
const selectedBrands = ref<string[]>(route.query.brands ? (route.query.brands as string).split(',') : [])
const selectedSubCat = ref<string>((route.query.sub_category as string) || '')
const minPrice = ref<number | undefined>(route.query.min_price ? Number(route.query.min_price) : undefined)
const maxPrice = ref<number | undefined>(route.query.max_price ? Number(route.query.max_price) : undefined)
const inStock = ref(route.query.in_stock === '1')
const specFilters = ref<Record<string, string[]>>({})
const viewMode = ref<'grid' | 'list'>('grid')

// Initialize spec filters from query
for (const [key, value] of Object.entries(route.query)) {
  if (key.startsWith('spec_') && value) {
    specFilters.value[key] = (value as string).split(',')
  }
}

// Build query params
const queryParams = computed(() => {
  const params: Record<string, any> = {
    page: page.value,
    sort: sort.value,
    per_page: 20,
  }
  if (selectedBrands.value.length) params.brands = selectedBrands.value.join(',')
  if (selectedSubCat.value) params.sub_category = selectedSubCat.value
  if (minPrice.value) params.min_price = minPrice.value
  if (maxPrice.value) params.max_price = maxPrice.value
  if (inStock.value) params.in_stock = '1'
  for (const [key, vals] of Object.entries(specFilters.value)) {
    if (vals.length) params[key] = vals.join(',')
  }
  return params
})

// Fetch data
const { data, refresh, status } = await useFetch<CategoryResponse>(
  `${config.public.apiBase}/categories/${slug}`,
  {
    params: queryParams,
    watch: [queryParams],
  }
)

const category = computed(() => data.value?.category)
const products = computed(() => data.value?.products?.data ?? [])
const totalProducts = computed(() => data.value?.products?.total ?? 0)
const totalPages = computed(() => data.value?.products?.last_page ?? 1)
const filters = computed(() => data.value?.filters)

// Update URL when filters change
watch(queryParams, (params) => {
  const query: Record<string, string> = {}
  if (params.page > 1) query.page = String(params.page)
  if (params.sort !== 'newest') query.sort = params.sort
  if (params.brands) query.brands = params.brands
  if (params.sub_category) query.sub_category = params.sub_category
  if (params.min_price) query.min_price = String(params.min_price)
  if (params.max_price) query.max_price = String(params.max_price)
  if (params.in_stock) query.in_stock = '1'
  for (const [key, val] of Object.entries(params)) {
    if (key.startsWith('spec_')) query[key] = val
  }
  router.replace({ query })
}, { deep: true })

// Reset page when filters change
function resetPage() {
  page.value = 1
}

// Toggle brand
function toggleBrand(brandSlug: string) {
  const idx = selectedBrands.value.indexOf(brandSlug)
  if (idx > -1) selectedBrands.value.splice(idx, 1)
  else selectedBrands.value.push(brandSlug)
  resetPage()
}

// Toggle spec value
function toggleSpec(key: string, value: string) {
  if (!specFilters.value[key]) specFilters.value[key] = []
  const idx = specFilters.value[key].indexOf(value)
  if (idx > -1) specFilters.value[key].splice(idx, 1)
  else specFilters.value[key].push(value)
  resetPage()
}

// Clear all filters
function clearAllFilters() {
  selectedBrands.value = []
  selectedSubCat.value = ''
  minPrice.value = undefined
  maxPrice.value = undefined
  inStock.value = false
  specFilters.value = {}
  sort.value = 'newest'
  resetPage()
}

// Price presets
const pricePresets = [
  { label: 'Dưới 5 triệu', min: 0, max: 5000000 },
  { label: '5 - 10 triệu', min: 5000000, max: 10000000 },
  { label: '10 - 20 triệu', min: 10000000, max: 20000000 },
  { label: '20 - 50 triệu', min: 20000000, max: 50000000 },
  { label: 'Trên 50 triệu', min: 50000000, max: undefined },
]

function applyPricePreset(preset: { min: number; max: number | undefined }) {
  minPrice.value = preset.min || undefined
  maxPrice.value = preset.max
  resetPage()
}

const sortOptions = [
  { value: 'newest', label: 'Mới nhất' },
  { value: 'price_asc', label: 'Giá thấp → cao' },
  { value: 'price_desc', label: 'Giá cao → thấp' },
  { value: 'popular', label: 'Bán chạy' },
  { value: 'name_asc', label: 'A → Z' },
]

const activeFilterCount = computed(() => {
  let count = 0
  if (selectedBrands.value.length) count++
  if (selectedSubCat.value) count++
  if (minPrice.value || maxPrice.value) count++
  if (inStock.value) count++
  count += Object.values(specFilters.value).filter(v => v.length > 0).length
  return count
})

const getDiscount = (p: Product) => {
  if (!p.sale_price) return 0
  return Math.round((1 - p.sale_price / p.price) * 100)
}

// Mobile filter sidebar
const showMobileFilters = ref(false)

useSeoMeta({
  title: () => category.value?.name ? `${category.value.name} - PC Shop` : 'Danh mục - PC Shop',
  description: () => category.value?.description || `Khám phá sản phẩm ${category.value?.name} tại PC Shop`,
})
</script>

<template>
  <div class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-6">
      <!-- Breadcrumb -->
      <nav class="mb-5 text-sm flex items-center gap-1.5 flex-wrap">
        <NuxtLink to="/" class="text-gray-500 hover:text-primary-600">Trang chủ</NuxtLink>
        <span class="text-gray-300">/</span>
        <template v-if="category?.parent">
          <NuxtLink :to="`/categories/${category.parent.slug}`" class="text-gray-500 hover:text-primary-600">
            {{ category.parent.name }}
          </NuxtLink>
          <span class="text-gray-300">/</span>
        </template>
        <span class="text-gray-900 font-medium">{{ category?.name ?? 'Đang tải...' }}</span>
      </nav>

      <!-- Category Header -->
      <div class="bg-white rounded-xl p-6 mb-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">{{ category?.name }}</h1>
            <p v-if="category?.description" class="text-gray-500 mt-1">{{ category.description }}</p>
            <p class="text-sm text-gray-400 mt-1">{{ totalProducts }} sản phẩm</p>
          </div>
          <!-- Subcategory chips -->
          <div v-if="category?.children?.length" class="flex flex-wrap gap-2">
            <button
              @click="selectedSubCat = ''; resetPage()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm font-medium transition-all',
                !selectedSubCat
                  ? 'bg-primary-600 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              Tất cả
            </button>
            <button
              v-for="child in category.children"
              :key="child.id"
              @click="selectedSubCat = child.slug; resetPage()"
              :class="[
                'px-3 py-1.5 rounded-full text-sm font-medium transition-all',
                selectedSubCat === child.slug
                  ? 'bg-primary-600 text-white shadow-sm'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]"
            >
              {{ child.name }}
            </button>
          </div>
        </div>
      </div>

      <div class="flex gap-6">
        <!-- ===== SIDEBAR FILTERS (Desktop) ===== -->
        <aside class="w-64 shrink-0 hidden lg:block space-y-4">
          <!-- Active filter badge + clear -->
          <div v-if="activeFilterCount > 0" class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-semibold text-gray-900">Bộ lọc đang chọn ({{ activeFilterCount }})</span>
              <button @click="clearAllFilters" class="text-xs text-red-500 hover:text-red-700">Xóa tất cả</button>
            </div>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="b in selectedBrands" :key="b"
                class="inline-flex items-center gap-1 px-2 py-0.5 bg-primary-50 text-primary-700 rounded-full text-xs"
              >
                {{ b }}
                <button @click="toggleBrand(b)" class="text-primary-400 hover:text-primary-600">✕</button>
              </span>
              <span v-if="minPrice || maxPrice" class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 rounded-full text-xs">
                {{ minPrice ? fmt(minPrice) : '0' }}₫ - {{ maxPrice ? fmt(maxPrice) : '∞' }}₫
                <button @click="minPrice = undefined; maxPrice = undefined; resetPage()" class="text-green-400 hover:text-green-600">✕</button>
              </span>
              <span v-if="inStock" class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full text-xs">
                Còn hàng
                <button @click="inStock = false; resetPage()" class="text-blue-400 hover:text-blue-600">✕</button>
              </span>
            </div>
          </div>

          <!-- Brand filter -->
          <div v-if="filters?.brands?.length" class="bg-white rounded-xl p-4 shadow-sm">
            <h3 class="font-semibold text-gray-900 mb-3 text-sm">Thương hiệu</h3>
            <div class="space-y-2 max-h-48 overflow-y-auto">
              <label v-for="brand in filters.brands" :key="brand.id" class="flex items-center gap-2 cursor-pointer group">
                <input
                  type="checkbox"
                  :checked="selectedBrands.includes(brand.slug)"
                  @change="toggleBrand(brand.slug)"
                  class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                >
                <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ brand.name }}</span>
              </label>
            </div>
          </div>

          <!-- Price Range -->
          <div class="bg-white rounded-xl p-4 shadow-sm">
            <h3 class="font-semibold text-gray-900 mb-3 text-sm">Khoảng giá</h3>
            <div class="space-y-2 mb-3">
              <button
                v-for="preset in pricePresets"
                :key="preset.label"
                @click="applyPricePreset(preset)"
                :class="[
                  'w-full text-left px-3 py-1.5 rounded-lg text-xs transition-colors',
                  (minPrice === preset.min && maxPrice === preset.max)
                    ? 'bg-primary-50 text-primary-700 font-medium'
                    : 'text-gray-600 hover:bg-gray-50'
                ]"
              >
                {{ preset.label }}
              </button>
            </div>
            <div class="flex gap-2">
              <input
                v-model.number="minPrice"
                type="number"
                placeholder="Từ"
                @change="resetPage()"
                class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-1 focus:ring-primary-500"
              >
              <span class="text-gray-400 self-center">-</span>
              <input
                v-model.number="maxPrice"
                type="number"
                placeholder="Đến"
                @change="resetPage()"
                class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs focus:ring-1 focus:ring-primary-500"
              >
            </div>
          </div>

          <!-- In stock -->
          <div class="bg-white rounded-xl p-4 shadow-sm">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="inStock" @change="resetPage()" class="rounded border-gray-300 text-primary-600">
              <span class="text-sm text-gray-700 font-medium">Chỉ hiện hàng còn</span>
            </label>
          </div>

          <!-- Dynamic Spec Filters -->
          <div v-for="specF in (filters?.specs ?? [])" :key="specF.key_id" class="bg-white rounded-xl p-4 shadow-sm">
            <h3 class="font-semibold text-gray-900 mb-3 text-sm">
              {{ specF.label }}
              <span v-if="specF.unit" class="text-gray-400 font-normal">({{ specF.unit }})</span>
            </h3>
            <div class="space-y-2 max-h-40 overflow-y-auto">
              <label v-for="val in specF.values" :key="val" class="flex items-center gap-2 cursor-pointer group">
                <input
                  type="checkbox"
                  :checked="(specFilters[`spec_${specF.key_id}`] ?? []).includes(val)"
                  @change="toggleSpec(`spec_${specF.key_id}`, val)"
                  class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                >
                <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ val }}{{ specF.unit ? ` ${specF.unit}` : '' }}</span>
              </label>
            </div>
          </div>
        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <div class="flex-1 min-w-0">
          <!-- Toolbar -->
          <div class="bg-white rounded-xl p-3 mb-4 shadow-sm flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
              <!-- Mobile filter trigger -->
              <button @click="showMobileFilters = true" class="lg:hidden flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 rounded-lg text-sm text-gray-700 hover:bg-gray-200">
                <span>🔍</span> Bộ lọc
                <span v-if="activeFilterCount" class="bg-primary-600 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center">{{ activeFilterCount }}</span>
              </button>
              <!-- Sort -->
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 hidden sm:inline">Sắp xếp:</span>
                <select
                  v-model="sort"
                  @change="resetPage()"
                  class="text-sm border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-primary-500 bg-white"
                >
                  <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-400">{{ totalProducts }} kết quả</span>
              <!-- View mode toggle -->
              <button
                @click="viewMode = 'grid'"
                :class="['p-1.5 rounded', viewMode === 'grid' ? 'bg-gray-200 text-gray-900' : 'text-gray-400 hover:text-gray-600']"
              >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16">
                  <rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/>
                  <rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/>
                </svg>
              </button>
              <button
                @click="viewMode = 'list'"
                :class="['p-1.5 rounded', viewMode === 'list' ? 'bg-gray-200 text-gray-900' : 'text-gray-400 hover:text-gray-600']"
              >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16">
                  <rect x="1" y="1" width="14" height="3" rx="1"/><rect x="1" y="6" width="14" height="3" rx="1"/>
                  <rect x="1" y="11" width="14" height="3" rx="1"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Loading state -->
          <div v-if="status === 'pending'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div v-for="i in 8" :key="i" class="bg-white rounded-xl overflow-hidden border border-gray-100 animate-pulse">
              <div class="aspect-square bg-gray-200"></div>
              <div class="p-4 space-y-2">
                <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                <div class="h-5 bg-gray-200 rounded w-2/5 mt-2"></div>
              </div>
            </div>
          </div>

          <!-- Products Grid View -->
          <div v-else-if="products.length > 0 && viewMode === 'grid'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <NuxtLink
              v-for="product in products"
              :key="product.id"
              :to="`/products/${product.slug}`"
              class="bg-white rounded-xl overflow-hidden border border-gray-100 hover:shadow-lg hover:border-primary-200 transition-all group"
            >
              <div class="relative aspect-square bg-gray-50 flex items-center justify-center overflow-hidden">
                <img v-if="product.images?.[0]" :src="product.images[0].url" :alt="product.name"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                >
                <span v-else class="text-6xl">📦</span>
                <div v-if="getDiscount(product)" class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-lg">
                  -{{ getDiscount(product) }}%
                </div>
                <div v-if="product.is_featured" class="absolute top-2 right-2 bg-amber-400 text-gray-900 text-[10px] font-bold px-1.5 py-0.5 rounded">
                  HOT
                </div>
              </div>
              <div class="p-4">
                <p class="text-xs text-gray-400 mb-1 uppercase tracking-wide">{{ product.brand?.name }}</p>
                <h3 class="font-semibold text-sm text-gray-900 line-clamp-2 mb-1 group-hover:text-primary-600 transition-colors">{{ product.name }}</h3>
                <p class="text-xs text-gray-500 line-clamp-1 mb-3">{{ product.short_description }}</p>
                <div class="flex items-end gap-2">
                  <span class="text-lg font-bold text-red-600">{{ fmt(product.sale_price || product.price) }}₫</span>
                  <span v-if="product.sale_price" class="text-xs text-gray-400 line-through mb-0.5">{{ fmt(product.price) }}₫</span>
                </div>
              </div>
            </NuxtLink>
          </div>

          <!-- Products List View -->
          <div v-else-if="products.length > 0 && viewMode === 'list'" class="space-y-3">
            <NuxtLink
              v-for="product in products"
              :key="product.id"
              :to="`/products/${product.slug}`"
              class="bg-white rounded-xl border border-gray-100 hover:shadow-md hover:border-primary-200 transition-all flex overflow-hidden group"
            >
              <div class="w-32 h-32 md:w-40 md:h-40 bg-gray-50 flex items-center justify-center shrink-0 overflow-hidden">
                <img v-if="product.images?.[0]" :src="product.images[0].url" :alt="product.name"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                >
                <span v-else class="text-4xl">📦</span>
              </div>
              <div class="flex-1 p-4 flex flex-col justify-between min-w-0">
                <div>
                  <p class="text-xs text-gray-400 mb-0.5 uppercase tracking-wide">{{ product.brand?.name }}</p>
                  <h3 class="font-semibold text-gray-900 mb-1 group-hover:text-primary-600 transition-colors line-clamp-1">{{ product.name }}</h3>
                  <p class="text-sm text-gray-500 line-clamp-2">{{ product.short_description }}</p>
                </div>
                <div class="flex items-center gap-3 mt-2">
                  <span class="text-xl font-bold text-red-600">{{ fmt(product.sale_price || product.price) }}₫</span>
                  <span v-if="product.sale_price" class="text-sm text-gray-400 line-through">{{ fmt(product.price) }}₫</span>
                  <span v-if="getDiscount(product)" class="bg-red-50 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full">
                    -{{ getDiscount(product) }}%
                  </span>
                </div>
              </div>
            </NuxtLink>
          </div>

          <!-- Empty state -->
          <div v-else class="text-center py-16 bg-white rounded-xl shadow-sm">
            <p class="text-5xl mb-4">🔍</p>
            <p class="text-gray-600 font-medium mb-2">Không tìm thấy sản phẩm phù hợp</p>
            <p class="text-sm text-gray-400 mb-4">Thử thay đổi bộ lọc hoặc xóa bộ lọc hiện tại</p>
            <button @click="clearAllFilters" class="text-primary-600 hover:text-primary-700 font-medium text-sm">
              Xóa tất cả bộ lọc
            </button>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="mt-8 flex justify-center gap-1">
            <button
              v-for="p in totalPages"
              :key="p"
              @click="page = p"
              :class="[
                'w-9 h-9 rounded-lg text-sm font-medium transition-colors',
                page === p
                  ? 'bg-primary-600 text-white shadow-sm'
                  : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'
              ]"
            >
              {{ p }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MOBILE FILTER DRAWER ===== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showMobileFilters" class="fixed inset-0 bg-black/50 z-50 lg:hidden" @click.self="showMobileFilters = false">
          <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
          >
            <div v-if="showMobileFilters" class="absolute left-0 top-0 bottom-0 w-80 max-w-[85vw] bg-white overflow-y-auto">
              <div class="sticky top-0 bg-white border-b border-gray-100 px-4 py-3 flex items-center justify-between z-10">
                <h3 class="font-semibold text-gray-900">Bộ lọc</h3>
                <button @click="showMobileFilters = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
              </div>
              <div class="p-4 space-y-5">
                <!-- Brands -->
                <div v-if="filters?.brands?.length">
                  <h4 class="font-medium text-gray-900 mb-2 text-sm">Thương hiệu</h4>
                  <div class="space-y-2">
                    <label v-for="brand in filters.brands" :key="brand.id" class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" :checked="selectedBrands.includes(brand.slug)" @change="toggleBrand(brand.slug)"
                        class="rounded border-gray-300 text-primary-600">
                      <span class="text-sm text-gray-700">{{ brand.name }}</span>
                    </label>
                  </div>
                </div>
                <!-- Price -->
                <div>
                  <h4 class="font-medium text-gray-900 mb-2 text-sm">Khoảng giá</h4>
                  <div class="space-y-1.5">
                    <button v-for="preset in pricePresets" :key="preset.label" @click="applyPricePreset(preset)"
                      :class="['w-full text-left px-3 py-1.5 rounded-lg text-xs transition-colors',
                        (minPrice === preset.min && maxPrice === preset.max) ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-50'
                      ]">
                      {{ preset.label }}
                    </button>
                  </div>
                </div>
                <!-- In stock -->
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="inStock" @change="resetPage()" class="rounded border-gray-300 text-primary-600">
                  <span class="text-sm text-gray-700 font-medium">Chỉ hiện hàng còn</span>
                </label>
                <!-- Spec filters -->
                <div v-for="specF in (filters?.specs ?? [])" :key="specF.key_id">
                  <h4 class="font-medium text-gray-900 mb-2 text-sm">{{ specF.label }}</h4>
                  <div class="space-y-2">
                    <label v-for="val in specF.values" :key="val" class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" :checked="(specFilters[`spec_${specF.key_id}`] ?? []).includes(val)"
                        @change="toggleSpec(`spec_${specF.key_id}`, val)"
                        class="rounded border-gray-300 text-primary-600">
                      <span class="text-sm text-gray-700">{{ val }}</span>
                    </label>
                  </div>
                </div>
                <!-- Clear + Apply -->
                <div class="flex gap-3 pt-2">
                  <button @click="clearAllFilters" class="flex-1 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                    Xóa bộ lọc
                  </button>
                  <button @click="showMobileFilters = false" class="flex-1 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                    Áp dụng
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>