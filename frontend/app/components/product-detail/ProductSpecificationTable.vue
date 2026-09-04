<script setup lang="ts">
import type { ProductSpecification } from '~/types/product-detail'

defineProps<{ specifications: ProductSpecification[] }>()
</script>

<template>
  <section id="thong-so" class="pdp-panel scroll-mt-28">
    <h2 class="pdp-section-title">Thông số kỹ thuật</h2>
    <div v-if="specifications.length" class="mt-3 overflow-hidden rounded-[6px] border border-slate-200">
      <dl class="divide-y divide-slate-100 text-[11px]">
        <div v-for="specification in specifications" :key="`${specification.key}-${specification.label}`" class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.45fr)] gap-3 px-3 py-2">
          <dt class="text-slate-500">{{ specification.label }}</dt>
          <dd class="font-medium text-slate-700">{{ specification.value }}<span v-if="specification.unit"> {{ specification.unit }}</span></dd>
        </div>
      </dl>
    </div>
    <p v-else class="mt-3 text-xs text-slate-500">Sản phẩm chưa có thông số kỹ thuật.</p>
  </section>
</template>
