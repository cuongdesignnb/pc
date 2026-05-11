<script setup>
import { computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ filter: Object });
const isEdit = computed(() => !!props.filter);

const form = useForm({
    name: props.filter?.name || '',
    slug: props.filter?.slug || '',
    type: props.filter?.type || 'checkbox',
    match_field: props.filter?.match_field || 'specifications_text',
    sort_order: props.filter?.sort_order ?? 0,
    is_active: props.filter?.is_active ?? true,
    values: props.filter?.values?.map(v => ({ ...v })) || [],
});

function genSlug(str) {
    return str.toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'd')
        .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

function onNameBlur() {
    if (!form.slug) form.slug = genSlug(form.name);
}

// Auto-set match_field when type changes
watch(() => form.type, (t) => {
    if (t === 'price_range') form.match_field = 'price';
});

function addValue() {
    form.values.push({
        id: null, label: '', slug: '', match_value: '',
        price_min: null, price_max: null,
        sort_order: form.values.length, is_active: true,
    });
}

function removeValue(index) {
    form.values.splice(index, 1);
    form.values.forEach((v, i) => v.sort_order = i);
}

function moveValue(index, dir) {
    const newIndex = index + dir;
    if (newIndex < 0 || newIndex >= form.values.length) return;
    [form.values[index], form.values[newIndex]] = [form.values[newIndex], form.values[index]];
    form.values.forEach((v, i) => v.sort_order = i);
}

function onValueLabelBlur(val) {
    if (!val.slug) val.slug = genSlug(val.label);
    if (!val.match_value && form.match_field !== 'price') val.match_value = val.label;
}

function submit() {
    if (isEdit.value) {
        form.put(`/admin/filters/${props.filter.id}`);
    } else {
        form.post('/admin/filters');
    }
}
</script>
<template>
<AdminLayout :title="isEdit ? `Sửa bộ lọc: ${filter.name}` : 'Thêm bộ lọc'">
    <div class="max-w-4xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-slate-200">{{ isEdit ? `Sửa: ${filter.name}` : 'Thêm bộ lọc mới' }}</h3>
            <Link href="/admin/filters" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link>
        </div>
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Thông tin bộ lọc -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Thông tin bộ lọc</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Tên bộ lọc *</label>
                        <input v-model="form.name" @blur="onNameBlur" placeholder="VD: CPU, RAM, Khoảng giá..."
                            class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50">
                        <div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Slug *</label>
                        <input v-model="form.slug" placeholder="cpu, ram, khoang-gia"
                            class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50">
                        <div v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Loại bộ lọc</label>
                        <select v-model="form.type" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                            <option value="checkbox">Checkbox (chọn nhiều)</option>
                            <option value="price_range">Khoảng giá</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Tìm kiếm trong</label>
                        <select v-model="form.match_field" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"
                            :disabled="form.type === 'price_range'">
                            <option value="specifications_text">Thông số KT (specifications_text)</option>
                            <option value="product_name">Tên sản phẩm</option>
                            <option value="brand">Thương hiệu (brand slug)</option>
                            <option value="price">Giá</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">
                            <template v-if="form.match_field === 'specifications_text'">Tìm trong trường thông số kỹ thuật của sản phẩm</template>
                            <template v-else-if="form.match_field === 'product_name'">Tìm trong tên sản phẩm</template>
                            <template v-else-if="form.match_field === 'brand'">Khớp với slug thương hiệu</template>
                            <template v-else>Lọc theo khoảng giá</template>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Thứ tự</label>
                        <input v-model="form.sort_order" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Kích hoạt
                </label>
            </div>

            <!-- Danh sách giá trị -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">
                        Giá trị bộ lọc <span class="text-slate-500 font-normal normal-case">({{ form.values.length }} giá trị)</span>
                    </h4>
                    <button type="button" @click="addValue" class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-medium">
                        + Thêm giá trị
                    </button>
                </div>

                <div v-if="form.values.length" class="border border-slate-800/60 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-800/40">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase w-8">#</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Nhãn hiển thị</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Slug (URL)</th>
                                <th v-if="form.match_field !== 'price'" class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">
                                    Giá trị tìm kiếm
                                </th>
                                <th v-if="form.match_field === 'price' || form.type === 'price_range'" class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Giá min</th>
                                <th v-if="form.match_field === 'price' || form.type === 'price_range'" class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Giá max</th>
                                <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-400 uppercase w-16">Bật</th>
                                <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-400 uppercase w-24">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/40">
                            <tr v-for="(val, idx) in form.values" :key="idx" class="hover:bg-slate-800/40">
                                <td class="px-3 py-2 text-slate-500 text-center">{{ idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <input v-model="val.label" @blur="onValueLabelBlur(val)" placeholder="VD: Intel Core i5"
                                        class="w-full border border-slate-700/50 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-cyan-500/50">
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="val.slug" placeholder="intel-core-i5"
                                        class="w-full border border-slate-700/50 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-cyan-500/50">
                                </td>
                                <td v-if="form.match_field !== 'price'" class="px-3 py-2">
                                    <input v-model="val.match_value" :placeholder="form.match_field === 'brand' ? 'brand-slug' : 'Core i5'"
                                        class="w-full border border-slate-700/50 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-cyan-500/50">
                                </td>
                                <td v-if="form.match_field === 'price' || form.type === 'price_range'" class="px-3 py-2">
                                    <input v-model.number="val.price_min" type="number" placeholder="0"
                                        class="w-full border border-slate-700/50 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-cyan-500/50">
                                </td>
                                <td v-if="form.match_field === 'price' || form.type === 'price_range'" class="px-3 py-2">
                                    <input v-model.number="val.price_max" type="number" placeholder="∞"
                                        class="w-full border border-slate-700/50 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-cyan-500/50">
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input v-model="val.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500">
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" @click="moveValue(idx, -1)" :disabled="idx === 0"
                                            class="p-1 text-slate-500 hover:text-slate-400 disabled:opacity-30" title="Lên">↑</button>
                                        <button type="button" @click="moveValue(idx, 1)" :disabled="idx === form.values.length - 1"
                                            class="p-1 text-slate-500 hover:text-slate-400 disabled:opacity-30" title="Xuống">↓</button>
                                        <button type="button" @click="removeValue(idx)" class="p-1 text-red-400 hover:text-red-600" title="Xóa">✕</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-center py-8 text-slate-500">
                    <p>Chưa có giá trị nào. Nhấn <strong>"+ Thêm giá trị"</strong> để bắt đầu.</p>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <Link href="/admin/filters" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">
                    <span v-if="form.processing">Đang lưu...</span>
                    <span v-else>{{ isEdit ? 'Lưu thay đổi' : 'Tạo bộ lọc' }}</span>
                </button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
