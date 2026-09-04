<script setup lang="ts">
import type { ProductDetail } from '~/types/product-detail'

const props = defineProps<{ product: ProductDetail }>()
const selectedIndex = ref(0)
const open = ref(false)
const zoomed = ref(false)
const closeButton = ref<HTMLButtonElement | null>(null)
const toast = useToast()

const images = computed(() => props.product.images.filter(image => Boolean(image.url)))
const selected = computed(() => images.value[selectedIndex.value])

const next = () => {
  if (images.value.length) selectedIndex.value = (selectedIndex.value + 1) % images.value.length
  zoomed.value = false
}
const previous = () => {
  if (images.value.length) selectedIndex.value = (selectedIndex.value - 1 + images.value.length) % images.value.length
  zoomed.value = false
}
const openModal = () => {
  if (!selected.value) return
  open.value = true
  nextTick(() => closeButton.value?.focus())
}
const shareProduct = async () => {
  if (!import.meta.client) return
  const shareData = { title: props.product.name, url: window.location.href }
  try {
    if (navigator.share) {
      await navigator.share(shareData)
      return
    }
    await navigator.clipboard.writeText(window.location.href)
    toast.add({ title: 'Đã sao chép liên kết', color: 'success' })
  } catch {
    // Closing the native share sheet is not an error worth surfacing.
  }
}
const onKeydown = (event: KeyboardEvent) => {
  if (!open.value) return
  if (event.key === 'Escape') open.value = false
  if (event.key === 'ArrowRight') next()
  if (event.key === 'ArrowLeft') previous()
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <section class="pdp-gallery flex gap-3" aria-label="Thư viện ảnh sản phẩm">
    <div v-if="images.length > 1" class="order-2 flex max-h-[430px] shrink-0 gap-2 overflow-auto md:order-1 md:flex-col">
      <button
        v-for="(image, index) in images"
        :key="image.id"
        type="button"
        :aria-label="`Xem ảnh ${index + 1}`"
        :aria-pressed="selectedIndex === index"
        class="h-16 w-16 shrink-0 overflow-hidden rounded-[7px] border bg-white p-1"
        :class="selectedIndex === index ? 'border-blue-600' : 'border-slate-200 hover:border-blue-300'"
        @click="selectedIndex = index; zoomed = false"
      >
        <NuxtImg :src="image.url!" :alt="image.alt || `${product.name} ${index + 1}`" width="64" height="64" loading="lazy" class="h-full w-full object-contain" />
      </button>
    </div>
    <div class="group relative order-1 min-h-[360px] min-w-0 flex-1 overflow-hidden border border-slate-100 bg-white md:min-h-[430px]">
      <button type="button" class="absolute right-3 top-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm hover:border-blue-300 hover:text-blue-700" aria-label="Chia sẻ sản phẩm" @click="shareProduct">
        <span aria-hidden="true">↗</span>
      </button>
      <button type="button" class="h-full w-full" :aria-label="`Phóng to ảnh ${product.name}`" @click="openModal">
      <NuxtImg
        v-if="selected?.url"
        :src="selected.url"
        :alt="selected.alt || product.name"
        :width="selected.width || 800"
        :height="selected.height || 800"
        sizes="(max-width: 1024px) 100vw, 42vw"
        preload
        class="h-full w-full object-contain p-5 transition duration-300 group-hover:scale-[1.02]"
      />
      <span v-else class="flex h-full items-center justify-center text-sm text-slate-400">Chưa có ảnh sản phẩm</span>
      <span v-if="images.length > 1" class="absolute bottom-3 right-3 rounded bg-slate-900/70 px-2 py-1 text-[11px] font-medium text-white">{{ selectedIndex + 1 }}/{{ images.length }}</span>
      </button>
    </div>
  </section>

  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/95 p-4" role="dialog" aria-modal="true" :aria-label="`Ảnh ${product.name}`" @click.self="open = false">
      <button ref="closeButton" type="button" class="absolute right-4 top-4 rounded-full bg-white/15 px-4 py-2 text-sm text-white hover:bg-white/25" aria-label="Đóng thư viện ảnh" @click="open = false">Đóng</button>
      <button v-if="images.length > 1" type="button" class="absolute left-3 rounded-full bg-white/15 p-3 text-white hover:bg-white/25 md:left-8" aria-label="Ảnh trước" @click="previous">←</button>
      <button type="button" class="max-h-[85vh] max-w-[90vw] overflow-auto" :aria-label="zoomed ? 'Thu nhỏ ảnh' : 'Phóng to ảnh'" @click="zoomed = !zoomed">
        <NuxtImg v-if="selected?.url" :src="selected.url" :alt="selected.alt || product.name" class="max-h-[82vh] max-w-full object-contain transition" :class="zoomed ? 'scale-150 cursor-zoom-out' : 'cursor-zoom-in'" />
      </button>
      <button v-if="images.length > 1" type="button" class="absolute right-3 rounded-full bg-white/15 p-3 text-white hover:bg-white/25 md:right-8" aria-label="Ảnh sau" @click="next">→</button>
    </div>
  </Teleport>
</template>
