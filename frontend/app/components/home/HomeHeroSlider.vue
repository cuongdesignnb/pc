<script setup lang="ts">
import type { HomepageBanner } from '~/types/homepage'

const props = withDefaults(defineProps<{
  banners: HomepageBanner[]
  autoplay?: boolean
  interval?: number
}>(), {
  autoplay: true,
  interval: 5000,
})

const currentIndex = ref(0)
const paused = ref(false)
const reducedMotion = ref(false)
let timer: ReturnType<typeof setInterval> | null = null

const currentBanner = computed(() => props.banners[currentIndex.value] || null)

function clearTimer() {
  if (timer) clearInterval(timer)
  timer = null
}

function next() {
  if (props.banners.length > 1) currentIndex.value = (currentIndex.value + 1) % props.banners.length
}

function previous() {
  if (props.banners.length > 1) currentIndex.value = (currentIndex.value - 1 + props.banners.length) % props.banners.length
}

function goTo(index: number) {
  currentIndex.value = index
}

function startTimer() {
  clearTimer()
  if (props.autoplay && props.banners.length > 1 && !paused.value && !reducedMotion.value) {
    timer = setInterval(next, Math.max(2500, props.interval))
  }
}

function pause() {
  paused.value = true
  clearTimer()
}

function resume() {
  paused.value = false
  startTimer()
}

function linkFor(banner: HomepageBanner): string | null {
  return banner.metadata?.cta_link || banner.link || null
}

onMounted(() => {
  if (import.meta.client) reducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  startTimer()
})

watch(() => [props.autoplay, props.interval, props.banners.length, reducedMotion.value], startTimer)
onUnmounted(clearTimer)
</script>

<template>
  <section class="home-hero-slider" @mouseenter="pause" @mouseleave="resume" @focusin="pause" @focusout="resume">
    <template v-if="currentBanner">
      <Transition name="hero-banner-fade" mode="out-in">
        <NuxtLink v-if="linkFor(currentBanner)" :key="currentBanner.id" :to="linkFor(currentBanner) || '/'" class="home-hero-slide">
          <img :src="currentBanner.image || ''" :alt="currentBanner.title" class="home-hero-image" fetchpriority="high">
          <div v-if="currentBanner.metadata?.text_in_image !== 'true' && (currentBanner.title || currentBanner.description)" class="home-hero-caption">
            <span v-if="currentBanner.badge" class="home-hero-badge">{{ currentBanner.badge }}</span>
            <strong>{{ currentBanner.title }}</strong>
            <small v-if="currentBanner.description">{{ currentBanner.description }}</small>
          </div>
        </NuxtLink>
        <div v-else :key="currentBanner.id" class="home-hero-slide">
          <img :src="currentBanner.image || ''" :alt="currentBanner.title" class="home-hero-image" fetchpriority="high">
          <div v-if="currentBanner.metadata?.text_in_image !== 'true' && (currentBanner.title || currentBanner.description)" class="home-hero-caption">
            <span v-if="currentBanner.badge" class="home-hero-badge">{{ currentBanner.badge }}</span>
            <strong>{{ currentBanner.title }}</strong>
            <small v-if="currentBanner.description">{{ currentBanner.description }}</small>
          </div>
        </div>
      </Transition>

      <div v-if="banners.length > 1" class="home-hero-controls">
        <button type="button" aria-label="Banner trước" @click="previous">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 5-7 7 7 7" /></svg>
        </button>
        <button type="button" aria-label="Banner tiếp theo" @click="next">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
        </button>
      </div>
      <div v-if="banners.length > 1" class="home-hero-dots" aria-label="Chọn banner">
        <button v-for="(banner, index) in banners" :key="banner.id" type="button" :class="{ 'is-active': index === currentIndex }" :aria-label="`Banner ${index + 1}`" :aria-current="index === currentIndex ? 'true' : undefined" @click="goTo(index)" />
      </div>
    </template>
    <div v-else class="home-hero-empty">Đang cập nhật chương trình nổi bật</div>
  </section>
</template>
