<script setup lang="ts">
useSeoMeta({
  title: 'Liên hệ - PC Shop',
  description: 'Liên hệ PC Shop - Hỗ trợ mua hàng, tư vấn kỹ thuật, bảo hành.',
})
</script>

<template>
  <div class="container mx-auto px-4 py-12 max-w-4xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Liên hệ với chúng tôi</h1>

    <div class="grid md:grid-cols-2 gap-8">
      <!-- Contact Info -->
      <div class="space-y-6">
        <div>
          <h2 class="text-xl font-semibold text-gray-900 mb-4">Thông tin liên hệ</h2>
          <div class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
              <div>
                <p class="font-medium text-gray-900">Địa chỉ</p>
                <p class="text-gray-600">123 Nguyễn Văn Linh, Quận 7, TP.HCM</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              </div>
              <div>
                <p class="font-medium text-gray-900">Hotline</p>
                <p class="text-gray-600">1900 xxxx (8:00 – 21:00)</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              </div>
              <div>
                <p class="font-medium text-gray-900">Email</p>
                <p class="text-gray-600">info@pcshop.vn</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Form -->
      <div class="bg-gray-50 rounded-xl p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Gửi tin nhắn</h2>
        <form class="space-y-4" @submit.prevent>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Họ tên</label>
            <input type="text" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Nhập họ tên">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Nhập email">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
            <input type="tel" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Nhập số điện thoại">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nội dung</label>
            <textarea rows="4" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Nhập nội dung cần hỗ trợ"></textarea>
          </div>
          <UButton type="submit" size="lg" class="w-full justify-center">Gửi tin nhắn</UButton>
        </form>
      </div>
    </div>
  </div>
</template>
