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
  <section class="space-y-2 pt-1">
    <div class="grid gap-2 sm:grid-cols-[116px_minmax(0,1fr)]">
      <div class="flex h-10 overflow-hidden rounded-[6px] border border-slate-300 bg-white">
        <button type="button" class="w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40" aria-label="Giảm số lượng" :disabled="!purchasable || quantity <= 1" @click="emit('update:quantity', quantity - 1)">−</button>
        <input :value="quantity" type="number" min="1" :max="maxQuantity" aria-label="Số lượng" class="min-w-0 flex-1 border-x border-slate-300 text-center text-xs" :disabled="!purchasable" @input="updateQuantity">
        <button type="button" class="w-9 text-slate-600 hover:bg-slate-50 disabled:opacity-40" aria-label="Tăng số lượng" :disabled="!purchasable || quantity >= maxQuantity" @click="emit('update:quantity', quantity + 1)">+</button>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <button type="button" :disabled="!purchasable || adding" class="h-10 rounded-[6px] bg-orange-600 px-3 text-xs font-bold uppercase text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-45" @click="emit('buy')">Mua ngay</button>
        <button type="button" :disabled="!purchasable || adding" class="h-10 rounded-[6px] bg-blue-700 px-3 text-xs font-bold uppercase text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-45" @click="emit('add')">{{ adding ? 'Đang thêm…' : 'Thêm vào giỏ' }}</button>
      </div>
    </div>
    <p v-if="!purchasable" class="rounded-[6px] bg-slate-100 px-3 py-2 text-xs text-slate-600">Sản phẩm hiện chưa thể đặt trực tuyến.</p>
    <button v-if="canBuild" type="button" class="h-9 w-full rounded-[6px] border border-blue-500 bg-white px-4 text-xs font-semibold uppercase text-blue-700 hover:bg-blue-50" @click="emit('build')">Thêm vào PC Builder</button>
  </section>
</template>
