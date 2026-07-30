<script setup lang="ts">
const config = useRuntimeConfig()
const route = useRoute()
const { siteName, formatMoney } = useSettings()
const orderId = route.params.id as string
const order = ref<any>(null)
const loading = ref(true)
const accessDenied = ref(false)
const orderAccessToken = ref('')

const orderHeaders = (): Record<string, string> => orderAccessToken.value
  ? { 'X-Order-Access-Token': orderAccessToken.value }
  : {}

const refresh = async () => {
  try {
    order.value = await $fetch<any>(`${config.public.apiBase}/orders/${orderId}`, {
      headers: orderHeaders(),
    })
    accessDenied.value = false
  } catch {
    order.value = null
    accessDenied.value = true
  } finally {
    loading.value = false
  }
}

const statusLabels: Record<string, string> = {
  pending: 'Đang ghi nhận đơn hàng',
  sending: 'Đang kiểm tra tồn kho',
  retrying: 'Đang chờ kết nối hệ thống kho',
  synced: 'Đơn hàng đã được xác nhận',
  rejected: 'Đơn hàng không thể xác nhận',
  failed: 'Đồng bộ gặp lỗi, vui lòng liên hệ',
  cancel_pending: 'Đang gửi yêu cầu hủy',
  cancelled: 'Đơn hàng đã được hủy',
  not_required: 'Đơn hàng đã được ghi nhận',
}

const integrationMessage = computed(() => statusLabels[order.value?.kiot_sync_status] || 'Đang ghi nhận đơn hàng')
const isWaiting = computed(() => ['pending', 'sending', 'retrying', 'cancel_pending'].includes(order.value?.kiot_sync_status))

let interval: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  orderAccessToken.value = sessionStorage.getItem(`pc-order-access-token:${orderId}`) || ''
  refresh()
  interval = setInterval(async () => {
    if (isWaiting.value) await refresh()
  }, 5000)
})
onUnmounted(() => { if (interval) clearInterval(interval) })

const cancelling = ref(false)
const cancelOrder = async () => {
  if (!order.value?.can_cancel || cancelling.value) return
  cancelling.value = true
  try {
    const response = await $fetch<any>(`${config.public.apiBase}/orders/${orderId}/cancel`, {
      method: 'POST',
      headers: orderHeaders(),
      body: { reason: 'Khách hàng yêu cầu hủy' },
    })
    order.value = response.order
  } finally {
    cancelling.value = false
  }
}

useSeoMeta({ title: () => `Trạng thái đơn hàng - ${siteName.value}` })
</script>

<template>
  <div class="container mx-auto px-4 py-12">
    <div class="mx-auto max-w-2xl">
      <div v-if="loading" class="rounded-xl bg-white p-8 text-center shadow-sm"><p class="text-gray-600">Đang tải đơn hàng...</p></div>
      <div v-else-if="order" class="space-y-6">
        <div class="rounded-xl bg-white p-6 text-center shadow-sm">
          <h1 class="text-3xl font-bold text-gray-900">Đơn hàng {{ order.order_number }}</h1>
          <p class="mt-3 text-lg" :class="order.kiot_sync_status === 'synced' ? 'text-green-700' : order.kiot_sync_status === 'rejected' ? 'text-red-700' : 'text-amber-700'">
            {{ integrationMessage }}
          </p>
          <p v-if="order.kiot_order_code" class="mt-1 text-sm text-gray-500">Mã KIOT: {{ order.kiot_order_code }}</p>
          <p v-if="order.kiot_sync_error_code" class="mt-2 text-sm text-red-600">Mã lỗi: {{ order.kiot_sync_error_code }}</p>
        </div>

        <div v-if="order.can_pay && order.payment" class="rounded-xl bg-white p-6 text-center shadow-sm">
          <h2 class="text-xl font-bold">Thanh toán bằng chuyển khoản</h2>
          <p class="mt-2 text-sm text-gray-600">KIOT đã xác nhận sản phẩm và tồn kho cho đơn này.</p>
          <img :src="order.payment.qr_url" alt="Mã QR thanh toán" class="mx-auto mt-4 h-64 w-64" />
          <p class="mt-3 text-2xl font-bold text-primary-600">{{ formatMoney(order.payment.amount) }}</p>
          <p class="mt-2 text-sm text-gray-700">Nội dung chuyển khoản: <strong>{{ order.payment.transfer_content }}</strong></p>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
          <div class="grid gap-6 md:grid-cols-2">
            <div><p class="text-sm text-gray-500">Người nhận</p><p class="font-medium">{{ order.shipping_name }}</p><p class="text-gray-600">{{ order.shipping_phone }}</p><p class="text-gray-600">{{ order.customer_email }}</p></div>
            <div><p class="text-sm text-gray-500">Địa chỉ giao hàng</p><p class="text-gray-600">{{ order.shipping_address }}, {{ order.shipping_ward }}, {{ order.shipping_district }}, {{ order.shipping_city }}</p></div>
          </div>
          <div class="mt-6 border-t pt-6">
            <div v-for="item in order.items" :key="item.id" class="flex justify-between py-2">
              <div><p class="font-medium">{{ item.product_name }}</p><p class="text-sm text-gray-500">SKU: {{ item.sku }} · SL: {{ item.quantity }}</p></div>
              <p class="font-medium">{{ formatMoney(item.total) }}</p>
            </div>
          </div>
          <div class="mt-4 flex justify-between border-t pt-4 text-lg font-bold"><span>Tổng cộng</span><span class="text-primary-600">{{ formatMoney(order.total) }}</span></div>
        </div>

        <div class="flex justify-center gap-3">
          <UButton v-if="order.can_cancel" color="error" variant="soft" :loading="cancelling" @click="cancelOrder">Hủy đơn hàng</UButton>
          <UButton to="/" size="lg" variant="outline">Tiếp tục mua sắm</UButton>
        </div>
      </div>
      <div v-else class="rounded-xl bg-white p-8 text-center shadow-sm"><p class="text-gray-600">{{ accessDenied ? 'Bạn không có quyền xem đơn hàng này.' : 'Không tìm thấy đơn hàng.' }}</p></div>
    </div>
  </div>
</template>
