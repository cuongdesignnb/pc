<script setup lang="ts">
import type { ProductDetailBlock } from '~/types/product-detail'

const props = defineProps<{ description: string | null; blocks: ProductDetailBlock[] }>()

const heroBlock = computed(() => props.blocks.find(block => block.type === 'hero_banner'))
const benchmarkBlock = computed(() => props.blocks.find(block => block.type === 'benchmark_cards'))
const useCaseBlock = computed(() => props.blocks.find(block => block.type === 'use_case_cards'))
const otherBlocks = computed(() => props.blocks.filter(block => !['hero_banner', 'benchmark_cards', 'use_case_cards'].includes(block.type)))

const payloadText = (payload: Record<string, unknown> | undefined, key: string): string => {
  const value = payload?.[key]
  return typeof value === 'string' ? value : ''
}

const payloadCards = (payload: Record<string, unknown> | undefined): Record<string, unknown>[] => {
  const value = payload?.cards
  return Array.isArray(value)
    ? value.filter((card): card is Record<string, unknown> => typeof card === 'object' && card !== null)
    : []
}

const payloadFeatures = (payload: Record<string, unknown> | undefined): string[] => {
  const value = payload?.features
  return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []
}
</script>

<template>
  <section id="mo-ta" class="pdp-panel scroll-mt-28">
    <div v-if="heroBlock || benchmarkBlock || useCaseBlock" class="pdp-feature-overview">
      <article v-if="heroBlock" class="pdp-marketing-hero">
        <div class="pdp-marketing-copy">
          <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/70">GEFORCE RTX</span>
          <h2 class="mt-1 text-2xl font-bold leading-none text-lime-400">{{ heroBlock.title }}</h2>
          <p v-if="payloadText(heroBlock.payload, 'tagline')" class="mt-2 text-xs font-semibold text-white">{{ payloadText(heroBlock.payload, 'tagline') }}</p>
          <p v-else-if="payloadText(heroBlock.payload, 'description')" class="mt-2 whitespace-pre-line text-[11px] leading-5 text-white/80">{{ payloadText(heroBlock.payload, 'description') }}</p>
        </div>
        <NuxtImg
          v-if="payloadText(heroBlock.payload, 'image_url')"
          :src="payloadText(heroBlock.payload, 'image_url')"
          :alt="payloadText(heroBlock.payload, 'alt') || heroBlock.title || 'Hình ảnh sản phẩm'"
          width="640"
          height="480"
          loading="lazy"
          class="h-[190px] w-full object-contain px-3"
        />
        <div v-if="payloadFeatures(heroBlock.payload).length" class="grid grid-cols-2 gap-1.5 px-3 pb-3">
          <span v-for="feature in payloadFeatures(heroBlock.payload)" :key="feature" class="rounded border border-lime-400/70 px-1.5 py-1 text-center text-[9px] font-semibold text-lime-300">{{ feature }}</span>
        </div>
      </article>

      <div class="min-w-0">
        <h2 v-if="benchmarkBlock?.title" class="text-[15px] font-bold text-slate-900">{{ benchmarkBlock.title }}</h2>
        <h2 v-else-if="useCaseBlock?.title" class="text-[15px] font-bold text-slate-900">{{ useCaseBlock.title }}</h2>
        <p v-if="heroBlock && payloadText(heroBlock.payload, 'description') && payloadText(heroBlock.payload, 'tagline')" class="mt-1 text-xs leading-5 text-slate-600">{{ payloadText(heroBlock.payload, 'description') }}</p>

        <div v-if="benchmarkBlock" class="mt-3 grid grid-cols-3 gap-2">
          <article v-for="(card, index) in payloadCards(benchmarkBlock.payload)" :key="index" class="pdp-benchmark-card">
            <p class="line-clamp-2 text-[9px] font-semibold leading-3 text-orange-500">{{ payloadText(card, 'title') }}</p>
            <strong class="mt-1 block text-xl leading-none text-emerald-600">{{ payloadText(card, 'value') }}</strong>
            <span class="mt-1 block text-[9px] leading-3 text-emerald-600">{{ payloadText(card, 'description') }}</span>
          </article>
        </div>

        <template v-if="useCaseBlock">
          <h3 class="mt-4 text-sm font-bold text-slate-900">{{ useCaseBlock.title || 'Phù hợp cho' }}</h3>
          <div class="mt-2 grid grid-cols-3 gap-2">
            <article v-for="(card, index) in payloadCards(useCaseBlock.payload)" :key="index" class="pdp-use-case-card">
              <span class="text-xl" aria-hidden="true">{{ payloadText(card, 'icon') || '◈' }}</span>
              <strong class="mt-1 block text-[10px] leading-4 text-slate-800">{{ payloadText(card, 'title') }}</strong>
              <span class="mt-0.5 block text-[9px] leading-3 text-slate-500">{{ payloadText(card, 'description') }}</span>
            </article>
          </div>
        </template>
      </div>
    </div>

    <div v-for="block in otherBlocks" :key="block.id" class="mt-4">
      <template v-if="block.type === 'feature_cards'">
        <h3 v-if="block.title" class="text-sm font-bold text-slate-900">{{ block.title }}</h3>
        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <article v-for="(card, index) in payloadCards(block.payload)" :key="index" class="rounded-[7px] border border-slate-200 p-3">
            <p class="text-xs font-semibold text-slate-800">{{ payloadText(card, 'title') }}</p>
            <p v-if="payloadText(card, 'value')" class="mt-1 text-lg font-bold text-blue-700">{{ payloadText(card, 'value') }}</p>
            <p v-if="payloadText(card, 'description')" class="mt-1 text-[11px] leading-4 text-slate-600">{{ payloadText(card, 'description') }}</p>
          </article>
        </div>
      </template>
      <template v-else-if="block.type === 'notice'">
        <div class="rounded-[7px] border border-amber-200 bg-amber-50 px-3 py-2.5">
          <h3 v-if="block.title" class="text-xs font-bold text-amber-900">{{ block.title }}</h3>
          <p class="mt-1 whitespace-pre-line text-[11px] leading-4 text-amber-800">{{ payloadText(block.payload, 'description') }}</p>
        </div>
      </template>
      <template v-else-if="block.type === 'image_text'">
        <div class="grid gap-3 rounded-[7px] border border-slate-200 p-3 md:grid-cols-2 md:items-center">
          <NuxtImg v-if="payloadText(block.payload, 'image_url')" :src="payloadText(block.payload, 'image_url')" :alt="payloadText(block.payload, 'alt') || block.title || 'Hình ảnh sản phẩm'" class="w-full object-contain" />
          <div><h3 v-if="block.title" class="text-sm font-bold text-slate-900">{{ block.title }}</h3><p class="mt-1 whitespace-pre-line text-xs leading-5 text-slate-700">{{ payloadText(block.payload, 'description') }}</p></div>
        </div>
      </template>
    </div>

    <p v-if="description" class="mt-4 whitespace-pre-line border-t border-slate-100 pt-3 text-xs leading-5 text-slate-700">{{ description }}</p>
    <p v-if="!description && !blocks.length" class="text-xs text-slate-500">Sản phẩm chưa có mô tả chi tiết.</p>
  </section>
</template>
