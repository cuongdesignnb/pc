<script>
/** Editor rows for stored packages [{months, price}] or [{months, percent}]. */
export function warrantyRows(packages) {
    return (Array.isArray(packages) ? packages : []).map(pkg => ({
        months: pkg.months,
        mode: pkg.percent !== undefined && pkg.percent !== null ? 'percent' : 'fixed',
        price: pkg.price ?? 0,
        percent: pkg.percent ?? '',
    }));
}

/** What the backend stores for the rows. */
export function warrantyPayload(rows) {
    return rows
        .filter(row => Number(row.months) > 0)
        .map(row => row.mode === 'percent'
            ? { months: Number(row.months), percent: Number(row.percent || 0) }
            : { months: Number(row.months), price: Number(row.price || 0) });
}
</script>

<script setup>
// Paid warranty packages the customer ticks on the product page
// ("1 tháng", "3 tháng (+400.000đ)"). Each adds a fixed amount or a
// percentage of the product's price.
const rows = defineModel({ type: Array, required: true });
defineProps({
    hint: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    errorKey: { type: String, default: 'warranty_packages' },
});

function add() {
    const last = Math.max(0, ...rows.value.map(row => Number(row.months) || 0));
    rows.value.push({ months: last ? last * 2 : 1, mode: 'fixed', price: 0, percent: '' });
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="block text-sm font-medium text-slate-300">Gói bảo hành khách tích chọn khi mua</label>
            <button type="button" class="text-xs text-cyan-500 hover:text-cyan-400" @click="add">+ Thêm gói</button>
        </div>
        <div v-for="(row, i) in rows" :key="i" class="flex flex-wrap items-center gap-2 mb-2">
            <input v-model.number="row.months" type="number" min="1" max="120" class="w-20 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
            <span class="text-sm text-slate-400">tháng, cộng thêm (âm là giảm)</span>
            <select v-model="row.mode" class="border border-slate-700/50 rounded-lg px-2 py-2 text-sm">
                <option value="fixed">số tiền</option>
                <option value="percent">% giá máy</option>
            </select>
            <template v-if="row.mode === 'fixed'">
                <input v-model.number="row.price" type="number" step="10000" class="w-36 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">đ</span>
            </template>
            <template v-else>
                <input v-model="row.percent" type="number" min="-100" max="100" step="0.5" class="w-24 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <span class="text-sm text-slate-400">% (làm tròn lên nghìn đồng)</span>
            </template>
            <button type="button" class="text-xs text-red-400 hover:text-red-300 ml-auto" @click="rows.splice(i, 1)">Xóa</button>
        </div>
        <p class="text-xs text-slate-500">{{ hint }} Gói 0đ hiển thị là gói mặc định đi kèm máy. Nhập số âm (ví dụ -300000 hoặc -5%) cho gói rẻ hơn giá niêm yết. Khách chỉ được chọn một gói; giá gói cộng vào giá sản phẩm.</p>
        <template v-for="(msg, key) in errors" :key="key">
            <p v-if="String(key).startsWith(errorKey)" class="text-red-400 text-xs mt-1">{{ msg }}</p>
        </template>
    </div>
</template>
