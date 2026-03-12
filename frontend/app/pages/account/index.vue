<script setup lang="ts">
const config = useRuntimeConfig()
const router = useRouter()

definePageMeta({
  middleware: 'auth',
})

// Get user from cookie
const userCookie = useCookie('auth_user')
const user = computed(() => {
  try {
    return JSON.parse(userCookie.value || '{}')
  } catch {
    return {}
  }
})

// Fetch recent orders
const token = useCookie('auth_token')
const { data: ordersData } = await useFetch<{ orders: any[] }>(
  `${config.public.apiBase}/orders`,
  {
    headers: {
      Authorization: `Bearer ${token.value}`,
    },
    default: () => ({ orders: [] }),
  }
)

const recentOrders = computed(() => ordersData.value?.orders?.slice(0, 5) || [])

// Logout
const logout = async () => {
  try {
    await $fetch(`${config.public.apiBase}/auth/logout`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token.value}`,
      },
    })
  } catch (error) {
    // Ignore logout errors
  }
  
  // Clear cookies
  const tokenCookie = useCookie('auth_token')
  const userCookie = useCookie('auth_user')
  tokenCookie.value = null
  userCookie.value = null
  
  router.push('/')
}

const getOrderStatusBadge = (status: string): { bg: string; text: string; label: string } => {
  const badges: Record<string, { bg: string; text: string; label: string }> = {
    pending: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Chờ xử lý' },
    confirmed: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Đã xác nhận' },
    processing: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Đang xử lý' },
    shipping: { bg: 'bg-indigo-100', text: 'text-indigo-700', label: 'Đang giao' },
    delivered: { bg: 'bg-green-100', text: 'text-green-700', label: 'Đã giao' },
    cancelled: { bg: 'bg-red-100', text: 'text-red-700', label: 'Đã hủy' },
  }
  return badges[status] ?? { bg: 'bg-gray-100', text: 'text-gray-700', label: 'Không rõ' }
}

useSeoMeta({
  title: 'Tài khoản - PC Shop',
})
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <div class="grid lg:grid-cols-4 gap-8">
      <!-- Sidebar -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm p-6">
          <!-- User Info -->
          <div class="text-center mb-6">
            <div class="w-20 h-20 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center text-3xl font-bold mx-auto mb-3">
              {{ user.name?.[0]?.toUpperCase() || '?' }}
            </div>
            <h2 class="font-bold text-lg">{{ user.name }}</h2>
            <p class="text-gray-500 text-sm">{{ user.email }}</p>
          </div>

          <!-- Nav -->
          <nav class="space-y-1">
            <NuxtLink 
              to="/account" 
              class="flex items-center gap-3 px-4 py-2 rounded-lg font-medium bg-primary-50 text-primary-700"
            >
              📊 Tổng quan
            </NuxtLink>
            <NuxtLink 
              to="/account/orders" 
              class="flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              📦 Đơn hàng
            </NuxtLink>
            <NuxtLink 
              to="/account/builds" 
              class="flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              🖥️ Cấu hình đã lưu
            </NuxtLink>
            <NuxtLink 
              to="/account/profile" 
              class="flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              👤 Thông tin cá nhân
            </NuxtLink>
            <NuxtLink 
              to="/account/password" 
              class="flex items-center gap-3 px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              🔒 Đổi mật khẩu
            </NuxtLink>
            <button 
              class="w-full flex items-center gap-3 px-4 py-2 rounded-lg text-red-600 hover:bg-red-50"
              @click="logout"
            >
              🚪 Đăng xuất
            </button>
          </nav>
        </div>
      </div>

      <!-- Main Content -->
      <div class="lg:col-span-3 space-y-6">
        <!-- Welcome -->
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl p-6 text-white">
          <h1 class="text-2xl font-bold mb-2">Xin chào, {{ user.name }}!</h1>
          <p class="opacity-90">Quản lý tài khoản và theo dõi đơn hàng của bạn</p>
        </div>

        <!-- Quick Stats -->
        <div class="grid md:grid-cols-3 gap-4">
          <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-3xl font-bold text-primary-600">{{ recentOrders.length }}</p>
            <p class="text-gray-500">Đơn hàng</p>
          </div>
          <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-3xl font-bold text-green-600">0</p>
            <p class="text-gray-500">Cấu hình đã lưu</p>
          </div>
          <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-3xl font-bold text-yellow-600">0</p>
            <p class="text-gray-500">Điểm thưởng</p>
          </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Đơn hàng gần đây</h2>
            <NuxtLink to="/account/orders" class="text-primary-600 hover:underline">
              Xem tất cả →
            </NuxtLink>
          </div>

          <div v-if="recentOrders.length === 0" class="text-center py-8 text-gray-500">
            Bạn chưa có đơn hàng nào
          </div>

          <div v-else class="space-y-3">
            <div 
              v-for="order in recentOrders" 
              :key="order.id"
              class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
            >
              <div>
                <p class="font-semibold">{{ order.order_number }}</p>
                <p class="text-sm text-gray-500">
                  {{ new Date(order.created_at).toLocaleDateString('vi-VN') }}
                </p>
              </div>
              <div class="text-right">
                <p class="font-bold">
                  {{ new Intl.NumberFormat('vi-VN').format(order.total_amount) }}₫
                </p>
                <span 
                  :class="[
                    'text-xs px-2 py-1 rounded-full',
                    getOrderStatusBadge(order.status).bg,
                    getOrderStatusBadge(order.status).text
                  ]"
                >
                  {{ getOrderStatusBadge(order.status).label }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
