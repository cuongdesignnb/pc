<script setup lang="ts">
import type { ProductCard } from '~/types/product-detail'

const props = defineProps<{
  enabled: boolean
  endsAt: string | null
  products: ProductCard[]
}>()

const remainingSeconds = ref<number | null>(null)
let timer: ReturnType<typeof setInterval> | null = null

const countdown = computed(() => {
  if (remainingSeconds.value === null || remainingSeconds.value <= 0) return null
  const hours = Math.floor(remainingSeconds.value / 3600)
  const minutes = Math.floor((remainingSeconds.value % 3600) / 60)
  const seconds = remainingSeconds.value % 60
  return [hours, minutes, seconds].map(value => String(value).padStart(2, '0'))
})

function updateCountdown() {
  if (!props.endsAt) {
    remainingSeconds.value = null
    return
  }
  const timestamp = new Date(props.endsAt).getTime()
  if (!Number.isFinite(timestamp)) {
    remainingSeconds.value = null
    return
  }
  remainingSeconds.value = Math.max(0, Math.floor((timestamp - Date.now()) / 1000))
}

onMounted(() => {
  updateCountdown()
  timer = setInterval(updateCountdown, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <section v-if="enabled && products.length" class="home-section home-flash-sale">
    <div class="pc-container">
      <div class="home-section-heading home-flash-sale-heading">
        <div class="home-flash-sale-title"><span aria-hidden="true">ϟ</span><h2>FLASH SALE</h2><strong>Giá sốc trong ngày</strong></div>
        <div v-if="countdown" class="home-countdown" aria-label="Thời gian còn lại">
          <span>{{ countdown[0] }}</span><b>:</b><span>{{ countdown[1] }}</span><b>:</b><span>{{ countdown[2] }}</span>
        </div>
        <span v-else-if="remainingSeconds === 0" class="home-countdown-ended">Đã kết thúc</span>
        <NuxtLink to="/categories/linh-kien-pc">Xem tất cả <span aria-hidden="true">›</span></NuxtLink>
      </div>
      <HomeProductCarousel :products="products.slice(0, 6)" variant="homepage" />
    </div>
  </section>
</template>
