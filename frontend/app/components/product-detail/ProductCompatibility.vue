<script setup lang="ts">
interface CompatibilityResponse {
  component_type: { id: number; name: string; slug: string } | null
  facts: { label: string; value: string; status: 'ok' | 'warning' | 'error' }[]
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
</script>

<template>
  <section id="tuong-thich" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 lg:p-7">
    <h2 class="text-xl font-bold text-slate-900">Tương thích PC Builder</h2>
    <p v-if="loading" class="mt-3 text-sm text-slate-500">Đang kiểm tra dữ liệu tương thích…</p>
    <template v-else-if="data">
      <p v-if="data.component_type" class="mt-2 text-sm text-slate-600">Loại linh kiện: {{ data.component_type.name }}</p>
      <dl v-if="data.facts.length" class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 px-4"><div v-for="fact in data.facts" :key="fact.label" class="flex justify-between gap-4 py-3 text-sm"><dt class="text-slate-600">{{ fact.label }}</dt><dd class="font-medium text-slate-800">{{ fact.value }}</dd></div></dl>
      <ul v-if="data.warnings.length" class="mt-4 space-y-2"><li v-for="warning in data.warnings" :key="warning" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ warning }}</li></ul>
      <p v-if="!data.facts.length && !data.warnings.length" class="mt-4 text-sm text-slate-500">Chưa có dữ liệu tương thích để hiển thị.</p>
    </template>
  </section>
</template>
