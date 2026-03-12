<script setup lang="ts">
const config = useRuntimeConfig()
const router = useRouter()
const { getHeaders: getCartHeaders } = useCartSession()

// Form data
const form = reactive({
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  shipping_address: '',
  shipping_city: '',
  shipping_district: '',
  shipping_ward: '',
  notes: '',
  payment_method: 'sepay',
})

// Fetch cart
const { data: cartData } = await useFetch<{ items: any[], total: number }>(
  `${config.public.apiBase}/cart`,
  { default: () => ({ items: [], total: 0 }), headers: getCartHeaders() }
)

const cartItems = computed(() => cartData.value?.items || [])
const cartTotal = computed(() => cartData.value?.total || 0)

// Location data
const selectedProvinceCode = ref('')
const provinces = ref<any[]>([])
const wards = ref<any[]>([])

// Fetch provinces on mount (SSR disabled for checkout)
onMounted(async () => {
  try {
    const data = await $fetch<any[]>(`${config.public.apiBase}/locations/provinces`)
    provinces.value = data || []
  } catch (e) {
    console.error('Failed to load provinces:', e)
  }
})

// Fetch wards when province changes
watch(selectedProvinceCode, async (code) => {
  form.shipping_city = provinces.value.find((p: any) => p.code === code)?.fullname || ''
  form.shipping_ward = ''
  wards.value = []
  if (code) {
    const data = await $fetch<any[]>(`${config.public.apiBase}/locations/provinces/${code}/wards`)
    wards.value = data || []
  }
})

// Shipping fee
const shippingFee = computed(() => {
  return cartTotal.value >= 500000 ? 0 : 30000
})

const orderTotal = computed(() => cartTotal.value + shippingFee.value)

// Place order
const isSubmitting = ref(false)
const orderResult = ref<any>(null)
const paymentData = ref<any>(null)
const toast = useToast()

const placeOrder = async () => {
  isSubmitting.value = true
  try {
    const response = await $fetch<any>(`${config.public.apiBase}/orders`, {
      method: 'POST',
      headers: getCartHeaders(),
      body: {
        ...form,
        items: cartItems.value.map((item: any) => ({
          product_id: item.product_id,
          quantity: item.quantity,
        })),
      },
    })

    orderResult.value = response.order
    toast.add({ title: 'Đặt hàng thành công!', description: `Mã đơn hàng: #${response.order.id}`, icon: 'i-heroicons-check-circle', color: 'success' })
    
    // If SePay payment, show QR code for bank transfer
    if (form.payment_method === 'sepay' && response.payment) {
      paymentData.value = response.payment
    } else {
      // COD - redirect to success page
      router.push(`/orders/${response.order.id}/success`)
    }
  } catch (error: any) {
    toast.add({ title: 'Lỗi đặt hàng', description: error.data?.message || 'Có lỗi xảy ra. Vui lòng thử lại.', icon: 'i-heroicons-exclamation-triangle', color: 'error' })
  } finally {
    isSubmitting.value = false
  }
}

// Check payment status (polling for SePay bank transfer)
const paymentVerified = ref(false)
const checkingPayment = ref(false)

const checkPaymentStatus = async () => {
  if (!orderResult.value?.id) return
  
  checkingPayment.value = true
  try {
    const response = await $fetch<any>(
      `${config.public.apiBase}/orders/${orderResult.value.id}/check-payment`
    )
    
    if (response.paid) {
      paymentVerified.value = true
      setTimeout(() => {
        router.push(`/orders/${orderResult.value.id}/success`)
      }, 2000)
    }
  } catch (error) {
    console.error('Error checking payment:', error)
  } finally {
    checkingPayment.value = false
  }
}

// Auto-check payment every 5 seconds when QR is shown
let paymentCheckInterval: ReturnType<typeof setInterval> | null = null

watch(paymentData, (newData) => {
  if (newData) {
    paymentCheckInterval = setInterval(checkPaymentStatus, 5000)
  } else if (paymentCheckInterval) {
    clearInterval(paymentCheckInterval)
  }
})

onUnmounted(() => {
  if (paymentCheckInterval) {
    clearInterval(paymentCheckInterval)
  }
})

// Copy to clipboard
const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    alert('Đã sao chép!')
  } catch {
    // fallback
    const el = document.createElement('textarea')
    el.value = text
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    alert('Đã sao chép!')
  }
}

// Validation
const isFormValid = computed(() => {
  return (
    form.customer_name.trim() &&
    form.customer_email.trim() &&
    form.customer_phone.trim() &&
    form.shipping_address.trim() &&
    form.shipping_city.trim()
  )
})

useSeoMeta({
  title: 'Thanh toán - PC Shop',
})
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Thanh toán</h1>

    <!-- Empty cart -->
    <div v-if="cartItems.length === 0" class="text-center py-12">
      <p class="text-6xl mb-4">🛒</p>
      <p class="text-xl text-gray-500 mb-6">Giỏ hàng trống</p>
      <UButton to="/products" size="lg">Mua sắm ngay</UButton>
    </div>

    <!-- QR Payment Modal -->
    <div 
      v-else-if="paymentData" 
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 text-center">
        <template v-if="!paymentVerified">
          <h2 class="text-2xl font-bold mb-2">Quét mã QR để thanh toán</h2>
          <p class="text-gray-600 mb-4">
            Đơn hàng #{{ orderResult?.order_number }}
          </p>

          <!-- QR Code -->
          <div class="bg-gray-100 rounded-xl p-4 mb-4 inline-block">
            <img :src="paymentData.qr_url" alt="QR Code" class="w-64 h-64">
          </div>

          <p class="text-2xl font-bold text-primary-600 mb-4">
            {{ new Intl.NumberFormat('vi-VN').format(paymentData.amount) }}₫
          </p>

          <!-- Bank info -->
          <div class="bg-blue-50 rounded-lg p-4 mb-4 text-left space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Ngân hàng:</span>
              <span class="font-semibold text-blue-900">{{ paymentData.bank_code }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Số tài khoản:</span>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-blue-900">{{ paymentData.bank_account }}</span>
                <button 
                  class="text-xs text-blue-600 hover:underline"
                  @click="copyToClipboard(paymentData.bank_account)"
                >Sao chép</button>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Chủ TK:</span>
              <span class="font-semibold text-blue-900">{{ paymentData.account_name }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Nội dung CK:</span>
              <div class="flex items-center gap-2">
                <span class="font-bold text-blue-900">{{ paymentData.transfer_content }}</span>
                <button 
                  class="text-xs text-blue-600 hover:underline"
                  @click="copyToClipboard(paymentData.transfer_content)"
                >Sao chép</button>
              </div>
            </div>
          </div>

          <p class="text-sm text-red-500 mb-4">
            ⚠️ Vui lòng ghi đúng nội dung chuyển khoản để thanh toán được xác nhận tự động
          </p>

          <div class="flex items-center justify-center gap-2 text-gray-500">
            <span class="animate-spin">⏳</span>
            <span>Đang chờ thanh toán...</span>
          </div>

          <button 
            class="mt-4 text-sm text-gray-500 hover:underline"
            @click="checkPaymentStatus"
            :disabled="checkingPayment"
          >
            Kiểm tra trạng thái thanh toán
          </button>
        </template>

        <template v-else>
          <div class="text-6xl mb-4">✅</div>
          <h2 class="text-2xl font-bold text-green-600 mb-2">
            Thanh toán thành công!
          </h2>
          <p class="text-gray-600">
            Đang chuyển hướng...
          </p>
        </template>
      </div>
    </div>

    <!-- Checkout Form -->
    <div v-else class="grid lg:grid-cols-3 gap-8">
      <!-- Form -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Customer Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h2 class="text-xl font-bold mb-4">Thông tin khách hàng</h2>
          
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Họ tên *</label>
              <input 
                v-model="form.customer_name"
                type="text"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Nguyễn Văn A"
              >
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Số điện thoại *</label>
              <input 
                v-model="form.customer_phone"
                type="tel"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="0912345678"
              >
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Email *</label>
              <input 
                v-model="form.customer_email"
                type="email"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="email@example.com"
              >
            </div>
          </div>
        </div>

        <!-- Shipping Address -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h2 class="text-xl font-bold mb-4">Địa chỉ giao hàng</h2>
          
          <div class="grid md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Tỉnh/Thành phố *</label>
              <select
                v-model="selectedProvinceCode"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white"
              >
                <option value="">-- Chọn Tỉnh/Thành phố --</option>
                <option v-for="p in provinces" :key="p.code" :value="p.code">{{ p.fullname }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Quận/Huyện</label>
              <input 
                v-model="form.shipping_district"
                type="text"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Quận 1"
              >
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Phường/Xã</label>
              <select
                v-model="form.shipping_ward"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white"
                :disabled="!selectedProvinceCode"
              >
                <option value="">-- Chọn Phường/Xã --</option>
                <option v-for="w in wards" :key="w.code" :value="w.fullname">{{ w.fullname }}</option>
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium mb-1">Địa chỉ chi tiết *</label>
              <input 
                v-model="form.shipping_address"
                type="text"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Số nhà, tên đường"
              >
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium mb-1">Ghi chú</label>
              <textarea 
                v-model="form.notes"
                rows="3"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Ghi chú cho người giao hàng..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Payment Method -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h2 class="text-xl font-bold mb-4">Phương thức thanh toán</h2>
          
          <div class="space-y-3">
            <label 
              class="flex items-center gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50"
              :class="{ 'border-primary-500 bg-primary-50': form.payment_method === 'sepay' }"
            >
              <input 
                v-model="form.payment_method" 
                type="radio" 
                value="sepay"
                class="w-5 h-5 text-primary-600"
              >
              <div class="flex-1">
                <p class="font-semibold">Chuyển khoản qua QR (Sepay)</p>
                <p class="text-sm text-gray-500">Quét mã QR để thanh toán nhanh chóng</p>
              </div>
              <span class="text-3xl">📱</span>
            </label>

            <label 
              class="flex items-center gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50"
              :class="{ 'border-primary-500 bg-primary-50': form.payment_method === 'cod' }"
            >
              <input 
                v-model="form.payment_method" 
                type="radio" 
                value="cod"
                class="w-5 h-5 text-primary-600"
              >
              <div class="flex-1">
                <p class="font-semibold">Thanh toán khi nhận hàng (COD)</p>
                <p class="text-sm text-gray-500">Thanh toán bằng tiền mặt khi giao hàng</p>
              </div>
              <span class="text-3xl">💵</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Order Summary -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm p-6 sticky top-4">
          <h2 class="text-xl font-bold mb-4">Đơn hàng của bạn</h2>
          
          <!-- Order Items -->
          <div class="space-y-3 mb-4 max-h-64 overflow-y-auto">
            <div 
              v-for="item in cartItems" 
              :key="item.id"
              class="flex gap-3"
            >
              <div class="w-16 h-16 bg-gray-100 rounded flex-shrink-0">
                <img 
                  v-if="item.product.images?.[0]" 
                  :src="item.product.images[0].url"
                  :alt="item.product.name"
                  class="w-full h-full object-cover rounded"
                >
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium line-clamp-2">{{ item.product.name }}</p>
                <p class="text-sm text-gray-500">SL: {{ item.quantity }}</p>
              </div>
              <p class="text-sm font-semibold whitespace-nowrap">
                {{ new Intl.NumberFormat('vi-VN').format((item.product.sale_price || item.product.price) * item.quantity) }}₫
              </p>
            </div>
          </div>

          <div class="border-t pt-4 space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Tạm tính</span>
              <span>{{ new Intl.NumberFormat('vi-VN').format(cartTotal) }}₫</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Phí vận chuyển</span>
              <span :class="shippingFee === 0 ? 'text-green-600' : ''">
                {{ shippingFee === 0 ? 'Miễn phí' : new Intl.NumberFormat('vi-VN').format(shippingFee) + '₫' }}
              </span>
            </div>
            <div class="flex justify-between text-lg font-bold pt-2 border-t">
              <span>Tổng cộng</span>
              <span class="text-primary-600">{{ new Intl.NumberFormat('vi-VN').format(orderTotal) }}₫</span>
            </div>
          </div>

          <UButton 
            class="mt-6" 
            size="lg" 
            block
            :disabled="!isFormValid || isSubmitting"
            :loading="isSubmitting"
            @click="placeOrder"
          >
            {{ form.payment_method === 'sepay' ? 'Thanh toán QR' : 'Đặt hàng' }}
          </UButton>

          <p class="text-xs text-gray-500 text-center mt-4">
            Bằng việc đặt hàng, bạn đồng ý với 
            <NuxtLink to="/terms" class="text-primary-600 hover:underline">Điều khoản</NuxtLink>
            và
            <NuxtLink to="/privacy" class="text-primary-600 hover:underline">Chính sách bảo mật</NuxtLink>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
