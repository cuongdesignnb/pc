<script setup>
const config = useRuntimeConfig()
const query = ref('')
const results = ref({ products: [], posts: [] })
const isOpen = ref(false)
const loading = ref(false)
const searchRef = ref(null)

let timer = null

const hasResults = computed(() => results.value.products.length > 0 || results.value.posts.length > 0)

watch(query, (val) => {
  clearTimeout(timer)
  if (val.length < 2) {
    results.value = { products: [], posts: [] }
    isOpen.value = false
    return
  }
  loading.value = true
  timer = setTimeout(async () => {
    try {
      const data = await $fetch(`${config.public.apiBase}/search`, {
        params: { q: val },
      })
      results.value = data
      isOpen.value = true
    } catch {
      results.value = { products: [], posts: [] }
    } finally {
      loading.value = false
    }
  }, 300)
})

function formatPrice(p) {
  return new Intl.NumberFormat('vi-VN').format(p) + ' VND'
}

function close() {
  setTimeout(() => { isOpen.value = false }, 200)
}

function goToResult(url) {
  isOpen.value = false
  query.value = ''
  navigateTo(url)
}

// Close on route change
const route = useRoute()
watch(() => route.fullPath, () => {
  isOpen.value = false
  query.value = ''
})
</script>

<template>
  <div class="relative" ref="searchRef">
    <!-- Input -->
    <div class="relative">
      <input
        v-model="query"
        type="text"
        placeholder="Tìm sản phẩm, bài viết..."
        class="w-full pl-10 pr-10 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
        @focus="query.length >= 2 && hasResults && (isOpen = true)"
        @blur="close"
      >
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <!-- Loading spinner -->
      <svg v-if="loading" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
      </svg>
      <!-- Clear button -->
      <button v-else-if="query" @click="query = ''; isOpen = false" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Dropdown Results -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div
        v-if="isOpen && (hasResults || (query.length >= 2 && !loading))"
        class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden z-[60] max-h-[70vh] overflow-y-auto"
      >
        <!-- Products -->
        <div v-if="results.products.length">
          <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sản phẩm</span>
          </div>
          <div class="divide-y divide-gray-50">
            <button
              v-for="p in results.products"
              :key="'p-' + p.id"
              @mousedown.prevent="goToResult(p.url)"
              class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-indigo-50/50 transition-colors text-left"
            >
              <div class="w-10 h-10 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden">
                <img v-if="p.image" :src="p.image" :alt="p.name" class="w-full h-full object-cover">
                <div v-else class="w-full h-full flex items-center justify-center text-gray-300">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ p.name }}</p>
                <div class="flex items-center gap-2">
                  <span v-if="p.sale_price" class="text-xs font-semibold text-red-500">{{ formatPrice(p.sale_price) }}</span>
                  <span :class="p.sale_price ? 'text-[11px] text-gray-400 line-through' : 'text-xs font-semibold text-indigo-600'">{{ formatPrice(p.price) }}</span>
                </div>
              </div>
              <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>

        <!-- Posts -->
        <div v-if="results.posts.length">
          <div class="px-4 py-2 bg-gray-50 border-b border-gray-100" :class="results.products.length ? 'border-t' : ''">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Bài viết</span>
          </div>
          <div class="divide-y divide-gray-50">
            <button
              v-for="p in results.posts"
              :key="'b-' + p.id"
              @mousedown.prevent="goToResult(p.url)"
              class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-indigo-50/50 transition-colors text-left"
            >
              <div class="w-8 h-8 bg-indigo-50 rounded-lg flex-shrink-0 flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ p.title }}</p>
                <span v-if="p.category" class="text-[11px] text-gray-400">{{ p.category }}</span>
              </div>
              <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>

        <!-- No results -->
        <div v-if="!hasResults && query.length >= 2 && !loading" class="px-4 py-8 text-center">
          <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <p class="text-sm text-gray-500">Không tìm thấy kết quả cho "{{ query }}"</p>
        </div>
      </div>
    </Transition>
  </div>
</template>
