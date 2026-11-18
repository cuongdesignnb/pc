<script>
/** Form state for a stored deposit rule (null = not set). */
export function depositRuleForm(rule) {
    return { type: rule?.type || '', value: rule?.value ?? '' };
}
</script>

<script setup>
// "Đặt cọc" rule for categories and products. Empty type means "use the
// parent category's rule" (and in the end the province / general setting).
defineProps({
    modelValue: { type: Object, required: true },
    inheritHint: { type: String, default: 'Để trống thì dùng mức của danh mục cha, rồi mức theo tỉnh / mức chung trong Cài đặt → Thanh toán.' },
    errors: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1">Đặt cọc (chuyển khoản)</label>
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="modelValue.type" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <option value="">Không đặt riêng</option>
                <option value="none">Không cọc</option>
                <option value="percent">% giá sản phẩm</option>
                <option value="fixed">Số tiền cố định mỗi sản phẩm</option>
            </select>
            <template v-if="modelValue.type === 'percent'">
                <input v-model="modelValue.value" type="number" min="0" max="100" step="1" class="w-24 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">%</span>
            </template>
            <template v-if="modelValue.type === 'fixed'">
                <input v-model="modelValue.value" type="number" min="0" step="50000" class="w-36 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">đ</span>
            </template>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            {{ modelValue.type ? 'Áp dụng khi bật "Cho phép đặt cọc" hoặc "Bắt buộc đặt cọc"; ưu tiên hơn mức theo tỉnh và mức chung (trừ tỉnh đặt "Không cọc"). "Không cọc": hàng thuộc đây không cần cọc, đơn chỉ có hàng này không bị bắt buộc cọc.' : inheritHint }}
        </p>
        <template v-for="(msg, key) in errors" :key="key">
            <p v-if="String(key).startsWith('deposit_rule')" class="text-red-400 text-xs mt-1">{{ msg }}</p>
        </template>
    </div>
</template>
