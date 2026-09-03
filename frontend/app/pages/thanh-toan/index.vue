<script setup lang="ts">
import type { BuyNowItem, ProductDetail, ProductDetailResponse } from '~/types/product-detail'

const config = useRuntimeConfig();
const router = useRouter();
const route = useRoute();
const { getHeaders: getCartHeaders } = useCartSession();
const { read: readBuyNow, clear: clearBuyNow } = useBuyNow();
const {
  siteName,
  paymentCodEnabled,
  shippingFreeThreshold,
  shippingDefaultFee,
  formatMoney,
} = useSettings();
const checkoutIdempotencyKey = ref("");
const orderAccessToken = ref("");
const checkoutMode = ref<'cart' | 'buy_now'>('cart');
const buyNowItem = ref<BuyNowItem | null>(null);
const buyNowProduct = ref<ProductDetail | null>(null);
const buyNowLoading = ref(false);

interface CartResponseItem {
  id: number;
  product_id: number;
  variant_id: number | null;
  quantity: number;
  price: number | string;
  variant?: { id: number; name: string } | null;
  product: { name: string; images?: { url: string | null }[] };
}

interface CheckoutLine {
  product_id: number;
  variant_id: number | null;
  quantity: number;
  name: string;
  image: string | null;
  variant_name: string | null;
  unit_price: number;
}

// Form data
const form = reactive({
  customer_name: "",
  customer_email: "",
  customer_phone: "",
  shipping_address: "",
  shipping_city: "",
  shipping_district: "",
  shipping_ward: "",
  notes: "",
  payment_method: "sepay",
});

// Fetch cart
const { data: cartData } = await useFetch<{ items: CartResponseItem[]; total: number }>(
  `${config.public.apiBase}/cart`,
  { default: () => ({ items: [], total: 0 }), headers: getCartHeaders() },
);

const cartItems = computed(() => cartData.value?.items || []);
const checkoutItems = computed<CheckoutLine[]>(() => {
  if (checkoutMode.value === 'buy_now' && buyNowItem.value && buyNowProduct.value) {
    const variant = buyNowItem.value.variant_id
      ? buyNowProduct.value.variants.find(item => item.id === buyNowItem.value?.variant_id) || null
      : null;
    return [{
      product_id: buyNowProduct.value.id,
      variant_id: variant?.id || null,
      quantity: buyNowItem.value.quantity,
      name: buyNowProduct.value.name,
      image: buyNowProduct.value.images[0]?.url || null,
      variant_name: variant?.name || null,
      unit_price: variant?.pricing.display_price || buyNowProduct.value.pricing.display_price,
    }];
  }
  return cartItems.value.map(item => ({
    product_id: item.product_id,
    variant_id: item.variant_id || null,
    quantity: item.quantity,
    name: item.product.name,
    image: item.product.images?.[0]?.url || null,
    variant_name: item.variant?.name || null,
    unit_price: Number(item.price),
  }));
});
const cartTotal = computed(() => checkoutItems.value.reduce((total, item) => total + item.unit_price * item.quantity, 0));

// Location data
const selectedProvinceCode = ref("");
const provinces = ref<any[]>([]);
const wards = ref<any[]>([]);

// Fetch provinces on mount (SSR disabled for checkout)
onMounted(async () => {
  const storageKey = "pc-checkout-idempotency-key";
  checkoutIdempotencyKey.value = sessionStorage.getItem(storageKey) || crypto.randomUUID();
  sessionStorage.setItem(storageKey, checkoutIdempotencyKey.value);
  const accessTokenStorageKey = "pc-checkout-order-access-token";
  orderAccessToken.value = sessionStorage.getItem(accessTokenStorageKey) || crypto.randomUUID();
  sessionStorage.setItem(accessTokenStorageKey, orderAccessToken.value);
  if (route.query.mode === 'buy-now') {
    const item = readBuyNow();
    if (item) {
      checkoutMode.value = 'buy_now';
      buyNowItem.value = item;
      buyNowLoading.value = true;
      try {
        const response = await $fetch<ProductDetailResponse>(`${config.public.apiBase}/products/${encodeURIComponent(item.product_slug)}`);
        if (item.variant_id && !response.product.variants.some(variant => variant.id === item.variant_id)) {
          throw new Error('Biến thể không còn khả dụng');
        }
        buyNowProduct.value = response.product;
      } catch {
        clearBuyNow();
        buyNowItem.value = null;
        checkoutMode.value = 'cart';
        toast.add({ title: 'Không thể tải sản phẩm mua ngay', description: 'Vui lòng quay lại trang sản phẩm và thử lại.', color: 'error' });
      } finally {
        buyNowLoading.value = false;
      }
    }
  }
  try {
    const data = await $fetch<any[]>(
      `${config.public.apiBase}/locations/provinces`,
    );
    provinces.value = data || [];
  } catch (e) {
    console.error("Failed to load provinces:", e);
  }
});

// Fetch wards when province changes
watch(selectedProvinceCode, async (code) => {
  form.shipping_city =
    provinces.value.find((p: any) => p.code === code)?.fullname || "";
  form.shipping_ward = "";
  wards.value = [];
  if (code) {
    const data = await $fetch<any[]>(
      `${config.public.apiBase}/locations/provinces/${code}/wards`,
    );
    wards.value = data || [];
  }
});

// Shipping fee
const shippingFee = computed(() => {
  return shippingFreeThreshold.value > 0 && cartTotal.value >= shippingFreeThreshold.value
    ? 0
    : shippingDefaultFee.value;
});

const orderTotal = computed(() => cartTotal.value + shippingFee.value);

// Place order
const isSubmitting = ref(false);
const orderResult = ref<any>(null);
const paymentData = ref<any>(null);
const toast = useToast();

const placeOrder = async () => {
  isSubmitting.value = true;
  try {
    const response = await $fetch<any>(`${config.public.apiBase}/orders`, {
      method: "POST",
      headers: getCartHeaders(),
      body: {
        ...form,
        checkout_mode: checkoutMode.value,
        checkout_idempotency_key: checkoutIdempotencyKey.value,
        order_access_token: orderAccessToken.value,
        items: checkoutItems.value.map((item) => ({
          product_id: item.product_id,
          variant_id: item.variant_id,
          quantity: item.quantity,
        })),
      },
    });

    orderResult.value = response.order;
    sessionStorage.setItem(`pc-order-access-token:${response.order.id}`, orderAccessToken.value);
    sessionStorage.removeItem("pc-checkout-idempotency-key");
    sessionStorage.removeItem("pc-checkout-order-access-token");
    if (checkoutMode.value === 'buy_now') clearBuyNow();
    toast.add({
      title: "Đặt hàng thành công!",
      description: `Mã đơn hàng: #${response.order.id}`,
      icon: "i-heroicons-check-circle",
      color: "success",
    });

    // If SePay payment, show QR code for bank transfer
    if (form.payment_method === "sepay" && response.payment && response.order.can_pay) {
      paymentData.value = response.payment;
    } else {
      // COD or an order still waiting for KIOT confirmation.
      router.push(`/don-hang/${response.order.id}/thanh-cong`);
    }
  } catch (error: any) {
    if (error.data?.integration_status === "rejected") {
      checkoutIdempotencyKey.value = crypto.randomUUID();
      orderAccessToken.value = crypto.randomUUID();
      sessionStorage.setItem("pc-checkout-idempotency-key", checkoutIdempotencyKey.value);
      sessionStorage.setItem("pc-checkout-order-access-token", orderAccessToken.value);
    }
    toast.add({
      title: "Lỗi đặt hàng",
      description: error.data?.message || "Có lỗi xảy ra. Vui lòng thử lại.",
      icon: "i-heroicons-exclamation-triangle",
      color: "error",
    });
  } finally {
    isSubmitting.value = false;
  }
};

// Check payment status (polling for SePay bank transfer)
const paymentVerified = ref(false);
const checkingPayment = ref(false);

const checkPaymentStatus = async () => {
  if (!orderResult.value?.id) return;

  checkingPayment.value = true;
  try {
    const response = await $fetch<any>(
      `${config.public.apiBase}/orders/${orderResult.value.id}/check-payment`,
      { headers: { "X-Order-Access-Token": orderAccessToken.value } },
    );

    if (response.paid) {
      paymentVerified.value = true;
      setTimeout(() => {
        router.push(`/don-hang/${orderResult.value.id}/thanh-cong`);
      }, 2000);
    }
  } catch (error) {
    console.error("Error checking payment:", error);
  } finally {
    checkingPayment.value = false;
  }
};

// Auto-check payment every 5 seconds when QR is shown
let paymentCheckInterval: ReturnType<typeof setInterval> | null = null;

watch(paymentData, (newData) => {
  if (newData) {
    paymentCheckInterval = setInterval(checkPaymentStatus, 5000);
  } else if (paymentCheckInterval) {
    clearInterval(paymentCheckInterval);
  }
});

onUnmounted(() => {
  if (paymentCheckInterval) {
    clearInterval(paymentCheckInterval);
  }
});

// Copy to clipboard
const copyToClipboard = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text);
    alert("Đã sao chép!");
  } catch {
    // fallback
    const el = document.createElement("textarea");
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand("copy");
    document.body.removeChild(el);
    alert("Đã sao chép!");
  }
};

// Validation
const isFormValid = computed(() => {
  return (
    form.customer_name.trim() &&
    form.customer_email.trim() &&
    form.customer_phone.trim() &&
    form.shipping_address.trim() &&
    form.shipping_city.trim() &&
    checkoutIdempotencyKey.value
  );
});

useSeoMeta({
  title: () => `Thanh toán - ${siteName.value}`,
});
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Thanh toán</h1>

    <!-- Empty cart -->
    <div v-if="buyNowLoading" class="text-center py-12"><p class="text-xl text-gray-500">Đang chuẩn bị đơn hàng…</p></div>
    <div v-else-if="checkoutItems.length === 0" class="text-center py-12">
      <p class="text-xl text-gray-500 mb-6">Giỏ hàng trống</p>
      <UButton to="/" size="lg">Mua sắm ngay</UButton>
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
            <img :src="paymentData.qr_url" alt="QR Code" class="w-64 h-64" />
          </div>

          <p class="text-2xl font-bold text-primary-600 mb-4">
            {{ formatMoney(paymentData.amount) }}
          </p>

          <!-- Bank info -->
          <div class="bg-blue-50 rounded-lg p-4 mb-4 text-left space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Ngân hàng:</span>
              <span class="font-semibold text-blue-900">{{
                paymentData.bank_code
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Số tài khoản:</span>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-blue-900">{{
                  paymentData.bank_account
                }}</span>
                <button
                  class="text-xs text-blue-600 hover:underline"
                  @click="copyToClipboard(paymentData.bank_account)"
                >
                  Sao chép
                </button>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Chủ TK:</span>
              <span class="font-semibold text-blue-900">{{
                paymentData.account_name
              }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-blue-800">Nội dung CK:</span>
              <div class="flex items-center gap-2">
                <span class="font-bold text-blue-900">{{
                  paymentData.transfer_content
                }}</span>
                <button
                  class="text-xs text-blue-600 hover:underline"
                  @click="copyToClipboard(paymentData.transfer_content)"
                >
                  Sao chép
                </button>
              </div>
            </div>
          </div>

          <p class="text-sm text-red-500 mb-4">
            Vui lòng ghi đúng nội dung chuyển khoản để thanh toán được xác
            nhận tự động
          </p>

          <div class="flex items-center justify-center gap-2 text-gray-500">
            <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-primary-600"></span>
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
          <h2 class="text-2xl font-bold text-green-600 mb-2">
            Thanh toán thành công!
          </h2>
          <p class="text-gray-600">Đang chuyển hướng...</p>
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
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1"
                >Số điện thoại *</label
              >
              <input
                v-model="form.customer_phone"
                type="tel"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="0912345678"
              />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Email *</label>
              <input
                v-model="form.customer_email"
                type="email"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="email@example.com"
              />
            </div>
          </div>
        </div>

        <!-- Shipping Address -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <h2 class="text-xl font-bold mb-4">Địa chỉ giao hàng</h2>

          <div class="grid md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1"
                >Tỉnh/Thành phố *</label
              >
              <select
                v-model="selectedProvinceCode"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white"
              >
                <option value="">-- Chọn Tỉnh/Thành phố --</option>
                <option v-for="p in provinces" :key="p.code" :value="p.code">
                  {{ p.fullname }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Quận/Huyện</label>
              <input
                v-model="form.shipping_district"
                type="text"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Quận 1"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Phường/Xã</label>
              <select
                v-model="form.shipping_ward"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white"
                :disabled="!selectedProvinceCode"
              >
                <option value="">-- Chọn Phường/Xã --</option>
                <option v-for="w in wards" :key="w.code" :value="w.fullname">
                  {{ w.fullname }}
                </option>
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium mb-1"
                >Địa chỉ chi tiết *</label
              >
              <input
                v-model="form.shipping_address"
                type="text"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Số nhà, tên đường"
              />
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
              :class="{
                'border-primary-500 bg-primary-50':
                  form.payment_method === 'sepay',
              }"
            >
              <input
                v-model="form.payment_method"
                type="radio"
                value="sepay"
                class="w-5 h-5 text-primary-600"
              />
              <div class="flex-1">
                <p class="font-semibold">Chuyển khoản qua QR (Sepay)</p>
                <p class="text-sm text-gray-500">
                  Quét mã QR để thanh toán nhanh chóng
                </p>
              </div>
              <span class="rounded bg-primary-100 px-2 py-1 text-sm font-semibold text-primary-700">QR</span>
            </label>

            <label
              v-if="paymentCodEnabled"
              class="flex items-center gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50"
              :class="{
                'border-primary-500 bg-primary-50':
                  form.payment_method === 'cod',
              }"
            >
              <input
                v-model="form.payment_method"
                type="radio"
                value="cod"
                class="w-5 h-5 text-primary-600"
              />
              <div class="flex-1">
                <p class="font-semibold">Thanh toán khi nhận hàng (COD)</p>
                <p class="text-sm text-gray-500">
                  Thanh toán bằng tiền mặt khi giao hàng
                </p>
              </div>
              <span class="rounded bg-gray-100 px-2 py-1 text-sm font-semibold text-gray-700">COD</span>
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
            <div v-for="item in checkoutItems" :key="`${item.product_id}-${item.variant_id || 'base'}`" class="flex gap-3">
              <div class="w-16 h-16 bg-gray-100 rounded flex-shrink-0">
                <img
                  v-if="item.image"
                  :src="item.image"
                  :alt="item.name"
                  class="w-full h-full object-cover rounded"
                />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium line-clamp-2">
                  {{ item.name }}
                </p>
                <p class="text-sm text-gray-500">{{ item.variant_name ? `${item.variant_name} · ` : '' }}SL: {{ item.quantity }}</p>
              </div>
              <p class="text-sm font-semibold whitespace-nowrap">
                {{ formatMoney(item.unit_price * item.quantity) }}
              </p>
            </div>
          </div>

          <div class="border-t pt-4 space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Tạm tính</span>
              <span>{{ formatMoney(cartTotal) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Phí vận chuyển</span>
              <span :class="shippingFee === 0 ? 'text-green-600' : ''">
                {{
                  shippingFee === 0
                    ? "Miễn phí"
                    : formatMoney(shippingFee)
                }}
              </span>
            </div>
            <div class="flex justify-between text-lg font-bold pt-2 border-t">
              <span>Tổng cộng</span>
              <span class="text-primary-600">{{ formatMoney(orderTotal) }}</span>
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
            {{ form.payment_method === "sepay" ? "Thanh toán QR" : "Đặt hàng" }}
          </UButton>

          <p class="text-xs text-gray-500 text-center mt-4">
            Bằng việc đặt hàng, bạn đồng ý với
            <NuxtLink to="/terms" class="text-primary-600 hover:underline"
              >Điều khoản</NuxtLink
            >
            và
            <NuxtLink to="/privacy" class="text-primary-600 hover:underline"
              >Chính sách bảo mật</NuxtLink
            >
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
