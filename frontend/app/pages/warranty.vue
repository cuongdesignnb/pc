<script setup lang="ts">
useSeoMeta({
  title: 'Chính sách bảo hành - PC Shop',
  description: 'Chính sách bảo hành sản phẩm tại PC Shop - Cam kết bảo hành chính hãng, đổi trả minh bạch.',
})
</script>

<template>
  <div class="container mx-auto px-4 py-12 max-w-4xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Chính sách bảo hành</h1>

    <div class="prose prose-lg max-w-none text-gray-700 space-y-6">
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-8">
        <p class="text-blue-800 font-medium">
          PC Shop cam kết bảo hành chính hãng 100% cho tất cả sản phẩm. Mọi sản phẩm đều có phiếu bảo hành
          và được hỗ trợ trực tiếp bởi đội ngũ kỹ thuật viên chuyên nghiệp.
        </p>
      </div>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">1. Điều kiện bảo hành</h2>
      <ul class="list-disc pl-6 space-y-2">
        <li>Sản phẩm còn trong thời hạn bảo hành (theo phiếu bảo hành)</li>
        <li>Tem bảo hành, serial number còn nguyên vẹn</li>
        <li>Sản phẩm bị lỗi do nhà sản xuất (không do tác động bên ngoài)</li>
        <li>Có hóa đơn mua hàng hoặc phiếu bảo hành từ PC Shop</li>
      </ul>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">2. Thời gian bảo hành</h2>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">Loại sản phẩm</th>
              <th class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">Thời gian</th>
            </tr>
          </thead>
          <tbody class="text-sm">
            <tr><td class="border border-gray-200 px-4 py-2">PC nguyên bộ</td><td class="border border-gray-200 px-4 py-2">36 tháng</td></tr>
            <tr class="bg-gray-50"><td class="border border-gray-200 px-4 py-2">Laptop</td><td class="border border-gray-200 px-4 py-2">24 tháng</td></tr>
            <tr><td class="border border-gray-200 px-4 py-2">Linh kiện (CPU, VGA, RAM, SSD...)</td><td class="border border-gray-200 px-4 py-2">36 tháng</td></tr>
            <tr class="bg-gray-50"><td class="border border-gray-200 px-4 py-2">Màn hình</td><td class="border border-gray-200 px-4 py-2">24 tháng</td></tr>
            <tr><td class="border border-gray-200 px-4 py-2">Phụ kiện (chuột, bàn phím, tai nghe...)</td><td class="border border-gray-200 px-4 py-2">12 tháng</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">3. Trường hợp không bảo hành</h2>
      <ul class="list-disc pl-6 space-y-2">
        <li>Sản phẩm hết hạn bảo hành</li>
        <li>Hư hỏng do va đập, rơi vỡ, ngấm nước</li>
        <li>Sản phẩm bị can thiệp sửa chữa bởi bên thứ ba</li>
        <li>Tem bảo hành, serial bị rách, mờ hoặc không còn nguyên vẹn</li>
        <li>Hư hỏng do thiên tai, sét đánh, nguồn điện không ổn định</li>
      </ul>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">4. Quy trình bảo hành</h2>
      <div class="grid sm:grid-cols-3 gap-4">
        <div class="bg-gray-50 rounded-xl p-5 text-center">
          <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center mx-auto mb-3 font-bold">1</div>
          <p class="font-medium text-gray-900 mb-1">Liên hệ</p>
          <p class="text-sm text-gray-500">Hotline 1900 xxxx hoặc mang sản phẩm đến cửa hàng</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-5 text-center">
          <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center mx-auto mb-3 font-bold">2</div>
          <p class="font-medium text-gray-900 mb-1">Kiểm tra</p>
          <p class="text-sm text-gray-500">Kỹ thuật viên kiểm tra và xác nhận lỗi (1-3 ngày)</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-5 text-center">
          <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center mx-auto mb-3 font-bold">3</div>
          <p class="font-medium text-gray-900 mb-1">Xử lý</p>
          <p class="text-sm text-gray-500">Sửa chữa / đổi mới và trả sản phẩm (3-7 ngày)</p>
        </div>
      </div>
    </div>
  </div>
</template>
