<script setup lang="ts">
const props = defineProps<{
  quantity: number
  maxQuantity: number
  purchasable: boolean
  adding: boolean
  canBuild: boolean
}>()
const emit = defineEmits<{ 'update:quantity': [quantity: number]; add: []; buy: []; build: [] }>()

const updateQuantity = (event: Event) => {
  const value = Number((event.target as HTMLInputElement).value) || 1
  emit('update:quantity', Math.min(props.maxQuantity, Math.max(1, value)))
}
</script>

<template>
  <section class="space-y-3 border-t border-slate-200 pt-5">
    <div class="flex items-center gap-3">
      <span class="text-sm font-medium text-slate-700">Số lượng</span>
      <div class="flex overflow-hidden rounded-lg border border-slate-300">
        <button type="button" class="px-3 py-2 hover:bg-slate-50 disabled:opacity-40" aria-label="Giảm số lượng" :disabled="!purchasable || quantity <= 1" @click="emit('update:quantity', quantity - 1)">−</button>
        <input :value="quantity" type="number" min="1" :max="maxQuantity" class="w-12 border-x border-slate-300 text-center text-sm" :disabled="!purchasable" @input="updateQuantity">
        <button type="button" class="px-3 py-2 hover:bg-slate-50 disabled:opacity-40" aria-label="Tăng số lượng" :disabled="!purchasable || quantity >= maxQuantity" @click="emit('update:quantity', quantity + 1)">+</button>
      </div>
    </div>
    <p v-if="!purchasable" class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">Sản phẩm hiện chưa thể đặt trực tuyến.</p>
    <div class="grid gap-2 sm:grid-cols-2">
      <button type="button" :disabled="!purchasable || adding" class="rounded-lg bg-orange-600 px-4 py-3 text-sm font-bold text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-45" @click="emit('buy')">Mua ngay</button>
      <button type="button" :disabled="!purchasable || adding" class="rounded-lg bg-blue-700 px-4 py-3 text-sm font-bold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-45" @click="emit('add')">{{ adding ? 'Đang thêm…' : 'Thêm vào giỏ' }}</button>
    </div>
    <button v-if="canBuild" type="button" class="w-full rounded-lg border border-blue-600 px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50" @click="emit('build')">Thêm vào PC Builder</button>
  </section>
</template>
