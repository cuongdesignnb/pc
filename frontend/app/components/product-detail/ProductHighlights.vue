<script setup lang="ts">
import type { ProductHighlight } from '~/types/product-detail'
defineProps<{ highlights: ProductHighlight[]; shortDescription: string | null }>()
</script>

<template>
  <section v-if="highlights.length || shortDescription" class="border-t border-slate-200 pt-5">
    <ul v-if="highlights.length" class="space-y-2">
      <li v-for="highlight in highlights" :key="highlight.id" class="flex gap-2 text-sm leading-5 text-slate-700"><span class="text-blue-600" aria-hidden="true">✓</span><span>{{ highlight.title }}</span></li>
    </ul>
    <p v-else class="text-sm leading-6 text-slate-700">{{ shortDescription }}</p>
  </section>
</template>
