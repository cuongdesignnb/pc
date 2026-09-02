<script setup lang="ts">
defineProps<{ price: number; purchasable: boolean }>()
const emit = defineEmits<{ add: []; buy: [] }>()
const { formatMoney } = useSettings()
</script>

<template>
  <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-lg lg:hidden">
    <div class="mx-auto flex max-w-xl items-center gap-3"><strong class="min-w-0 flex-1 text-lg text-blue-700">{{ formatMoney(price) }}</strong><button type="button" :disabled="!purchasable" class="rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-45" @click="emit('buy')">Mua ngay</button><button type="button" :disabled="!purchasable" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-45" @click="emit('add')">Thêm giỏ</button></div>
  </div>
</template>
