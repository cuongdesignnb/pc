<script setup lang="ts">
interface Province {
  code: string | number
  name: string
  fullname?: string
}

const props = defineProps<{ subtotal: number }>()
const config = useRuntimeConfig()
const { shippingFreeThreshold, shippingDefaultFee, shippingExpressFee, formatMoney } = useSettings()
const provinces = ref<Province[]>([])
const selectedProvinceCode = ref<string>('')
const loading = ref(false)

const selectedProvince = computed(() => provinces.value.find(province => String(province.code) === selectedProvinceCode.value))
const isFreeShipping = computed(() => shippingFreeThreshold.value > 0 && props.subtotal >= shippingFreeThreshold.value)
const standardFee = computed(() => isFreeShipping.value ? 'Miễn phí' : formatMoney(shippingDefaultFee.value))
const expressFee = computed(() => isFreeShipping.value ? 'Miễn phí' : formatMoney(shippingExpressFee.value))

onMounted(async () => {
  loading.value = true
  try {
    const response = await $fetch<Province[] | { provinces?: Province[] }>(`${config.public.apiBase}/locations/provinces`)
    provinces.value = Array.isArray(response) ? response : (response.provinces || [])
    const defaultProvince = provinces.value.find(province => province.name.toLowerCase().includes('hà nội')) || provinces.value[0]
    if (defaultProvince) selectedProvinceCode.value = String(defaultProvince.code)
  } catch {
    provinces.value = []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="pdp-panel pdp-delivery-card">
    <h2 class="text-sm font-bold text-slate-900">Kiểm tra giao hàng</h2>
    <label class="sr-only" for="pdp-province">Tỉnh/thành phố nhận hàng</label>
    <select id="pdp-province" v-model="selectedProvinceCode" :disabled="loading || !provinces.length" class="mt-3 h-9 w-full rounded-[6px] border border-slate-200 bg-white px-2.5 text-xs text-slate-700 outline-none focus:border-blue-500">
      <option v-if="!provinces.length" value="">{{ loading ? 'Đang tải khu vực…' : 'Chọn khu vực giao hàng' }}</option>
      <option v-for="province in provinces" :key="province.code" :value="String(province.code)">{{ province.fullname || province.name }}</option>
    </select>
    <p v-if="selectedProvince" class="mt-2 text-[10px] text-slate-500">Phương án giao hàng tại {{ selectedProvince.name }}</p>

    <div class="mt-3 space-y-3">
      <div class="flex items-start gap-2 text-xs">
        <span class="mt-0.5 text-blue-600" aria-hidden="true">◉</span>
        <div class="min-w-0 flex-1"><strong class="block text-slate-800">Giao nhanh</strong><span class="text-[10px] text-slate-500">Thời gian xác nhận theo khu vực</span></div>
        <strong class="shrink-0 text-[10px] text-emerald-600">{{ expressFee }}</strong>
      </div>
      <div class="flex items-start gap-2 text-xs">
        <span class="mt-0.5 text-blue-600" aria-hidden="true">◉</span>
        <div class="min-w-0 flex-1"><strong class="block text-slate-800">Giao tiêu chuẩn</strong><span class="text-[10px] text-slate-500">Thời gian xác nhận theo khu vực</span></div>
        <strong class="shrink-0 text-[10px] text-emerald-600">{{ standardFee }}</strong>
      </div>
      <div class="flex items-start gap-2 text-xs">
        <span class="mt-0.5 text-blue-600" aria-hidden="true">◉</span>
        <div class="min-w-0 flex-1"><strong class="block text-slate-800">Nhận tại cửa hàng</strong><span class="text-[10px] text-slate-500">Liên hệ kiểm tra tồn tại cửa hàng</span></div>
        <span class="shrink-0 text-[10px] font-semibold text-emerald-600">Hỗ trợ</span>
      </div>
    </div>
  </section>
</template>
