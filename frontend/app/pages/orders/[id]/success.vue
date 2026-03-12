<script setup lang="ts">
const config = useRuntimeConfig()
const route = useRoute()

const orderId = route.params.id as string

// Fetch order details
const { data: order } = await useFetch<any>(
  `${config.public.apiBase}/orders/${orderId}`,
  { default: () => null }
)

useSeoMeta({
  title: 'Đặt hàng thành công - PC Shop',
})
</script>

<template>
  <div class="container mx-auto px-4 py-12">
    <div class="max-w-2xl mx-auto text-center">
      <!-- Success Icon -->
      <div class="text-8xl mb-6">🎉</div>
      
      <h1 class="text-4xl font-bold text-green-600 mb-4">
        Đặt hàng thành công!
      </h1>
      
      <p class="text-xl text-gray-600 mb-8">
        Cảm ơn bạn đã tin tưởng PC Shop
      </p>

      <template v-if="order">
        <!-- Order Info -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8 text-left">
          <div class="flex justify-between items-center mb-4 pb-4 border-b">
            <div>
              <p class="text-sm text-gray-500">Mã đơn hàng</p>
              <p class="text-xl font-bold">{{ order.order_number }}</p>
            </div>
            <span 
              :class="[
                'px-3 py-1 rounded-full text-sm font-medium',
                order.payment_status === 'paid' 
                  ? 'bg-green-100 text-green-700' 
                  : 'bg-yellow-100 text-yellow-700'
              ]"
            >
              {{ order.payment_status === 'paid' ? 'Đã thanh toán' : 'Chờ thanh toán' }}
            </span>
          </div>

          <div class="grid md:grid-cols-2 gap-6">
            <div>
              <p class="text-sm text-gray-500 mb-1">Người nhận</p>
              <p class="font-medium">{{ order.shipping_name }}</p>
              <p class="text-gray-600">{{ order.shipping_phone }}</p>
              <p class="text-gray-600">{{ order.customer_email }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-500 mb-1">Địa chỉ giao hàng</p>
              <p class="text-gray-600">
                {{ order.shipping_address }}<br>
                {{ order.shipping_ward && order.shipping_ward + ', ' }}
                {{ order.shipping_district && order.shipping_district + ', ' }}
                {{ order.shipping_city }}
              </p>
            </div>
          </div>

          <!-- Order Items -->
          <div class="mt-6 pt-6 border-t">
            <p class="text-sm text-gray-500 mb-3">Sản phẩm đã đặt</p>
            <div class="space-y-3">
              <div 
                v-for="item in order.items" 
                :key="item.id"
                class="flex justify-between"
              >
                <div>
                  <p class="font-medium">{{ item.product?.name }}</p>
                  <p class="text-sm text-gray-500">SL: {{ item.quantity }}</p>
                </div>
                <p class="font-medium">
                  {{ new Intl.NumberFormat('vi-VN').format(item.price * item.quantity) }}₫
                </p>
              </div>
            </div>
          </div>

          <!-- Total -->
          <div class="mt-6 pt-6 border-t flex justify-between text-lg font-bold">
            <span>Tổng cộng</span>
            <span class="text-primary-600">
              {{ new Intl.NumberFormat('vi-VN').format(order.total) }}₫
            </span>
          </div>
        </div>

        <!-- Next Steps -->
        <div class="bg-blue-50 rounded-xl p-6 mb-8 text-left">
          <h3 class="font-bold mb-2">📧 Tiếp theo</h3>
          <ul class="space-y-2 text-gray-700">
            <li>• Email xác nhận đơn hàng đã được gửi đến {{ order.customer_email }}</li>
            <li>• Chúng tôi sẽ liên hệ qua số {{ order.shipping_phone }} để xác nhận</li>
            <li>• Đơn hàng sẽ được giao trong 2-5 ngày làm việc</li>
          </ul>
        </div>
      </template>

      <!-- Actions -->
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <UButton to="/products" size="lg" variant="outline">
          Tiếp tục mua sắm
        </UButton>
        <UButton v-if="order" :to="`/account/orders/${orderId}`" size="lg">
          Theo dõi đơn hàng
        </UButton>
      </div>
    </div>
  </div>
</template>
