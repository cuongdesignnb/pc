<script setup lang="ts">
const config = useRuntimeConfig()
const router = useRouter()

definePageMeta({
  middleware: 'guest',
})

const form = reactive({
  email: '',
  password: '',
  remember: false,
})

const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})

const login = async () => {
  isSubmitting.value = true
  errors.value = {}
  
  try {
    const response = await $fetch<{ user: any; token: string }>(
      `${config.public.apiBase}/auth/login`,
      {
        method: 'POST',
        body: form,
      }
    )
    
    // Store token
    const tokenCookie = useCookie('auth_token', { 
      maxAge: form.remember ? 60 * 60 * 24 * 30 : 60 * 60 * 24 // 30 days or 1 day
    })
    tokenCookie.value = response.token
    
    // Store user
    const userCookie = useCookie('auth_user', {
      maxAge: form.remember ? 60 * 60 * 24 * 30 : 60 * 60 * 24
    })
    userCookie.value = JSON.stringify(response.user)
    
    // Redirect to intended page or home
    const redirect = router.currentRoute.value.query.redirect as string
    router.push(redirect || '/account')
  } catch (error: any) {
    if (error.data?.errors) {
      errors.value = error.data.errors
    } else {
      errors.value.general = error.data?.message || 'Đăng nhập thất bại'
    }
  } finally {
    isSubmitting.value = false
  }
}

useSeoMeta({
  title: 'Đăng nhập - PC Shop',
})
</script>

<template>
  <div class="min-h-[80vh] flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-2xl shadow-lg p-8">
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold mb-2">Đăng nhập</h1>
          <p class="text-gray-600">Chào mừng trở lại PC Shop</p>
        </div>

        <form @submit.prevent="login" class="space-y-4">
          <!-- General Error -->
          <div 
            v-if="errors.general" 
            class="p-3 bg-red-50 text-red-600 rounded-lg text-sm"
          >
            {{ errors.general }}
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

          <!-- Remember & Forgot -->
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2">
              <input 
                v-model="form.remember" 
                type="checkbox"
                class="w-4 h-4 text-primary-600 rounded"
              >
              <span class="text-sm text-gray-600">Ghi nhớ đăng nhập</span>
            </label>
            <NuxtLink to="/auth/forgot-password" class="text-sm text-primary-600 hover:underline">
              Quên mật khẩu?
            </NuxtLink>
          </div>

          <!-- Submit -->
          <UButton 
            type="submit" 
            size="lg" 
            block
            :loading="isSubmitting"
            :disabled="isSubmitting"
          >
            Đăng nhập
          </UButton>
        </form>

        <!-- Register Link -->
        <p class="text-center text-gray-600 mt-6">
          Chưa có tài khoản?
          <NuxtLink to="/auth/register" class="text-primary-600 font-medium hover:underline">
            Đăng ký ngay
          </NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>
