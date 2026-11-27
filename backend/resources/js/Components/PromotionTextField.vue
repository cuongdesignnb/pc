<script setup>
// "Khuyến mại" lines on the product page, one offer per line.
const text = defineModel({ type: String, default: '' });
defineProps({
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1">Khuyến mại (mỗi dòng một ưu đãi)</label>
        <textarea v-model="text" rows="4" maxlength="5000" placeholder="Tặng túi chống sốc&#10;Tặng chuột không dây&#10;Tặng bàn di chuột" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea>
        <p class="text-xs text-slate-500 mt-1">{{ hint }} Hiện thành khối "Khuyến mại" dưới giá ở trang sản phẩm. Để trống thì không hiện.</p>
        <p v-if="error" class="text-red-400 text-xs mt-1">{{ error }}</p>
    </div>
</template>
