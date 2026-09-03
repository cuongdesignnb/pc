<script setup lang="ts">
import type { ProductDetailBlock } from '~/types/product-detail'

defineProps<{ description: string | null; blocks: ProductDetailBlock[] }>()

const text = (payload: Record<string, unknown>, key: string) => typeof payload[key] === 'string' ? payload[key] : ''
const cards = (payload: Record<string, unknown>) => Array.isArray(payload.cards)
  ? payload.cards.filter((card): card is Record<string, unknown> => typeof card === 'object' && card !== null)
  : []
</script>

<template>
  <section id="mo-ta" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 lg:p-7">
    <h2 class="text-xl font-bold text-slate-900">Mô tả sản phẩm</h2>
    <div v-for="block in blocks" :key="block.id" class="mt-6">
      <template v-if="block.type === 'hero_banner'">
        <NuxtImg v-if="text(block.payload, 'image_url')" :src="text(block.payload, 'image_url')" :alt="text(block.payload, 'alt') || block.title || 'Hình ảnh sản phẩm'" class="w-full rounded-xl object-cover" />
        <h3 v-if="block.title" class="mt-3 text-lg font-bold text-slate-900">{{ block.title }}</h3>
        <p v-if="text(block.payload, 'description')" class="mt-2 whitespace-pre-line leading-7 text-slate-700">{{ text(block.payload, 'description') }}</p>
      </template>
      <template v-else-if="['feature_cards', 'benchmark_cards', 'use_case_cards'].includes(block.type)">
        <h3 v-if="block.title" class="text-lg font-bold text-slate-900">{{ block.title }}</h3>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <article v-for="(card, index) in cards(block.payload)" :key="index" class="rounded-xl border border-slate-200 p-4">
            <p v-if="text(card, 'title')" class="font-semibold text-slate-800">{{ text(card, 'title') }}</p>
            <p v-if="text(card, 'value')" class="mt-1 text-xl font-bold text-blue-700">{{ text(card, 'value') }}</p>
            <p v-if="text(card, 'description')" class="mt-1 text-sm leading-6 text-slate-600">{{ text(card, 'description') }}</p>
          </article>
        </div>
      </template>
      <template v-else-if="block.type === 'notice'">
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4"><h3 v-if="block.title" class="font-bold text-blue-950">{{ block.title }}</h3><p class="mt-1 whitespace-pre-line text-sm leading-6 text-blue-900">{{ text(block.payload, 'description') }}</p></div>
      </template>
      <template v-else-if="block.type === 'image_text'">
        <div class="grid gap-4 md:grid-cols-2 md:items-center"><NuxtImg v-if="text(block.payload, 'image_url')" :src="text(block.payload, 'image_url')" :alt="text(block.payload, 'alt') || block.title || 'Hình ảnh sản phẩm'" class="w-full rounded-xl" /><div><h3 v-if="block.title" class="text-lg font-bold text-slate-900">{{ block.title }}</h3><p class="mt-2 whitespace-pre-line leading-7 text-slate-700">{{ text(block.payload, 'description') }}</p></div></div>
      </template>
    </div>
    <p v-if="description" class="mt-7 whitespace-pre-line leading-7 text-slate-700">{{ description }}</p>
    <p v-if="!description && !blocks.length" class="mt-4 text-sm text-slate-500">Sản phẩm chưa có mô tả chi tiết.</p>
  </section>
</template>
