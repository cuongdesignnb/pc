<script setup lang="ts">
import type { ProductSpecification } from '~/types/product-detail'
defineProps<{ specifications: ProductSpecification[] }>()
</script>

<template>
  <section id="thong-so" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 lg:p-7">
    <h2 class="text-xl font-bold text-slate-900">Thông số kỹ thuật</h2>
    <div v-if="specifications.length" class="mt-4 overflow-hidden rounded-xl border border-slate-200">
      <dl class="divide-y divide-slate-200 text-sm">
        <div v-for="specification in specifications" :key="`${specification.key}-${specification.label}`" class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4"><dt class="font-semibold text-slate-700">{{ specification.label }}</dt><dd class="sm:col-span-2 text-slate-700">{{ specification.value }}<span v-if="specification.unit"> {{ specification.unit }}</span></dd></div>
      </dl>
    </div>
    <p v-else class="mt-4 text-sm text-slate-500">Sản phẩm chưa có thông số kỹ thuật.</p>
  </section>
</template>
