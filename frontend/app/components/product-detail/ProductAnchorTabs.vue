<script setup lang="ts">
defineProps<{ hasCompatibility: boolean }>()
const tabs = computed(() => [
  { id: 'mo-ta', label: 'Mô tả' },
  { id: 'thong-so', label: 'Thông số kỹ thuật' },
  { id: 'tuong-thich', label: 'Tương thích PC Builder', condition: true },
  { id: 'danh-gia', label: 'Đánh giá' },
  { id: 'hoi-dap', label: 'Hỏi đáp' },
])
</script>

<template>
  <nav aria-label="Điều hướng nội dung sản phẩm" class="sticky top-14 z-30 -mx-4 overflow-x-auto border-y border-slate-200 bg-white/95 px-4 backdrop-blur lg:top-20">
    <div class="mx-auto flex w-max min-w-full max-w-7xl gap-5">
      <a v-for="tab in tabs.filter(tab => !tab.condition || hasCompatibility)" :key="tab.id" :href="`#${tab.id}`" class="whitespace-nowrap border-b-2 border-transparent py-3 text-sm font-semibold text-slate-600 hover:border-blue-600 hover:text-blue-700">{{ tab.label }}</a>
    </div>
  </nav>
</template>
