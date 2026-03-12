<script setup lang="ts">
const config = useRuntimeConfig()
const router = useRouter()

definePageMeta({
  middleware: 'guest',
})

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})

const register = async () => {
  isSubmitting.value = true
  errors.value = {}
  
  try {
    const response = await $fetch<{ user: any; token: string }>(
      `${config.public.apiBase}/auth/register`,
      {
        method: 'POST',
        body: form,
      }
    )
    
    // Store token
    const tokenCookie = useCookie('auth_token', { maxAge: 60 * 60 * 24 })
    tokenCookie.value = response.token
    
    // Store user
    const userCookie = useCookie('auth_user', { maxAge: 60 * 60 * 24 })
    userCookie.value = JSON.stringify(response.user)
    
    // Redirect to account
    router.push('/account')
  } catch (error: any) {
    if (error.data?.errors) {
      errors.value = error.data.errors
    } else {
      errors.value.general = error.data?.message || 'Đăng ký thất bại'
    }
  } finally {
    isSubmitting.value = false
  }
}

useSeoMeta({
  title: 'Đăng ký - PC Shop',
})
</script>

<template>
  <div class="min-h-[80vh] flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-2xl shadow-lg p-8">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold mb-2">Đăng ký</h1>
          <p class="text-gray-600">Tạo tài khoản PC Shop</p>
        </div>

        <form @submit.prevent="register" class="space-y-4">
          <!-- General Error -->
          <div 
            v-if="errors.general" 
            class="p-3 bg-red-50 text-red-600 rounded-lg text-sm"
          >
            {{ errors.general }}
          </div>

          <!-- Name -->
          <div>
            <label class="block text-sm font-medium mb-1">Họ tên</label>
            <input 
              v-model="form.name"
              type="text"
              class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': errors.name }"
              placeholder="Nguyễn Văn A"
              required
            >
            <p v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</p>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input 
              v-model="form.email"
              type="email"
              class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': errors.email }"
              placeholder="email@example.com"
              required
            >
            <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</p>
          </div>

          <!-- Phone -->
          <div>
            <label class="block text-sm font-medium mb-1">Số điện thoại</label>
            <input 
              v-model="form.phone"
              type="tel"
              class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': errors.phone }"
              placeholder="0912345678"
            >
            <p v-if="errors.phone" class="text-red-500 text-sm mt-1">{{ errors.phone }}</p>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium mb-1">Mật khẩu</label>
            <input 
              v-model="form.password"
              type="password"
              class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': errors.password }"
              placeholder="••••••••"
              required
            >
            <p v-if="errors.password" class="text-red-500 text-sm mt-1">{{ errors.password }}</p>
          </div>

          <!-- Confirm Password -->
          <div>
            <label class="block text-sm font-medium mb-1">Xác nhận mật khẩu</label>
            <input 
              v-model="form.password_confirmation"
              type="password"
              class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="••••••••"
              required
            >
          </div>

          <!-- Terms -->
          <p class="text-sm text-gray-500">
            Bằng việc đăng ký, bạn đồng ý với 
            <NuxtLink to="/terms" class="text-primary-600 hover:underline">Điều khoản</NuxtLink>
            và
            <NuxtLink to="/privacy" class="text-primary-600 hover:underline">Chính sách bảo mật</NuxtLink>
          </p>

          <!-- Submit -->
          <UButton 
            type="submit" 
            size="lg" 
            block
            :loading="isSubmitting"
            :disabled="isSubmitting"
          >
            Đăng ký
          </UButton>
        </form>

        <!-- Login Link -->
        <p class="text-center text-gray-600 mt-6">
          Đã có tài khoản?
          <NuxtLink to="/auth/login" class="text-primary-600 font-medium hover:underline">
            Đăng nhập
          </NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>
