<script setup lang="ts">
import type { ComponentType, Product } from '~/types'

const config = useRuntimeConfig()
const cart = useCart()
const toast = useToast()

const { data: componentTypes } = await useFetch<ComponentType[]>(`${config.public.apiBase}/builder/component-types`)

const build = ref<Record<number, number>>({})
const selectedProducts = ref<Record<number, Product>>({})
const compatibilityIssues = ref<any[]>([])
const totalPrice = ref(0)
const totalTdp = ref(0)
const isChecking = ref(false)

const activeTypeId = ref<number | null>(null)
const availableProducts = ref<any[]>([])
const isLoadingProducts = ref(false)
const searchQuery = ref('')

const filteredProducts = computed(() => {
  let items = [...availableProducts.value]
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    items = items.filter((item: any) =>
      item.product.name.toLowerCase().includes(q) ||
      item.product.brand?.name?.toLowerCase().includes(q)
    )
  }
  // Compatible first, then by price asc
  items.sort((a: any, b: any) => {
    if (a.is_compatible !== b.is_compatible) return a.is_compatible ? -1 : 1
    const pa = Number(a.product.sale_price || a.product.price) || 0
    const pb = Number(b.product.sale_price || b.product.price) || 0
    return pa - pb
  })
  return items
})

const compatibleCount = computed(() => availableProducts.value.filter((i: any) => i.is_compatible).length)
const selectedCount = computed(() => Object.keys(selectedProducts.value).length)
const requiredTypes = computed(() => componentTypes.value?.filter(t => t.is_required) || [])
const missingRequired = computed(() => requiredTypes.value.filter(t => !selectedProducts.value[t.id]))

const selectComponentType = async (typeId: number) => {
  if (activeTypeId.value === typeId) { activeTypeId.value = null; return }
  activeTypeId.value = typeId
  searchQuery.value = ''
  isLoadingProducts.value = true
  try {
    const type = componentTypes.value?.find((t) => t.id === typeId)
    if (!type) return
    const response = await $fetch<any>(`${config.public.apiBase}/builder/compatible/${type.slug}`, {
      method: 'POST',
      body: { build: build.value },
    })
    availableProducts.value = response.products || []
  } catch (error) {
    console.error('Error loading products:', error)
    availableProducts.value = []
  } finally {
    isLoadingProducts.value = false
  }
}

const addToBuild = async (typeId: number, productData: any) => {
  const product = productData.product
  build.value[typeId] = product.id
  selectedProducts.value[typeId] = product
  activeTypeId.value = null
  calculateTotals()
  await checkCompatibility()
}

const removeFromBuild = async (typeId: number) => {
  delete build.value[typeId]
  delete selectedProducts.value[typeId]
  calculateTotals()
  await checkCompatibility()
}

const calculateTotals = () => {
  totalPrice.value = Object.values(selectedProducts.value).reduce((sum: number, p: any) => {
    return sum + (Number(p.sale_price) || Number(p.price) || 0)
  }, 0)
  totalTdp.value = Object.values(selectedProducts.value).reduce((sum: number, p: any) => {
    const tdpSpec = p.specifications?.find((s: any) => s.specification_key?.key === 'tdp')
    return sum + (tdpSpec ? parseInt(tdpSpec.value) : 0)
  }, 0)
}

const checkCompatibility = async () => {
  if (Object.keys(build.value).length < 2) { compatibilityIssues.value = []; return }
  isChecking.value = true
  try {
    const response = await $fetch<any>(`${config.public.apiBase}/builder/check`, {
      method: 'POST',
      body: { build: build.value },
    })
    compatibilityIssues.value = response.issues || []
  } catch (error) {
    console.error('Error checking compatibility:', error)
  } finally {
    isChecking.value = false
  }
}

const isAddingToCart = ref(false)

const addAllToCart = async () => {
  if (isAddingToCart.value) return
  isAddingToCart.value = true
  try {
    let successCount = 0
    for (const product of Object.values(selectedProducts.value)) {
      const ok = await cart.addItem(product.id, 1)
      if (ok) successCount++
    }
    if (successCount > 0) {
      toast.add({
        title: 'Đã thêm vào giỏ hàng',
        description: `${successCount} linh kiện đã được thêm vào giỏ.`,
        icon: 'i-heroicons-check-circle',
        color: 'success',
      })
      navigateTo('/cart')
    } else {
      toast.add({
        title: 'Không thể thêm vào giỏ',
        description: 'Đã xảy ra lỗi. Vui lòng thử lại.',
        icon: 'i-heroicons-exclamation-triangle',
        color: 'error',
      })
    }
  } catch (error) {
    console.error('Error adding build to cart:', error)
    toast.add({
      title: 'Lỗi',
      description: 'Không thể thêm cấu hình vào giỏ hàng.',
      icon: 'i-heroicons-exclamation-triangle',
      color: 'error',
    })
  } finally {
    isAddingToCart.value = false
  }
}

const resetBuild = () => {
  build.value = {}
  selectedProducts.value = {}
  compatibilityIssues.value = []
  totalPrice.value = 0
  totalTdp.value = 0
  activeTypeId.value = null
}

const formatPrice = (v: any) => new Intl.NumberFormat('vi-VN').format(Number(v) || 0) + '₫'

// Print quotation
const printQuotation = () => {
  const products = Object.entries(selectedProducts.value).map(([typeId, product]) => {
    const type = componentTypes.value?.find(t => t.id === Number(typeId))
    const price = Number(product.sale_price) || Number(product.price) || 0
    return { typeName: type?.name || '', product, price, quantity: 1 }
  })

  const total = products.reduce((sum, p) => sum + p.price * p.quantity, 0)
  const date = new Date().toLocaleDateString('vi-VN')

  const html = `<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Báo giá cấu hình PC - PC Shop</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1a1a1a; padding: 40px; font-size: 13px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 2px solid #2563eb; }
    .logo-area { display: flex; align-items: center; gap: 12px; }
    .logo-box { width: 48px; height: 48px; background: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px; }
    .logo-text { font-size: 22px; font-weight: 700; color: #1e293b; }
    .logo-text small { display: block; font-size: 11px; font-weight: 400; color: #64748b; }
    .company-info { text-align: right; font-size: 12px; color: #475569; line-height: 1.8; }
    .company-info strong { color: #1e293b; }
    .title { text-align: center; margin-bottom: 24px; }
    .title h1 { font-size: 20px; color: #1e293b; margin-bottom: 4px; }
    .title p { font-size: 12px; color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    thead th { background: #2563eb; color: white; padding: 10px 12px; font-size: 12px; font-weight: 600; text-align: left; }
    thead th:first-child { border-radius: 6px 0 0 0; }
    thead th:last-child { border-radius: 0 6px 0 0; text-align: right; }
    thead th.center { text-align: center; }
    thead th.right { text-align: right; }
    tbody td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
    tbody tr:hover { background: #f8fafc; }
    .product-name { font-weight: 500; }
    .product-brand { color: #64748b; font-size: 11px; }
    .center { text-align: center; }
    .right { text-align: right; }
    .total-row { background: #f1f5f9; }
    .total-row td { padding: 12px; font-weight: 700; font-size: 14px; border-bottom: none; }
    .total-price { color: #2563eb; font-size: 16px; }
    .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; }
    .notes { margin-top: 24px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; }
    .notes h3 { font-size: 13px; margin-bottom: 8px; color: #334155; }
    .notes ul { list-style: disc; padding-left: 20px; font-size: 12px; color: #475569; line-height: 1.8; }
    .tdp-info { margin-top: 12px; padding: 10px 16px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe; font-size: 12px; color: #1e40af; }
    @media print {
      body { padding: 20px; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="logo-area">
      <div class="logo-box">PC</div>
      <div class="logo-text">
        PC Shop
        <small>Chuyên PC, Laptop & Linh kiện</small>
      </div>
    </div>
    <div class="company-info">
      <strong>CÔNG TY TNHH PC SHOP</strong><br>
      Hotline: 1900 1234<br>
      Email: support@pcshop.vn<br>
      Website: pcshop.vn
    </div>
  </div>

  <div class="title">
    <h1>BÁO GIÁ CẤU HÌNH MÁY TÍNH</h1>
    <p>Ngày: ${date}</p>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:40px">STT</th>
        <th>Loại</th>
        <th>Sản phẩm</th>
        <th class="center" style="width:60px">SL</th>
        <th class="right" style="width:130px">Đơn giá</th>
        <th class="right" style="width:130px">Thành tiền</th>
      </tr>
    </thead>
    <tbody>
      ${products.map((p, i) => `
      <tr>
        <td class="center">${i + 1}</td>
        <td>${p.typeName}</td>
        <td>
          <div class="product-name">${p.product.name}</div>
          ${p.product.brand?.name ? `<div class="product-brand">${p.product.brand.name}</div>` : ''}
        </td>
        <td class="center">${p.quantity}</td>
        <td class="right">${new Intl.NumberFormat('vi-VN').format(p.price)}₫</td>
        <td class="right">${new Intl.NumberFormat('vi-VN').format(p.price * p.quantity)}₫</td>
      </tr>`).join('')}
      <tr class="total-row">
        <td colspan="5" class="right">TỔNG CỘNG:</td>
        <td class="right total-price">${new Intl.NumberFormat('vi-VN').format(total)}₫</td>
      </tr>
    </tbody>
  </table>

  ${totalTdp.value > 0 ? `<div class="tdp-info">⚡ Tổng công suất tiêu thụ (TDP): <strong>${totalTdp.value}W</strong></div>` : ''}

  <div class="notes">
    <h3>Ghi chú</h3>
    <ul>
      <li>Giá trên đã bao gồm VAT</li>
      <li>Báo giá có hiệu lực trong 7 ngày</li>
      <li>Bảo hành theo chính sách từng hãng</li>
      <li>Miễn phí lắp ráp và cài đặt</li>
    </ul>
  </div>

  <div class="footer">
    <span>PC Shop - pcshop.vn</span>
    <span>Báo giá tự động từ hệ thống Build PC</span>
  </div>

  <script>window.onload = function() { window.print(); }<\/script>
</body>
</html>`

  const printWindow = window.open('', '_blank')
  if (printWindow) {
    printWindow.document.write(html)
    printWindow.document.close()
  }
}

// Save configuration
const isSaving = ref(false)
const saveBuild = async () => {
  if (isSaving.value || selectedCount.value === 0) return
  isSaving.value = true
  try {
    await $fetch(`${config.public.apiBase}/builder/save`, {
      method: 'POST',
      body: {
        build: build.value,
        total_price: totalPrice.value,
        total_tdp: totalTdp.value,
      },
    })
    toast.add({
      title: 'Đã lưu cấu hình',
      description: 'Cấu hình đã được lưu vào tài khoản của bạn.',
      icon: 'i-heroicons-check-circle',
      color: 'success',
    })
  } catch (error: any) {
    if (error?.response?.status === 401) {
      toast.add({
        title: 'Cần đăng nhập',
        description: 'Vui lòng đăng nhập để lưu cấu hình.',
        icon: 'i-heroicons-exclamation-triangle',
        color: 'warning',
      })
      navigateTo('/auth/login?redirect=/configurator')
    } else {
      console.error('Error saving build:', error)
      toast.add({
        title: 'Lỗi',
        description: 'Không thể lưu cấu hình. Vui lòng thử lại.',
        icon: 'i-heroicons-exclamation-triangle',
        color: 'error',
      })
    }
  } finally {
    isSaving.value = false
  }
}

useSeoMeta({
  title: 'Xây dựng cấu hình PC - PC Shop',
  description: 'Công cụ xây dựng cấu hình PC thông minh với kiểm tra tương thích tự động',
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div class="container mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Xây dựng cấu hình PC</h1>
        <p class="text-sm text-gray-500 mt-1">Chọn linh kiện và hệ thống sẽ tự động kiểm tra tương thích</p>
      </div>

      <div class="flex flex-col lg:flex-row gap-6">
        <!-- LEFT: Component list -->
        <div class="flex-1 min-w-0 space-y-2">
          <template v-if="componentTypes">
            <div v-for="type in componentTypes" :key="type.id">
              <div
                :class="[
                  'bg-white rounded-lg border transition-all overflow-hidden',
                  activeTypeId === type.id ? 'border-primary-500 shadow-sm' :
                  selectedProducts[type.id] ? 'border-gray-200' : 'border-gray-200 hover:border-gray-300',
                ]"
              >
                <!-- Slot row -->
                <div class="flex items-center gap-3 px-4 py-3 cursor-pointer" @click="selectComponentType(type.id)">
                  <!-- Number / check -->
                  <div
                    :class="[
                      'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0',
                      selectedProducts[type.id] ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-400',
                    ]"
                  >
                    <svg v-if="selectedProducts[type.id]" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                    <span v-else>{{ type.sort_order || componentTypes?.indexOf(type)! + 1 }}</span>
                  </div>

                  <!-- Info -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                      <span class="font-medium text-gray-900 text-sm">{{ type.name }}</span>
                      <span v-if="type.is_required" class="text-[10px] text-red-500 font-medium">*</span>
                    </div>
                    <p v-if="selectedProducts[type.id]" class="text-xs text-gray-500 truncate">
                      {{ selectedProducts[type.id]?.brand?.name }} {{ selectedProducts[type.id]?.name }}
                    </p>
                  </div>

                  <!-- Price / select -->
                  <div class="flex items-center gap-2 shrink-0">
                    <template v-if="selectedProducts[type.id]">
                      <span class="text-sm font-semibold text-primary-600">
                        {{ formatPrice(selectedProducts[type.id]?.sale_price || selectedProducts[type.id]?.price) }}
                      </span>
                      <button
                        class="text-gray-400 hover:text-red-500 transition-colors p-1"
                        title="Xóa"
                        @click.stop="removeFromBuild(type.id)"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </template>
                    <span v-else class="text-xs text-gray-400">Chọn</span>
                    <svg
                      :class="['w-4 h-4 text-gray-400 transition-transform', activeTypeId === type.id && 'rotate-180']"
                      fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>

                <!-- Expanded product list -->
                <div v-if="activeTypeId === type.id" class="border-t border-gray-100">
                  <!-- Search -->
                  <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                    <div class="relative">
                      <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                      <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Tìm kiếm..."
                        class="w-full border border-gray-200 rounded-md pl-8 pr-3 py-1.5 text-sm focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                      >
                    </div>
                  </div>

                  <!-- Loading -->
                  <div v-if="isLoadingProducts" class="flex items-center justify-center py-8 text-gray-400 text-sm">
                    <svg class="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Đang tải...
                  </div>

                  <!-- Empty -->
                  <div v-else-if="filteredProducts.length === 0" class="text-center py-8 text-gray-400 text-sm">
                    Không tìm thấy sản phẩm
                  </div>

                  <!-- Products -->
                  <div v-else class="max-h-80 overflow-y-auto">
                    <div
                      v-for="item in filteredProducts"
                      :key="item.product.id"
                      :class="[
                        'flex items-center gap-3 px-4 py-2.5 border-b border-gray-50 last:border-b-0 transition-colors',
                        item.is_compatible ? 'cursor-pointer hover:bg-primary-50/50' : 'opacity-40 cursor-not-allowed',
                        selectedProducts[type.id]?.id === item.product.id && 'bg-primary-50',
                      ]"
                      @click="item.is_compatible && addToBuild(type.id, item)"
                    >
                      <!-- Image -->
                      <div class="w-10 h-10 bg-gray-100 rounded overflow-hidden shrink-0 flex items-center justify-center">
                        <img v-if="item.product.images?.[0]" :src="item.product.images[0].url" :alt="item.product.name" class="w-full h-full object-cover">
                        <span v-else class="text-gray-300 text-xs">N/A</span>
                      </div>

                      <!-- Info -->
                      <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 truncate">{{ item.product.name }}</p>
                        <p v-if="item.product.brand" class="text-xs text-gray-400">{{ item.product.brand.name }}</p>
                        <p v-if="!item.is_compatible && item.issues?.[0]" class="text-xs text-red-500 mt-0.5">
                          {{ item.issues[0]?.message }}
                        </p>
                      </div>

                      <!-- Price -->
                      <div class="text-right shrink-0">
                        <p class="text-sm font-semibold text-gray-900">
                          {{ formatPrice(item.product.sale_price || item.product.price) }}
                        </p>
                        <p v-if="item.product.sale_price" class="text-xs text-gray-400 line-through">
                          {{ formatPrice(item.product.price) }}
                        </p>
                      </div>

                      <!-- Radio -->
                      <div v-if="item.is_compatible" class="shrink-0">
                        <div
                          :class="[
                            'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                            selectedProducts[type.id]?.id === item.product.id
                              ? 'border-primary-500 bg-primary-500' : 'border-gray-300',
                          ]"
                        >
                          <div v-if="selectedProducts[type.id]?.id === item.product.id" class="w-2 h-2 rounded-full bg-white" />
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Footer -->
                  <div class="px-4 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-400 flex justify-between">
                    <span>{{ compatibleCount }} sản phẩm tương thích</span>
                    <button class="text-primary-500 hover:underline" @click="activeTypeId = null">Đóng</button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- RIGHT: Summary -->
        <div class="lg:w-80 shrink-0">
          <div class="bg-white rounded-lg border border-gray-200 sticky top-20 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
              <h2 class="font-semibold text-gray-900">Cấu hình đã chọn</h2>
            </div>

            <!-- Selected list -->
            <div class="divide-y divide-gray-50">
              <template v-if="componentTypes">
                <template v-for="type in componentTypes" :key="type.id">
                  <div v-if="selectedProducts[type.id]" class="flex items-center gap-2 px-4 py-2">
                    <div class="flex-1 min-w-0">
                      <p class="text-[11px] text-gray-400 uppercase">{{ type.name }}</p>
                      <p class="text-xs text-gray-700 truncate">{{ selectedProducts[type.id]?.name }}</p>
                    </div>
                    <span class="text-xs font-medium text-gray-900 shrink-0">
                      {{ formatPrice(selectedProducts[type.id]?.sale_price || selectedProducts[type.id]?.price) }}
                    </span>
                  </div>
                </template>
              </template>
              <div v-if="selectedCount === 0" class="px-4 py-6 text-center text-gray-400 text-sm">
                Chưa chọn linh kiện nào
              </div>
            </div>

            <!-- Missing -->
            <div v-if="missingRequired.length > 0 && selectedCount > 0" class="px-4 py-2 bg-amber-50 border-t border-amber-100">
              <p class="text-[11px] text-amber-600 font-medium mb-1">Còn thiếu:</p>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="t in missingRequired" :key="t.id"
                  class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 cursor-pointer hover:bg-amber-200"
                  @click="selectComponentType(t.id)"
                >
                  {{ t.name }}
                </span>
              </div>
            </div>

            <!-- Compatibility -->
            <div v-if="compatibilityIssues.length > 0" class="px-4 py-2 border-t border-gray-100 space-y-1">
              <div
                v-for="(issue, idx) in compatibilityIssues" :key="idx"
                :class="['text-xs p-2 rounded', issue.type === 'error' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600']"
              >
                {{ issue.message }}
              </div>
            </div>
            <div v-else-if="selectedCount >= 2" class="px-4 py-2 border-t border-gray-100">
              <p class="text-xs text-green-600 bg-green-50 rounded p-2">Tất cả linh kiện tương thích</p>
            </div>

            <!-- Total -->
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
              <div v-if="totalTdp > 0" class="flex justify-between text-xs text-gray-500 mb-2">
                <span>Tổng TDP</span>
                <span>{{ totalTdp }}W</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Tổng tiền</span>
                <span class="text-xl font-bold text-primary-600">{{ formatPrice(totalPrice) }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="px-4 py-3 border-t border-gray-100 space-y-2">
              <button
                :disabled="selectedCount === 0 || isAddingToCart || compatibilityIssues.some(i => i.type === 'error')"
                class="w-full py-2.5 rounded-lg text-sm font-semibold bg-primary-500 text-white hover:bg-primary-600 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed transition-colors"
                @click="addAllToCart"
              >
                <span v-if="isAddingToCart" class="flex items-center justify-center gap-2">
                  <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                  Đang thêm...
                </span>
                <span v-else>Thêm tất cả vào giỏ hàng</span>
              </button>
              <button
                :disabled="selectedCount === 0"
                class="w-full py-2 rounded-lg text-xs font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-1.5"
                @click="printQuotation"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                In báo giá
              </button>
              <div class="flex gap-2">
                <button
                  :disabled="selectedCount === 0 || isSaving"
                  class="flex-1 py-2 rounded-lg text-xs border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  @click="saveBuild"
                >
                  {{ isSaving ? 'Đang lưu...' : 'Lưu cấu hình' }}
                </button>
                <button
                  :disabled="selectedCount === 0"
                  class="flex-1 py-2 rounded-lg text-xs border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  @click="resetBuild"
                >
                  Làm lại
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile bottom bar -->
    <div class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 px-4 py-3 shadow-lg">
      <div class="flex items-center gap-3">
        <div class="flex-1">
          <p class="text-xs text-gray-400">{{ selectedCount }} linh kiện</p>
          <p class="text-lg font-bold text-primary-600">{{ formatPrice(totalPrice) }}</p>
        </div>
        <button
          :disabled="selectedCount === 0"
          class="px-6 py-2.5 rounded-lg text-sm font-semibold bg-primary-500 text-white hover:bg-primary-600 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed transition-colors"
          @click="addAllToCart"
        >
          Mua ngay
        </button>
      </div>
    </div>
  </div>
</template>
