<script setup lang="ts">
import type { ProductHighlight } from '~/types/product-detail'
defineProps<{ highlights: ProductHighlight[]; shortDescription: string | null }>()
</script>

<template>
  <section v-if="highlights.length || shortDescription" class="pdp-highlights">
    <ul v-if="highlights.length" class="space-y-1">
      <li v-for="highlight in highlights" :key="highlight.id" class="flex gap-2 text-xs leading-5 text-slate-700">
        <span class="shrink-0 font-bold text-blue-600" aria-hidden="true">✓</span>
        <span>{{ highlight.title }}</span>
      </li>
    </ul>
    <p v-else class="text-xs leading-5 text-slate-700">{{ shortDescription }}</p>
  </section>
</template>
