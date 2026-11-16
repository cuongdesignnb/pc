<script setup>
// "Robots" and "Canonical" for one product, post or page (SEO box of its form).
defineProps({ form: { type: Object, required: true } });

const robotsOptions = [
    { value: '', label: 'Mặc định (index, follow)' },
    { value: 'index,follow', label: 'index, follow' },
    { value: 'noindex,follow', label: 'noindex, follow (ẩn khỏi Google, vẫn theo link)' },
    { value: 'noindex,nofollow', label: 'noindex, nofollow' },
    { value: 'index,nofollow', label: 'index, nofollow' },
];
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Robots</label>
            <select v-model="form.meta_robots" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <option v-for="option in robotsOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <p v-if="form.errors.meta_robots" class="text-xs text-red-400 mt-1">{{ form.errors.meta_robots }}</p>
            <p class="text-xs text-slate-500 mt-1">Chọn noindex để Google không đưa trang này lên kết quả tìm kiếm; trang noindex cũng bị bỏ khỏi sitemap.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Canonical</label>
            <input v-model="form.canonical_url" type="text" placeholder="Để trống = link của chính trang này" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
            <p v-if="form.errors.canonical_url" class="text-xs text-red-400 mt-1">{{ form.errors.canonical_url }}</p>
            <p class="text-xs text-slate-500 mt-1">Link https://... đầy đủ hoặc đường dẫn trên website (ví dụ /laptop/dell-latitude-7420). Chỉ điền khi muốn Google coi một trang khác là bản chính.</p>
        </div>
    </div>
</template>
