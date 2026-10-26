<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    heading: { type: String, default: 'Không thể lưu sản phẩm' },
});

const labels = {
    name: 'Tên sản phẩm',
    slug: 'Slug',
    sku: 'SKU',
    category_id: 'Danh mục',
    brand_id: 'Thương hiệu',
    component_type_id: 'Loại linh kiện',
    price: 'Giá gốc',
    sale_price: 'Giá sale',
    stock_quantity: 'Tồn kho',
    warranty_months: 'Thời hạn bảo hành',
    short_description: 'Mô tả ngắn',
    description: 'Mô tả chi tiết',
    meta_title: 'Meta title',
    meta_description: 'Meta description',
    video_url: 'Video sản phẩm',
    specifications_text: 'Thông số kỹ thuật',
    thumbnail: 'Ảnh đại diện',
    gallery: 'Thư viện ảnh',
};

const prefixes = [
    ['variants.', 'Biến thể sản phẩm'],
    ['highlights.', 'Điểm nổi bật'],
    ['detail_blocks.', 'Khối nội dung'],
    ['relations.', 'Sản phẩm liên quan'],
    ['compatibility_specs.', 'Thông số tương thích'],
    ['gallery.', 'Thư viện ảnh'],
];

const items = computed(() => Object.entries(props.errors || {}).map(([field, message]) => ({
    field,
    label: labels[field] || prefixes.find(([prefix]) => field.startsWith(prefix))?.[1] || field,
    message,
})));

const box = ref(null);

// Bring the summary into view after a failed submit so errors on fields far
// down the form (or without their own message) are never missed.
async function reveal() {
    await nextTick();
    box.value?.focus();
    box.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

defineExpose({ reveal });
</script>

<template>
    <div
        v-if="items.length"
        ref="box"
        tabindex="-1"
        role="alert"
        class="rounded-lg border border-red-500/40 bg-red-500/10 px-5 py-4 text-red-100 outline-none focus:ring-2 focus:ring-red-400/70"
    >
        <p class="font-semibold">{{ heading }} — vui lòng kiểm tra {{ items.length }} lỗi sau:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-200">
            <li v-for="error in items" :key="error.field">
                <strong>{{ error.label }}:</strong> {{ error.message }}
            </li>
        </ul>
    </div>
</template>
