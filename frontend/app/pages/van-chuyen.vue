<script setup lang="ts">
const {
  siteName,
  siteHotline,
  shippingFreeThreshold,
  shippingDefaultFee,
  shippingExpressFee,
  formatMoney,
} = useSettings()

useSeoMeta({
  title: () => `Chính sách vận chuyển - ${siteName.value}`,
  description: () => `Chính sách vận chuyển và giao hàng tại ${siteName.value}.`,
})
</script>

<template>
  <div class="container mx-auto px-4 py-12 max-w-4xl">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Chính sách vận chuyển</h1>

    <div class="prose prose-lg max-w-none text-gray-700 space-y-6">
      <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-8">
        <p class="text-green-800 font-medium">
          {{ siteName }} cung cấp dịch vụ giao hàng toàn quốc với nhiều phương thức linh hoạt.
          Miễn phí giao hàng cho đơn hàng từ {{ formatMoney(shippingFreeThreshold) }}.
        </p>
      </div>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">1. Phí vận chuyển</h2>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">Khu vực</th>
              <th class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">Phí vận chuyển</th>
              <th class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">Thời gian</th>
            </tr>
          </thead>
          <tbody class="text-sm">
            <tr><td class="border border-gray-200 px-4 py-2">Đơn đủ điều kiện miễn phí</td><td class="border border-gray-200 px-4 py-2 text-green-600 font-medium">Miễn phí (đơn ≥ {{ formatMoney(shippingFreeThreshold) }})</td><td class="border border-gray-200 px-4 py-2">Theo khu vực</td></tr>
            <tr class="bg-gray-50"><td class="border border-gray-200 px-4 py-2">Giao hàng tiêu chuẩn</td><td class="border border-gray-200 px-4 py-2">{{ formatMoney(shippingDefaultFee) }}</td><td class="border border-gray-200 px-4 py-2">1 – 5 ngày</td></tr>
            <tr><td class="border border-gray-200 px-4 py-2">Giao hàng nhanh</td><td class="border border-gray-200 px-4 py-2">{{ formatMoney(shippingExpressFee) }}</td><td class="border border-gray-200 px-4 py-2">Theo khu vực</td></tr>
          </tbody>
        </table>
      </div>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">2. Phương thức giao hàng</h2>
      <ul class="list-disc pl-6 space-y-2">
        <li><strong>Giao hàng nhanh (Express):</strong> Giao trong 2-4 giờ nội thành HCM & HN</li>
        <li><strong>Giao hàng tiêu chuẩn:</strong> Qua đơn vị vận chuyển GHN, GHTK, Viettel Post</li>
        <li><strong>Nhận tại cửa hàng:</strong> Đặt online và đến nhận trực tiếp</li>
      </ul>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">3. Lưu ý khi nhận hàng</h2>
      <ul class="list-disc pl-6 space-y-2">
        <li>Kiểm tra tình trạng bên ngoài kiện hàng trước khi nhận</li>
        <li>Quay video khi mở hộp để đảm bảo quyền lợi</li>
        <li>Liên hệ ngay Hotline {{ siteHotline }} nếu phát hiện sai / thiếu sản phẩm</li>
        <li>Thời gian khiếu nại: trong vòng 24 giờ kể từ khi nhận hàng</li>
      </ul>

      <h2 class="text-2xl font-semibold text-gray-900 mt-8 mb-4">4. Chính sách đổi trả</h2>
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="bg-gray-50 rounded-xl p-5">
          <h3 class="font-semibold text-gray-900 mb-2">Đổi hàng trong 7 ngày</h3>
          <p class="text-sm text-gray-600">Sản phẩm còn nguyên seal, chưa kích hoạt bảo hành. Không áp dụng cho phần mềm, phụ kiện đã bóc seal.</p>
        </div>
        <div class="bg-gray-50 rounded-xl p-5">
          <h3 class="font-semibold text-gray-900 mb-2">Hoàn tiền trong 3 ngày</h3>
          <p class="text-sm text-gray-600">Áp dụng khi sản phẩm lỗi từ nhà sản xuất và không có hàng thay thế. Hoàn tiền qua chuyển khoản trong 1-3 ngày làm việc.</p>
        </div>
      </div>
    </div>
  </div>
</template>
