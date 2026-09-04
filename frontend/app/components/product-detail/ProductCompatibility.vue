<script setup lang="ts">
interface CompatibilityFact {
  label: string
  value: string
  status: 'ok' | 'warning' | 'error'
}

interface CompatibilityResponse {
  component_type: { id: number; name: string; slug: string } | null
  facts: CompatibilityFact[]
  warnings: string[]
}

const props = defineProps<{ slug: string }>()
const config = useRuntimeConfig()
const data = ref<CompatibilityResponse | null>(null)
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    data.value = await $fetch<CompatibilityResponse>(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/compatibility-summary`)
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
})

const statusIcon = (status: CompatibilityFact['status']) => status === 'ok' ? '✓' : status === 'warning' ? '!' : '×'
const statusClass = (status: CompatibilityFact['status']) => status === 'ok' ? 'text-emerald-600' : status === 'warning' ? 'text-amber-600' : 'text-red-600'
</script>

<template>
  <section id="tuong-thich" class="pdp-panel scroll-mt-28">
    <div class="flex items-center justify-between gap-3">
      <h2 class="pdp-section-title">Tương thích PC Builder</h2>
      <span v-if="data?.component_type" class="text-[10px] text-slate-500">{{ data.component_type.name }}</span>
    </div>
    <p v-if="loading" class="mt-3 text-xs text-slate-500">Đang kiểm tra dữ liệu tương thích…</p>
    <template v-else-if="data">
      <dl v-if="data.facts.length" class="mt-3 overflow-hidden rounded-[6px] border border-slate-200 text-[11px]">
        <div v-for="fact in data.facts.slice(0, 8)" :key="fact.label" class="flex items-center justify-between gap-3 border-b border-slate-100 px-3 py-2 last:border-0">
          <dt class="text-slate-500">{{ fact.label }}</dt>
          <dd class="flex items-center gap-2 text-right font-medium text-slate-700"><span :class="statusClass(fact.status)" aria-hidden="true">{{ statusIcon(fact.status) }}</span>{{ fact.value }}</dd>
        </div>
      </dl>
      <ul v-if="data.warnings.length" class="mt-3 space-y-1.5"><li v-for="warning in data.warnings" :key="warning" class="rounded-[6px] bg-amber-50 px-3 py-2 text-[11px] leading-4 text-amber-800">{{ warning }}</li></ul>
      <p v-if="!data.facts.length && !data.warnings.length" class="mt-3 text-xs text-slate-500">Chưa có dữ liệu tương thích để hiển thị.</p>
      <NuxtLink :to="`/cau-hinh?product=${encodeURIComponent(slug)}`" class="mt-3 inline-flex h-8 w-full items-center justify-center rounded-[6px] border border-blue-400 text-[11px] font-semibold uppercase text-blue-700 hover:bg-blue-50">Kiểm tra trên PC Builder</NuxtLink>
    </template>
    <p v-else class="mt-3 text-xs text-slate-500">Chưa có dữ liệu tương thích để hiển thị.</p>
  </section>
</template>
