<script setup lang="ts">
import type { ProductVariant } from '~/types/product-detail'

const props = defineProps<{
  variants: ProductVariant[]
  optionGroups: { name: string; values: string[] }[]
  selectedVariant: ProductVariant | null
  selectedAttributes: Record<string, string>
  isOptionAvailable: (name: string, value: string) => boolean
}>()
const emit = defineEmits<{ selectVariant: [variant: ProductVariant]; selectAttribute: [name: string, value: string] }>()
</script>

<template>
  <section v-if="variants.length" class="space-y-4 border-t border-slate-200 pt-5">
    <template v-if="optionGroups.length">
      <div v-for="group in optionGroups" :key="group.name">
        <p class="mb-2 text-sm font-semibold text-slate-800">{{ group.name }}: <span class="font-normal text-slate-600">{{ selectedAttributes[group.name] || 'Chưa chọn' }}</span></p>
        <div class="flex flex-wrap gap-2">
          <button v-for="value in group.values" :key="value" type="button" :disabled="!isOptionAvailable(group.name, value)" :aria-pressed="selectedAttributes[group.name] === value" class="rounded-lg border px-3 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-40" :class="selectedAttributes[group.name] === value ? 'border-blue-600 bg-blue-50 text-blue-800' : 'border-slate-300 hover:border-blue-400'" @click="emit('selectAttribute', group.name, value)">{{ value }}</button>
        </div>
      </div>
    </template>
    <template v-else>
      <p class="text-sm font-semibold text-slate-800">Chọn phiên bản</p>
      <div class="grid gap-2 sm:grid-cols-2">
        <button v-for="variant in variants" :key="variant.id" type="button" :disabled="!variant.inventory.is_available" :aria-pressed="selectedVariant?.id === variant.id" class="rounded-lg border p-3 text-left disabled:cursor-not-allowed disabled:opacity-50" :class="selectedVariant?.id === variant.id ? 'border-blue-600 bg-blue-50' : 'border-slate-200 hover:border-blue-400'" @click="emit('selectVariant', variant)">
          <span class="block text-sm font-semibold text-slate-800">{{ variant.name }}</span>
          <span class="mt-1 block text-xs" :class="variant.inventory.is_available ? 'text-emerald-700' : 'text-slate-500'">{{ variant.inventory.is_available ? `Còn ${variant.inventory.quantity}` : 'Hết hàng' }}</span>
        </button>
      </div>
    </template>
  </section>
</template>
