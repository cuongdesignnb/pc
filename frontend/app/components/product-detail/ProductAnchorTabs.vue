<script setup lang="ts">
const props = defineProps<{
  hasCompatibility: boolean
  ratingCount: number
  questionsCount: number
}>()

const activeId = ref('mo-ta')
let observer: IntersectionObserver | null = null

const tabs = computed(() => [
  { id: 'mo-ta', label: 'MÔ TẢ' },
  { id: 'thong-so', label: 'THÔNG SỐ KỸ THUẬT' },
  { id: 'tuong-thich', label: 'TƯƠNG THÍCH PC BUILDER', condition: props.hasCompatibility },
  { id: 'danh-gia', label: `ĐÁNH GIÁ (${props.ratingCount})` },
  { id: 'hoi-dap', label: `HỎI ĐÁP (${props.questionsCount})` },
])

const jumpTo = (id: string) => {
  activeId.value = id
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(() => {
  const sections = tabs.value
    .map(tab => document.getElementById(tab.id))
    .filter((section): section is HTMLElement => Boolean(section))
  observer = new IntersectionObserver((entries) => {
    const visible = entries.filter(entry => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0]
    if (visible?.target.id) activeId.value = visible.target.id
  }, { rootMargin: '-96px 0px -65% 0px', threshold: [0.1, 0.4, 0.8] })
  sections.forEach(section => observer?.observe(section))
})

onUnmounted(() => observer?.disconnect())
</script>

<template>
  <nav aria-label="Điều hướng nội dung sản phẩm" class="pdp-anchor-tabs">
    <a
      v-for="tab in tabs.filter(tab => !tab.condition || props.hasCompatibility)"
      :key="tab.id"
      :href="`#${tab.id}`"
      class="pdp-anchor-tab"
      :class="activeId === tab.id ? 'pdp-anchor-tab--active' : ''"
      @click.prevent="jumpTo(tab.id)"
    >
      {{ tab.label }}
    </a>
  </nav>
</template>
