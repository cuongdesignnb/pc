<script>
/** Form state for a stored rule (null = not set). */
export function commissionForm(rule) {
    return {
        type: rule?.type || '',
        value: rule?.value ?? '',
        per: rule?.per || 'item',
        tiers: (rule?.tiers || []).map(tier => ({ min_price: tier.min_price, amount: tier.amount })),
    };
}
</script>

<script setup>
// "Hoa hồng CTV" editor for categories and products. Empty type means "use
// the parent category's rule".
const props = defineProps({
    modelValue: { type: Object, required: true },
    inheritHint: { type: String, default: 'Để trống thì dùng mức của danh mục cha.' },
    errors: { type: Object, default: () => ({}) },
});

function addTier() {
    props.modelValue.tiers.push({ min_price: '', amount: '' });
}
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-1">Hoa hồng CTV</label>
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="modelValue.type" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <option value="">Không đặt riêng</option>
                <option value="percent">% giá bán</option>
                <option value="fixed">Số tiền cố định</option>
                <option value="tiers">Theo khoảng giá</option>
            </select>
            <template v-if="modelValue.type === 'percent'">
                <input v-model="modelValue.value" type="number" min="0" max="100" step="0.1" class="w-24 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">%</span>
            </template>
            <template v-if="modelValue.type === 'fixed'">
                <input v-model="modelValue.value" type="number" min="0" step="1000" class="w-36 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">đ</span>
                <select v-model="modelValue.per" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option value="item">mỗi sản phẩm</option>
                    <option value="order">mỗi đơn</option>
                </select>
            </template>
        </div>
        <div v-if="modelValue.type === 'tiers'" class="mt-2 space-y-2">
            <div v-for="(tier, i) in modelValue.tiers" :key="i" class="flex items-center gap-2">
                <span class="text-sm text-slate-400">Giá từ</span>
                <input v-model="tier.min_price" type="number" min="0" step="100000" class="w-40 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">đ → hoa hồng</span>
                <input v-model="tier.amount" type="number" min="0" step="10000" class="w-32 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">đ/máy</span>
                <button type="button" @click="modelValue.tiers.splice(i, 1)" class="text-xs text-red-400 hover:text-red-300 ml-auto">Xóa</button>
            </div>
            <button type="button" @click="addTier" class="text-xs text-cyan-500 hover:text-cyan-400">+ Thêm khoảng giá</button>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            {{ modelValue.type ? 'Áp dụng cho sản phẩm thuộc đây và các danh mục con chưa đặt riêng.' : inheritHint }}
            Ví dụ: laptop cũ theo khoảng giá 200–500k/máy, laptop mới 1–2%, linh kiện 5%, sửa chữa 50.000đ mỗi đơn.
        </p>
        <template v-for="(msg, key) in errors" :key="key">
            <p v-if="String(key).startsWith('affiliate_commission')" class="text-red-400 text-xs mt-1">{{ msg }}</p>
        </template>
    </div>
</template>
