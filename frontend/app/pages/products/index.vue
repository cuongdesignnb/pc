<script setup lang="ts">
import type { Product, Category, Brand } from '~/types'

interface ProductsApiResponse {
  data: Product[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const config = useRuntimeConfig()
const route = useRoute()
const router = useRouter()
const fmt = (n: number) => new Intl.NumberFormat('vi-VN').format(Number(n) || 0)

// Filter state
const page = ref(Number(route.query.page) || 1)
const search = ref((route.query.search as string) || '')
const selectedCategory = ref((route.query.category as string) || '')
const selectedBrand = ref((route.query.brand as string) || '')
const sort = ref((route.query.sort as string) || 'created_at')
const order = ref((route.query.order as string) || 'desc')
const perPage = 24

// Fetch categories
const { data: categories } = await useFetch<Category[]>(`${config.public.apiBase}/categories`, { default: () => [] })

// Flatten all child categories for quick lookup
const allCategories = computed(() => {
  const result: { slug: string; name: string; parentSlug?: string; parentName?: string }[] = []
  for (const cat of categories.value ?? []) {
    result.push({ slug: cat.slug, name: cat.name })
    for (const child of cat.children ?? []) {
      result.push({ slug: child.slug, name: child.name, parentSlug: cat.slug, parentName: cat.name })
    }
  }
  return result
})

const selectedCategoryName = computed(() => {
  if (!selectedCategory.value) return 'Tất cả sản phẩm'
  return allCategories.value.find(c => c.slug === selectedCategory.value)?.name || 'Tất cả sản phẩm'
})


// Fetch products
const isLoading = ref(false)
const productsData = ref<ProductsApiResponse | null>(null)

async function fetchProducts() {
  isLoading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page.value))
    params.set('per_page', String(perPage))
    if (search.value) params.set('search', search.value)
    if (selectedCategory.value) params.set('category', selectedCategory.value)
    if (selectedBrand.value) params.set('brand', selectedBrand.value)
    if (sort.value) params.set('sort', sort.value)
    if (order.value) params.set('order', order.value)
    productsData.value = await $fetch<ProductsApiResponse>(`${config.public.apiBase}/products?${params.toString()}`)
  } catch (e) {
    console.error('Error fetching products:', e)
  } finally {
    isLoading.value = false
  }
}

// Initial fetch
await fetchProducts()

// Re-fetch and update URL when any filter changes
watch([page, search, selectedCategory, selectedBrand, sort, order], () => {
  fetchProducts()
  // Update URL
  const q: Record<string, string> = {}
  if (page.value > 1) q.page = String(page.value)
  if (search.value) q.search = search.value
  if (selectedCategory.value) q.category = selectedCategory.value
  if (selectedBrand.value) q.brand = selectedBrand.value
  if (sort.value !== 'created_at') q.sort = sort.value
  if (order.value !== 'desc') q.order = order.value
  router.replace({ query: q })
})

// Sort options
const sortOptions = [
  { label: 'Mới nhất', sort: 'created_at', order: 'desc' },
  { label: 'Giá thấp → cao', sort: 'price', order: 'asc' },
  { label: 'Giá cao → thấp', sort: 'price', order: 'desc' },
  { label: 'Tên A → Z', sort: 'name', order: 'asc' },
]

const currentSortLabel = computed(() =>
  sortOptions.find(o => o.sort === sort.value && o.order === order.value)?.label || 'Mới nhất'
)

function applySort(opt: { sort: string; order: string }) {
  sort.value = opt.sort
  order.value = opt.order
  page.value = 1
}

function selectCategory(slug: string) {
  selectedCategory.value = slug
  page.value = 1
}

// Expanded parent for showing child category chips
const expandedParent = ref('')

const activeParentChildren = computed(() => {
  if (!expandedParent.value) return []
  const parent = (categories.value ?? []).find(c => c.slug === expandedParent.value)
  return parent?.children ?? []
})

const expandedParentName = computed(() => {
  return (categories.value ?? []).find(c => c.slug === expandedParent.value)?.name || ''
})

function isChildOfParent(parentSlug: string) {
  const parent = (categories.value ?? []).find(c => c.slug === parentSlug)
  return parent?.children?.some(ch => ch.slug === selectedCategory.value) ?? false
}

function handleParentClick(cat: Category) {
  if (expandedParent.value === cat.slug) {
    // Already expanded → select the parent category itself
    selectCategory(cat.slug)
  } else {
    // Expand children row
    expandedParent.value = cat.slug
    selectCategory(cat.slug)
  }
}

function clearFilters() {
  search.value = ''
  selectedCategory.value = ''
  selectedBrand.value = ''
  sort.value = 'created_at'
  order.value = 'desc'
  page.value = 1
}

const getDiscount = (p: Product) => {
  if (!p.sale_price) return 0
  return Math.round((1 - Number(p.sale_price) / Number(p.price)) * 100)
}

const totalPages = computed(() => productsData.value?.last_page ?? 1)
const hasActiveFilters = computed(() => !!(search.value || selectedCategory.value || selectedBrand.value))

useSeoMeta({
  title: () => selectedCategoryName.value + ' - PC Shop',
  description: 'Khám phá hàng ngàn sản phẩm PC, Laptop, linh kiện máy tính chính hãng tại PC Shop',
})
</script>

<template>
  <div class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-6">
      <!-- Breadcrumb -->
      <nav class="mb-4 text-sm flex items-center gap-1.5">
        <NuxtLink to="/" class="text-gray-500 hover:text-primary-600">Trang chủ</NuxtLink>
        <span class="text-gray-300">/</span>
        <span class="text-gray-900 font-medium">{{ selectedCategoryName }}</span>
      </nav>

      <!-- Page Header + Search -->
      <div class="bg-white rounded-xl p-5 mb-5 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ selectedCategoryName }}</h1>
            <p class="text-sm text-gray-400 mt-0.5">{{ productsData?.total || 0 }} sản phẩm</p>
          </div>
          <!-- Search bar -->
          <div class="relative w-full md:w-80">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="search"
              type="text"
              placeholder="Tìm kiếm sản phẩm..."
              class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
              @keyup.enter="page = 1"
            >
          </div>
        </div>
      </div>

      <!-- Category Tabs -->
      <div class="bg-white rounded-xl shadow-sm mb-5">
        <!-- Parent categories -->
        <div class="flex items-center overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 px-3 py-2.5 gap-1 border-b border-gray-100">
          <button
            @click="selectCategory(''); expandedParent = ''"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors shrink-0',
              !selectedCategory
                ? 'bg-primary-600 text-white shadow-sm'
                : 'text-gray-600 hover:bg-gray-100'
            ]"
          >
            Tất cả
          </button>
          <template v-for="cat in categories" :key="cat.id">
            <button
              @click="handleParentClick(cat)"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors shrink-0',
                selectedCategory === cat.slug || isChildOfParent(cat.slug)
                  ? 'bg-primary-600 text-white shadow-sm'
                  : 'text-gray-600 hover:bg-gray-100'
              ]"
            >
              {{ cat.name }}
            </button>
          </template>
        </div>
        <!-- Child categories row -->
        <div v-if="activeParentChildren.length" class="flex items-center overflow-x-auto scrollbar-thin scrollbar-thumb-gray-200 px-3 py-2 gap-1 bg-gray-50">
          <button
            @click="selectCategory(expandedParent)"
            :class="[
              'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors shrink-0',
              selectedCategory === expandedParent
                ? 'bg-primary-100 text-primary-700'
                : 'text-gray-500 hover:bg-gray-200'
            ]"
          >
            Tất cả {{ expandedParentName }}
          </button>
          <button
            v-for="child in activeParentChildren"
            :key="child.id"
            @click="selectCategory(child.slug)"
            :class="[
              'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors shrink-0',
              selectedCategory === child.slug
                ? 'bg-primary-100 text-primary-700'
                : 'text-gray-500 hover:bg-gray-200'
            ]"
          >
            {{ child.name }}
          </button>
        </div>
      </div>

      <!-- Toolbar: Sort + Active filters -->
      <div class="bg-white rounded-xl p-3 mb-5 shadow-sm flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Active filters -->
          <span v-if="hasActiveFilters" class="text-xs text-gray-400">Lọc:</span>
          <span v-if="selectedCategory" class="inline-flex items-center gap-1 px-2.5 py-1 bg-primary-50 text-primary-700 rounded-full text-xs font-medium">
            {{ selectedCategoryName }}
            <button @click="selectCategory('')" class="text-primary-400 hover:text-primary-600 ml-0.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </span>
          <span v-if="search" class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
            "{{ search }}"
            <button @click="search = ''; page = 1" class="text-gray-400 hover:text-gray-600 ml-0.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </span>
          <button v-if="hasActiveFilters" @click="clearFilters" class="text-xs text-red-500 hover:text-red-700 ml-1">Xóa bộ lọc</button>
        </div>

        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-400">Sắp xếp:</span>
          <select
            :value="`${sort}:${order}`"
            @change="(e: any) => { const [s, o] = e.target.value.split(':'); sort = s; order = o; page = 1; }"
            class="text-sm border border-gray-200 rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-primary-500 bg-white"
          >
            <option v-for="opt in sortOptions" :key="opt.label" :value="`${opt.sort}:${opt.order}`">{{ opt.label }}</option>
          </select>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="isLoading && !productsData?.data?.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <div v-for="i in 10" :key="i" class="bg-white rounded-xl overflow-hidden border border-gray-100 animate-pulse">
          <div class="aspect-square bg-gray-200" />
          <div class="p-4 space-y-2">
            <div class="h-3 bg-gray-200 rounded w-1/3" />
            <div class="h-4 bg-gray-200 rounded w-3/4" />
            <div class="h-5 bg-gray-200 rounded w-2/5 mt-2" />
          </div>
        </div>
      </div>

      <!-- Products Grid -->
      <div v-else-if="productsData?.data?.length" class="relative">
        <!-- Loading overlay during refetch -->
        <div v-if="isLoading" class="absolute inset-0 bg-white/60 z-10 flex items-center justify-center rounded-xl">
          <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <NuxtLink
          v-for="product in productsData.data"
          :key="product.id"
          :to="`/products/${product.slug}`"
          class="bg-white rounded-xl overflow-hidden border border-gray-100 hover:shadow-lg hover:border-primary-200 transition-all group"
        >
          <div class="relative aspect-square bg-gray-50 flex items-center justify-center overflow-hidden">
            <img
              v-if="product.images?.[0]"
              :src="product.images[0].url"
              :alt="product.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            >
            <div v-else class="w-16 h-16 rounded-xl bg-gray-200 flex items-center justify-center text-2xl font-bold text-gray-400">
              {{ product.name.charAt(0) }}
            </div>
            <div v-if="getDiscount(product)" class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-lg">
              -{{ getDiscount(product) }}%
            </div>
            <div v-if="product.is_featured" class="absolute top-2 right-2 bg-amber-400 text-gray-900 text-[10px] font-bold px-1.5 py-0.5 rounded">
              HOT
            </div>
          </div>
          <div class="p-3">
            <p class="text-[11px] text-gray-400 mb-0.5 uppercase tracking-wide">{{ product.brand?.name }}</p>
            <h3 class="font-semibold text-sm text-gray-900 line-clamp-2 mb-1 group-hover:text-primary-600 transition-colors leading-snug">
              {{ product.name }}
            </h3>
            <p v-if="product.short_description" class="text-xs text-gray-500 line-clamp-1 mb-2">{{ product.short_description }}</p>
            <div class="flex items-end gap-1.5">
              <span class="text-base font-bold text-red-600">{{ fmt(product.sale_price || product.price) }}₫</span>
              <span v-if="product.sale_price" class="text-[11px] text-gray-400 line-through mb-0.5">{{ fmt(product.price) }}₫</span>
            </div>
          </div>
        </NuxtLink>
      </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="!isLoading" class="text-center py-16 bg-white rounded-xl shadow-sm">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <p class="text-gray-600 font-medium mb-2">Không tìm thấy sản phẩm</p>
        <p class="text-sm text-gray-400 mb-4">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
        <button @click="clearFilters" class="text-primary-600 hover:text-primary-700 font-medium text-sm">
          Xóa tất cả bộ lọc
        </button>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="mt-8 flex justify-center gap-1">
        <button
          v-if="page > 1"
          @click="page = page - 1"
          class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 flex items-center justify-center"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
        <template v-for="p in totalPages" :key="p">
          <button
            v-if="p === 1 || p === totalPages || (p >= page - 2 && p <= page + 2)"
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
          <span v-else-if="p === page - 3 || p === page + 3" class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">...</span>
        </template>
        <button
          v-if="page < totalPages"
          @click="page = page + 1"
          class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-100 flex items-center justify-center"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
      </div>
    </div>
  </div>
</template>
