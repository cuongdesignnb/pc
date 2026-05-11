<script setup lang="ts">
import type { Product, ComponentType } from '~/types'

interface ProductDetailResponse {
  product: Product
  related: Product[]
}

interface SuggestionGroup {
  component_type: ComponentType
  products: Product[]
}

interface SuggestionsResponse {
  suggestions: SuggestionGroup[]
}

const config = useRuntimeConfig()
const route = useRoute()
const { user, isAuthenticated, token } = useAuth()

const slug = route.params.product as string

// Fetch product
const { data } = await useFetch<ProductDetailResponse>(`${config.public.apiBase}/products/${slug}`)

const product = computed(() => data.value?.product)
const relatedProducts = computed(() => data.value?.related || [])

// Compatible product suggestions (lazy AJAX)
const suggestions = ref<SuggestionGroup[]>([])
const loadingSuggestions = ref(false)
const activeSuggestionTab = ref(0)

const loadSuggestions = async () => {
  if (!product.value?.component_type) return
  loadingSuggestions.value = true
  try {
    const res = await $fetch<SuggestionsResponse>(
      `${config.public.apiBase}/products/${slug}/suggestions`
    )
    suggestions.value = res.suggestions || []
  } catch (e) {
    console.error('Error loading suggestions:', e)
  } finally {
    loadingSuggestions.value = false
  }
}

// Auto-load suggestions when product has a component type
watch(
  () => product.value?.component_type,
  (ct) => {
    if (ct) loadSuggestions()
  },
  { immediate: true }
)

// Format price
const formatPrice = (price: number) => {
  return new Intl.NumberFormat('vi-VN').format(price) + '₫'
}

// Selected image
const selectedImage = ref(0)

// Image Modal & Zoom
const isZoomed = ref(false)
const isImageModalOpen = ref(false)
const modalZoomScale = ref(1)

const nextImage = () => {
  if (!product.value?.images) return
  selectedImage.value = (selectedImage.value + 1) % product.value.images.length
  modalZoomScale.value = 1
}

const prevImage = () => {
  if (!product.value?.images) return
  selectedImage.value = (selectedImage.value - 1 + product.value.images.length) % product.value.images.length
  modalZoomScale.value = 1
}

const toggleZoom = () => {
  modalZoomScale.value = modalZoomScale.value === 1 ? 2 : 1
}

// Quantity
const quantity = ref(1)

// Add to cart
const addingToCart = ref(false)
const addedToCart = ref(false)
const cart = useCart()
const toast = useToast()

const addToCart = async () => {
  if (!product.value) return
  addingToCart.value = true
  try {
    const success = await cart.addItem(product.value.id, quantity.value)
    if (success) {
      addedToCart.value = true
      toast.add({
        title: 'Đã thêm vào giỏ hàng',
        description: `${product.value.name} x${quantity.value}`,
        icon: 'i-heroicons-check-circle',
        color: 'success',
      })
      setTimeout(() => { addedToCart.value = false }, 2500)
    } else {
      toast.add({
        title: 'Không thể thêm vào giỏ',
        description: 'Đã xảy ra lỗi. Vui lòng thử lại.',
        icon: 'i-heroicons-exclamation-triangle',
        color: 'error',
      })
    }
  } catch (error) {
    console.error('Error adding to cart:', error)
    toast.add({
      title: 'Lỗi',
      description: 'Không thể thêm sản phẩm vào giỏ hàng.',
      icon: 'i-heroicons-exclamation-triangle',
      color: 'error',
    })
  } finally {
    addingToCart.value = false
  }
}

// Calculate discount percentage
const discountPercent = computed(() => {
  if (!product.value?.sale_price) return 0
  return Math.round((1 - product.value.sale_price / product.value.price) * 100)
})

// Average rating
const averageRating = computed(() => {
  const reviews = product.value?.reviews
  if (!reviews?.length) return 0
  const sum = reviews.reduce((acc: number, r: any) => acc + r.rating, 0)
  return (sum / reviews.length).toFixed(1)
})

const reviewForm = reactive({
  rating: 5,
  title: '',
  body: '',
  guest_name: '',
  guest_email: '',
})
const submittingReview = ref(false)

const canSubmitReview = computed(() => {
  if (!reviewForm.body.trim()) return false
  if (!isAuthenticated.value && (!reviewForm.guest_name.trim() || !reviewForm.guest_email.trim())) {
    return false
  }
  return true
})

const scrollToReviews = () => {
  if (!process.client) return
  document.getElementById('product-reviews')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const submitReview = async () => {
  if (!product.value || !canSubmitReview.value || submittingReview.value) return

  submittingReview.value = true

  try {
    const headers: Record<string, string> = {}
    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`
    }

    await $fetch(`${config.public.apiBase}/products/${slug}/reviews`, {
      method: 'POST',
      headers: Object.keys(headers).length ? headers : undefined,
      body: {
        rating: reviewForm.rating,
        title: reviewForm.title || null,
        body: reviewForm.body,
        guest_name: reviewForm.guest_name || null,
        guest_email: reviewForm.guest_email || null,
      },
    })

    toast.add({
      title: 'Đã gửi đánh giá',
      description: 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi được duyệt.',
      icon: 'i-heroicons-check-circle',
      color: 'success',
    })

    reviewForm.title = ''
    reviewForm.body = ''
    if (!isAuthenticated.value) {
      reviewForm.guest_name = ''
      reviewForm.guest_email = ''
    }
  } catch (error: any) {
    toast.add({
      title: 'Không thể gửi đánh giá',
      description: error?.data?.message || 'Vui lòng kiểm tra thông tin và thử lại.',
      icon: 'i-heroicons-exclamation-triangle',
      color: 'error',
    })
  } finally {
    submittingReview.value = false
  }
}

// SEO
useSeoMeta({
  title: () => product.value?.name ? `${product.value.name} - PC Shop` : 'Sản phẩm - PC Shop',
  description: () => product.value?.short_description || product.value?.meta_description,
})

// Handle keyboard navigation for modal
const handleKeydown = (e: KeyboardEvent) => {
  if (!isImageModalOpen.value) return
  if (e.key === 'ArrowRight') nextImage()
  if (e.key === 'ArrowLeft') prevImage()
  if (e.key === 'Escape') isImageModalOpen.value = false
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="min-h-screen bg-gray-50/60">
    <div class="container mx-auto px-4 py-6 lg:py-10 max-w-7xl">
      <template v-if="product">
        <!-- Breadcrumb -->
        <nav class="mb-8 flex items-center gap-2 text-sm text-gray-500">
          <NuxtLink to="/" class="hover:text-primary-600 transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Trang chủ
          </NuxtLink>
          <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
          <NuxtLink to="/products" class="hover:text-primary-600 transition-colors">Sản phẩm</NuxtLink>
          <template v-if="product.category">
            <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            <NuxtLink :to="`/${product.category.slug}`" class="hover:text-primary-600 transition-colors">
              {{ product.category.name }}
            </NuxtLink>
          </template>
          <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
          <span class="text-gray-800 font-medium truncate max-w-xs">{{ product.name }}</span>
        </nav>

        <!-- ===== MAIN: Image + Info ===== -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:p-8 mb-8">
          <div class="grid lg:grid-cols-2 gap-8 lg:gap-12">
            <!-- Images -->
            <div class="space-y-4">
              <!-- Main image -->
              <div 
                class="relative aspect-square bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl overflow-hidden group cursor-zoom-in border border-gray-100"
                @click="isImageModalOpen = true"
              >
                <!-- Sale badge -->
                <div v-if="product.sale_price" class="absolute top-4 left-4 z-10">
                  <div class="bg-gradient-to-r from-red-500 to-rose-500 text-white text-sm font-bold px-3 py-1.5 rounded-full shadow-lg shadow-red-200">
                    -{{ discountPercent }}%
                  </div>
                </div>
                <img 
                  v-if="product.images?.[selectedImage]" 
                  :src="product.images?.[selectedImage]?.url" 
                  :alt="product.name"
                  :class="[
                    'w-full h-full object-contain transition-transform duration-500',
                    'group-hover:scale-105'
                  ]"
                >
                <div v-else class="w-full h-full flex items-center justify-center">
                  <div class="text-center">
                    <svg class="w-24 h-24 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="mt-2 text-sm text-gray-400">Chưa có ảnh</p>
                  </div>
                </div>
                <!-- Zoom hint -->
                <div class="absolute bottom-3 right-3 bg-black/50 text-white text-xs px-3 py-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-all flex items-center gap-1.5 backdrop-blur-sm">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                  Xem chi tiết
                </div>
              </div>
              
              <!-- Thumbnails -->
              <div v-if="(product.images?.length ?? 0) > 1" class="flex gap-3 justify-center">
                <button 
                  v-for="(img, idx) in product.images" 
                  :key="img.id"
                  :class="[
                    'w-20 h-20 rounded-xl overflow-hidden border-2 transition-all duration-200 hover:shadow-md',
                    selectedImage === idx 
                      ? 'border-primary-500 ring-2 ring-primary-200 shadow-md' 
                      : 'border-gray-200 hover:border-primary-300'
                  ]"
                  @click="selectedImage = idx; isZoomed = false"
                >
                  <img :src="img.url" :alt="`${product.name} - ${idx + 1}`" class="w-full h-full object-cover">
                </button>
              </div>
            </div>

            <!-- Product Info -->
            <div class="flex flex-col">
              <!-- Brand badge -->
              <div v-if="product.brand" class="mb-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold uppercase tracking-wide">
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  {{ product.brand.name }}
                </span>
              </div>
              
              <!-- Name -->
              <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 leading-tight mb-4">{{ product.name }}</h1>

              <!-- Rating + Reviews count -->
              <div class="flex items-center gap-3 mb-5">
                <div class="flex items-center gap-1">
                  <template v-for="i in 5" :key="i">
                    <svg 
                      class="w-5 h-5" 
                      :class="i <= Math.round(Number(averageRating)) ? 'text-amber-400' : 'text-gray-200'"
                      fill="currentColor" viewBox="0 0 20 20"
                    >
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                  </template>
                </div>
                <span v-if="Number(averageRating) > 0" class="text-sm font-semibold text-gray-700">{{ averageRating }}</span>
                <span class="text-sm text-gray-400">|</span>
                <button 
                  @click="scrollToReviews"
                  class="text-sm text-primary-600 hover:text-primary-700 font-medium transition-colors"
                >
                  {{ product.reviews?.length || 0 }} đánh giá
                </button>
                <span class="text-sm text-gray-400">|</span>
                <span class="text-sm text-gray-500">SKU: {{ product.sku }}</span>
              </div>

              <!-- Divider -->
              <div class="border-t border-gray-100 my-1"></div>

              <!-- Price section -->
              <div class="bg-gradient-to-r from-primary-50/80 to-orange-50/50 rounded-xl p-5 my-5">
                <div class="flex items-end gap-3">
                  <span class="text-3xl lg:text-4xl font-extrabold text-primary-600">
                    {{ formatPrice(product.sale_price || product.price) }}
                  </span>
                  <span v-if="product.sale_price" class="text-lg text-gray-400 line-through mb-0.5">
                    {{ formatPrice(product.price) }}
                  </span>
                  <span v-if="product.sale_price" class="mb-1 inline-flex items-center gap-1 px-2.5 py-0.5 bg-red-500 text-white text-xs font-bold rounded-md">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    {{ discountPercent }}%
                  </span>
                </div>
              </div>

              <!-- Short description -->
              <p v-if="product.short_description" class="text-gray-600 text-[15px] leading-relaxed mb-5">
                {{ product.short_description }}
              </p>

              <!-- Stock status -->
              <div class="mb-5">
                <div v-if="product.quantity > 0" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 border border-emerald-200">
                  <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                  </span>
                  <span class="text-sm font-medium text-emerald-700">Còn {{ product.quantity }} sản phẩm</span>
                </div>
                <div v-else class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-red-50 border border-red-200">
                  <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                  <span class="text-sm font-medium text-red-600">Hết hàng</span>
                </div>
              </div>

              <!-- Quantity & Add to cart -->
              <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-6">
                <div class="flex items-center border-2 border-gray-200 rounded-xl overflow-hidden">
                  <button 
                    class="px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors disabled:opacity-30"
                    :disabled="quantity <= 1"
                    @click="quantity--"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/></svg>
                  </button>
                  <input 
                    v-model.number="quantity" 
                    type="number" 
                    min="1" 
                    :max="product.quantity"
                    class="w-14 text-center border-0 focus:ring-0 font-semibold text-gray-800"
                  >
                  <button 
                    class="px-4 py-3 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors disabled:opacity-30"
                    :disabled="quantity >= product.quantity"
                    @click="quantity++"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                  </button>
                </div>

                <button
                  :disabled="product.quantity === 0 || addingToCart"
                  :class="[
                    'flex-1 sm:flex-none flex items-center justify-center gap-2.5 px-8 py-3.5 rounded-xl font-semibold text-white transition-all duration-300 shadow-lg',
                    addedToCart 
                      ? 'bg-emerald-500 shadow-emerald-200'
                      : 'bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 shadow-primary-200 hover:shadow-primary-300 hover:shadow-xl active:scale-[0.98]',
                    (product.quantity === 0 || addingToCart) && 'opacity-50 cursor-not-allowed !shadow-none'
                  ]"
                  @click="addToCart"
                >
                  <svg v-if="addingToCart" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                  <svg v-else-if="addedToCart" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                  <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                  <span>{{ addedToCart ? 'Đã thêm!' : 'Thêm vào giỏ hàng' }}</span>
                </button>
              </div>

              <!-- Trust badges -->
              <div class="grid grid-cols-2 gap-3 mt-auto pt-5 border-t border-gray-100">
                <div v-if="product.warranty_months" class="flex items-center gap-3 p-3 rounded-xl bg-blue-50/70">
                  <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                  </div>
                  <div>
                    <p class="text-xs text-blue-600 font-semibold">Bảo hành</p>
                    <p class="text-sm font-bold text-blue-800">{{ product.warranty_months }} tháng</p>
                  </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-green-50/70">
                  <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  </div>
                  <div>
                    <p class="text-xs text-green-600 font-semibold">Cam kết</p>
                    <p class="text-sm font-bold text-green-800">Chính hãng 100%</p>
                  </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-purple-50/70">
                  <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  </div>
                  <div>
                    <p class="text-xs text-purple-600 font-semibold">Đổi trả</p>
                    <p class="text-sm font-bold text-purple-800">Trong 30 ngày</p>
                  </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-orange-50/70">
                  <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                  </div>
                  <div>
                    <p class="text-xs text-orange-600 font-semibold">Giao hàng</p>
                    <p class="text-sm font-bold text-orange-800">Nhanh chóng</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ===== MÔ TẢ (trái) + THÔNG SỐ (phải) - NẰM NGANG FLEX ===== -->
        <div class="flex flex-col lg:flex-row gap-6 mb-8">
          <!-- Mô tả chi tiết — bên trái -->
          <div class="flex-1 min-w-0">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden h-full">
              <!-- Header -->
              <div class="flex items-center gap-2.5 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-primary-50/60 to-transparent">
                <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
                  <svg class="w-4.5 h-4.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h12"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Đánh giá chi tiết</h2>
              </div>
              <!-- Content -->
              <div class="p-6">
                <div v-if="product.description" class="prose prose-sm prose-gray max-w-none prose-headings:text-gray-800 prose-headings:font-bold prose-a:text-primary-600 prose-a:font-semibold prose-img:rounded-xl prose-p:leading-relaxed prose-p:text-gray-600" v-html="product.description"></div>
                <div v-else class="text-center py-16 text-gray-400">
                  <svg class="w-14 h-14 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h12"/></svg>
                  <p class="text-sm">Chưa có mô tả chi tiết</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Thông số kỹ thuật — bên phải (sticky) -->
          <div class="w-full lg:w-[420px] xl:w-[460px] flex-shrink-0">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden lg:sticky lg:top-4">
              <!-- Header -->
              <div class="flex items-center gap-2.5 px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-blue-50/70 to-transparent">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                  <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Thông số kỹ thuật</h2>
              </div>
              <!-- Specs Table -->
              <div v-if="product.parsed_specifications?.length" class="overflow-hidden">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="bg-gray-50/80">
                      <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 w-10">STT</th>
                      <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">Thông số</th>
                      <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">Chi tiết</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr 
                      v-for="(spec, index) in product.parsed_specifications" 
                      :key="index"
                      class="group hover:bg-blue-50/40 transition-colors"
                    >
                      <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-50 text-blue-600 text-xs font-bold">
                          {{ index + 1 }}
                        </span>
                      </td>
                      <td class="px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">
                        {{ spec.label }}
                      </td>
                      <td class="px-4 py-3 text-gray-600 font-medium text-primary-700">
                        {{ spec.value }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="p-8 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p class="text-sm">Chưa có thông số</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ===== ĐÁNH GIÁ SẢN PHẨM ===== -->
        <div id="product-reviews" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-amber-50/60 to-transparent">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                <svg class="w-4.5 h-4.5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
              </div>
              <h2 class="text-lg font-bold text-gray-900">Đánh giá sản phẩm</h2>
            </div>
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-amber-100 text-amber-700">
              {{ product.reviews?.length || 0 }} đánh giá
            </span>
          </div>

          <div class="p-6">
            <!-- Review form -->
            <div class="mb-6 rounded-xl border border-primary-100 bg-gradient-to-r from-primary-50/50 to-white p-5">
              <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                  <h3 class="text-base font-bold text-gray-900">Viết đánh giá của bạn</h3>
                  <p class="text-sm text-gray-500">Đánh giá sẽ được kiểm duyệt trước khi hiển thị công khai.</p>
                </div>
                <div v-if="isAuthenticated" class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1 rounded-full font-medium">
                  Đăng nhập: {{ user?.name }}
                </div>
                <div v-else class="text-sm text-gray-600 bg-white border border-gray-200 px-3 py-1 rounded-full">
                  Bạn có thể đánh giá không cần đăng nhập
                </div>
              </div>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-2">Số sao</label>
                  <div class="flex items-center gap-1.5">
                    <button
                      v-for="star in 5"
                      :key="`form-star-${star}`"
                      type="button"
                      class="transition-transform hover:scale-110"
                      @click="reviewForm.rating = star"
                    >
                      <svg
                        class="w-6 h-6"
                        :class="star <= reviewForm.rating ? 'text-amber-400' : 'text-gray-200'"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                      >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4" v-if="!isAuthenticated">
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Họ tên</label>
                    <input
                      v-model="reviewForm.guest_name"
                      type="text"
                      placeholder="Nhập họ tên"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-400"
                    >
                  </div>
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input
                      v-model="reviewForm.guest_email"
                      type="email"
                      placeholder="you@example.com"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-400"
                    >
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Tiêu đề (tuỳ chọn)</label>
                  <input
                    v-model="reviewForm.title"
                    type="text"
                    placeholder="Ví dụ: Hiệu năng tốt trong tầm giá"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-400"
                  >
                </div>

                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1">Nội dung đánh giá</label>
                  <textarea
                    v-model="reviewForm.body"
                    rows="4"
                    placeholder="Chia sẻ trải nghiệm thực tế của bạn về sản phẩm..."
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm resize-y focus:outline-none focus:ring-2 focus:ring-primary-200 focus:border-primary-400"
                  ></textarea>
                </div>

                <div class="flex justify-end">
                  <button
                    type="button"
                    :disabled="!canSubmitReview || submittingReview"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-60 disabled:cursor-not-allowed bg-primary-600 hover:bg-primary-700"
                    @click="submitReview"
                  >
                    <svg v-if="submittingReview" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    {{ submittingReview ? 'Đang gửi...' : 'Gửi đánh giá' }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Review summary -->
            <div v-if="product.reviews?.length" class="mb-6">
              <div class="flex flex-col sm:flex-row gap-6 p-5 bg-gradient-to-r from-amber-50 to-orange-50/30 rounded-xl border border-amber-100/80">
                <div class="text-center sm:pr-8 sm:border-r border-amber-200/80">
                  <div class="text-5xl font-extrabold text-amber-500">{{ averageRating }}</div>
                  <div class="flex items-center justify-center gap-0.5 mt-2">
                    <template v-for="i in 5" :key="i">
                      <svg 
                        class="w-4.5 h-4.5" 
                        :class="i <= Math.round(Number(averageRating)) ? 'text-amber-400' : 'text-gray-200'"
                        fill="currentColor" viewBox="0 0 20 20"
                      >
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                      </svg>
                    </template>
                  </div>
                  <p class="text-xs text-amber-600 mt-1 font-medium">{{ product.reviews.length }} lượt đánh giá</p>
                </div>
                <div class="flex-1 flex items-center">
                  <p class="text-sm text-amber-700/80 leading-relaxed">Đánh giá từ những khách hàng đã mua và sử dụng sản phẩm tại PC Shop.</p>
                </div>
              </div>
            </div>

            <!-- Review list -->
            <div v-if="product.reviews?.length" class="space-y-4">
              <div 
                v-for="review in product.reviews" 
                :key="review.id"
                class="p-5 rounded-xl border border-gray-100 hover:border-gray-200 hover:shadow-sm transition-all"
              >
                <div class="flex items-center gap-3 mb-3">
                  <div class="w-10 h-10 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-sm">
                    {{ review.user?.name?.[0]?.toUpperCase() || '?' }}
                  </div>
                  <div class="flex-1">
                    <p class="font-semibold text-gray-800">{{ review.user?.name || review.guest_name || 'Khách hàng' }}</p>
                    <div class="flex items-center gap-2">
                      <div class="flex gap-0.5">
                        <template v-for="i in 5" :key="i">
                          <svg 
                            class="w-3.5 h-3.5" 
                            :class="i <= review.rating ? 'text-amber-400' : 'text-gray-200'"
                            fill="currentColor" viewBox="0 0 20 20"
                          >
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                          </svg>
                        </template>
                      </div>
                      <span v-if="review.created_at" class="text-xs text-gray-400">
                        {{ new Date(review.created_at).toLocaleDateString('vi-VN') }}
                      </span>
                    </div>
                  </div>
                  <span class="px-2.5 py-1 text-xs rounded-full bg-green-50 text-green-600 border border-green-100 font-medium">
                    ✓ Đã mua hàng
                  </span>
                </div>
                <div class="pl-[52px]">
                  <p v-if="review.title" class="font-semibold text-gray-800 mb-1">{{ review.title }}</p>
                  <p class="text-gray-600 leading-relaxed">{{ review.body }}</p>
                  <p v-if="review.admin_reply" class="mt-3 text-sm text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
                    Phản hồi từ PC Shop: {{ review.admin_reply }}
                  </p>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12">
              <svg class="w-14 h-14 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              <p class="text-gray-400 mb-2">Chưa có đánh giá nào</p>
              <p class="text-sm text-gray-400">Hãy là người đầu tiên đánh giá sản phẩm này!</p>
            </div>
          </div>
        </div>

        <!-- ===== Compatible Product Suggestions ===== -->
        <div v-if="product.component_type && (loadingSuggestions || suggestions.length)" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
          <div class="px-6 lg:px-8 pt-6 lg:pt-8 pb-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
              </div>
              <div>
                <h2 class="text-xl font-bold text-gray-900">Linh kiện tương thích</h2>
                <p class="text-sm text-gray-500">Gợi ý sản phẩm phù hợp với {{ product.name }}</p>
              </div>
            </div>
          </div>
          
          <!-- Loading state -->
          <div v-if="loadingSuggestions" class="p-8 text-center">
            <div class="inline-flex items-center gap-3 text-gray-500">
              <svg class="animate-spin h-5 w-5 text-primary-500" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <span>Đang tìm linh kiện tương thích...</span>
            </div>
          </div>

          <!-- Suggestions tabs + products -->
          <template v-else-if="suggestions.length">
            <!-- Tabs -->
            <div class="flex border-b overflow-x-auto px-6 lg:px-8">
              <button
                v-for="(group, idx) in suggestions"
                :key="group.component_type.id"
                :class="[
                  'relative px-5 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                  activeSuggestionTab === idx
                    ? 'text-primary-600'
                    : 'text-gray-500 hover:text-gray-700'
                ]"
                @click="activeSuggestionTab = idx"
              >
                {{ group.component_type.name }}
                <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">
                  {{ group.products.length }}
                </span>
                <div v-if="activeSuggestionTab === idx" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-500 rounded-t-full"></div>
              </button>
            </div>

            <!-- Product grid for active tab -->
            <div class="p-6 lg:p-8">
              <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <NuxtLink
                  v-for="item in suggestions[activeSuggestionTab]?.products"
                  :key="item.id"
                  :to="`/${item.category?.slug || route.params.category}/${item.slug}`"
                  class="group bg-white border border-gray-100 rounded-xl overflow-hidden hover:shadow-lg hover:border-primary-200 transition-all duration-300"
                >
                  <div class="aspect-square bg-gray-50 flex items-center justify-center overflow-hidden">
                    <img
                      v-if="item.images?.[0]"
                      :src="item.images[0].url"
                      :alt="item.name"
                      class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    >
                    <span v-else class="text-4xl text-gray-300">📦</span>
                  </div>
                  <div class="p-3.5">
                    <p v-if="item.brand" class="text-xs text-gray-400 mb-1 font-medium">{{ item.brand.name }}</p>
                    <h4 class="text-sm font-semibold line-clamp-2 mb-2 group-hover:text-primary-600 transition-colors">
                      {{ item.name }}
                    </h4>
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-bold text-primary-600">
                        {{ formatPrice(item.sale_price || item.price) }}
                      </span>
                      <span v-if="item.sale_price" class="text-xs text-gray-400 line-through">
                        {{ formatPrice(item.price) }}
                      </span>
                    </div>
                  </div>
                  <div class="px-3.5 pb-3">
                    <span class="inline-flex items-center gap-1 text-xs text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-100 font-medium">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                      Tương thích
                    </span>
                  </div>
                </NuxtLink>
              </div>

              <p v-if="!suggestions[activeSuggestionTab]?.products?.length" class="text-center text-gray-500 py-8">
                Không tìm thấy sản phẩm tương thích
              </p>
            </div>
          </template>

          <!-- No suggestions -->
          <div v-else class="p-8 text-center text-gray-500">
            Chưa có dữ liệu tương thích cho sản phẩm này
          </div>
        </div>

        <!-- ===== Related Products ===== -->
        <div v-if="relatedProducts.length" class="mb-8">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Sản phẩm liên quan</h2>
            <NuxtLink :to="`/${product.category?.slug || route.params.category}`" class="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
              Xem tất cả
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </NuxtLink>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            <NuxtLink 
              v-for="related in relatedProducts" 
              :key="related.id"
              :to="`/${related.category?.slug || route.params.category}/${related.slug}`"
              class="group bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-lg hover:border-primary-200 transition-all duration-300"
            >
              <div class="aspect-square bg-gray-50 flex items-center justify-center overflow-hidden">
                <img 
                  v-if="related.images?.[0]" 
                  :src="related.images[0].url" 
                  :alt="related.name"
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                >
                <span v-else class="text-4xl text-gray-300">📦</span>
              </div>
              <div class="p-4">
                <h3 class="font-semibold text-gray-800 line-clamp-2 mb-2 group-hover:text-primary-600 transition-colors">{{ related.name }}</h3>
                <p class="text-lg font-bold text-primary-600">
                  {{ formatPrice(related.sale_price || related.price) }}
                </p>
                <p v-if="related.sale_price" class="text-sm text-gray-400 line-through">
                  {{ formatPrice(related.price) }}
                </p>
              </div>
            </NuxtLink>
          </div>
        </div>
      </template>
    </div>

    <!-- Image Gallery Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="isImageModalOpen" class="fixed inset-0 z-[9999] flex flex-col bg-black/95 backdrop-blur-md" tabindex="0" autofocus>
          <!-- Header Actions -->
          <div class="absolute top-0 left-0 right-0 p-4 lg:p-6 flex justify-between items-center z-50 bg-gradient-to-b from-black/80 to-transparent pointer-events-none">
            <div class="text-white/90 font-medium text-sm bg-black/40 px-4 py-1.5 rounded-full backdrop-blur-md border border-white/10 pointer-events-auto">
              {{ selectedImage + 1 }} / {{ product?.images?.length || 1 }}
            </div>
            <div class="flex items-center gap-3 pointer-events-auto">
              <button @click="toggleZoom" class="flex items-center justify-center w-10 h-10 text-white/70 hover:text-white bg-black/40 hover:bg-black/60 rounded-full transition-all border border-white/10 backdrop-blur-md" title="Phóng to/Thu nhỏ">
                <svg v-if="modalZoomScale === 1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/></svg>
              </button>
              <button @click="isImageModalOpen = false" class="flex items-center justify-center w-10 h-10 text-white/70 hover:text-white bg-black/40 hover:bg-black/60 rounded-full transition-all border border-white/10 backdrop-blur-md" title="Đóng (Esc)">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
          </div>

          <!-- Main Content -->
          <div class="flex-1 w-full h-full flex items-center justify-center overflow-hidden relative" @click="isImageModalOpen = false">
            <div class="w-full h-full flex items-center justify-center overflow-auto" @click.stop="toggleZoom">
              <img 
                v-if="product?.images?.[selectedImage]"
                :src="product.images[selectedImage]?.url" 
                :alt="product.name"
                :class="[
                  'max-w-full max-h-[85vh] object-contain transition-transform duration-300 ease-out origin-center select-none',
                  modalZoomScale > 1 ? 'cursor-zoom-out' : 'cursor-zoom-in'
                ]"
                :style="{ transform: `scale(${modalZoomScale})` }"
                draggable="false"
              >
            </div>
          </div>

          <!-- Navigation Buttons -->
          <button v-if="(product?.images?.length || 0) > 1" @click.stop="prevImage" class="absolute left-4 top-1/2 -translate-y-1/2 p-3 text-white/70 hover:text-white bg-black/40 hover:bg-black/80 border border-white/10 rounded-full transition-all backdrop-blur-md z-50">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <button v-if="(product?.images?.length || 0) > 1" @click.stop="nextImage" class="absolute right-4 top-1/2 -translate-y-1/2 p-3 text-white/70 hover:text-white bg-black/40 hover:bg-black/80 border border-white/10 rounded-full transition-all backdrop-blur-md z-50">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
          </button>

          <!-- Thumbnails -->
          <div v-if="(product?.images?.length || 0) > 1" class="absolute bottom-6 left-0 right-0 flex justify-center z-50 px-4 pointer-events-none">
            <div class="flex gap-3 px-4 py-3 bg-black/40 backdrop-blur-md border border-white/10 rounded-2xl overflow-x-auto max-w-full [&::-webkit-scrollbar]:hidden pointer-events-auto">
              <button 
                v-for="(img, idx) in product.images" 
                :key="img.id"
                @click.stop="selectedImage = idx; modalZoomScale = 1"
                :class="[
                  'w-16 h-16 flex-shrink-0 rounded-xl overflow-hidden border-2 transition-all duration-300',
                  selectedImage === idx ? 'border-primary-500 scale-105 shadow-[0_0_15px_rgba(var(--color-primary-500),0.5)]' : 'border-transparent opacity-50 hover:opacity-100 hover:scale-105'
                ]"
              >
                <img :src="img.url" class="w-full h-full object-cover">
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
