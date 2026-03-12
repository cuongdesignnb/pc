<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ rule: Object, componentTypes: Array });
const r = props.rule;
const form = useForm({ source_type_id: r.source_type_id, target_type_id: r.target_type_id, source_spec_key: r.source_spec_key, target_spec_key: r.target_spec_key, rule_type: r.rule_type, allowed_values: r.allowed_values || [], power_headroom: r.power_headroom || '', message: r.message || '', is_active: r.is_active ?? true });
const ruleTypes = [
    { value: 'must_match', label: 'Khớp chính xác', desc: 'Giá trị 2 bên phải giống nhau. VD: CPU socket = Mainboard socket' },
    { value: 'must_fit', label: 'Phải nằm trong danh sách', desc: 'Giá trị nguồn phải nằm trong danh sách cho phép của đích. VD: Mainboard ATX chỉ vừa Case ATX/E-ATX' },
    { value: 'must_fit_dimension', label: 'Kích thước phải vừa', desc: 'Giá trị nguồn phải ≤ giá trị đích. VD: Chiều dài GPU ≤ khoang chứa GPU của Case' },
    { value: 'must_contain', label: 'Phải chứa giá trị', desc: 'Giá trị nguồn phải chứa giá trị đích. VD: Tản nhiệt hỗ trợ socket phải chứa socket CPU' },
    { value: 'power_check', label: 'Kiểm tra công suất', desc: 'Tổng TDP các linh kiện + dư phải ≤ công suất nguồn PSU' },
];
const sourceSpecKeys = computed(() => {
    const ct = props.componentTypes.find(t => t.id == form.source_type_id);
    return ct?.specification_keys || [];
});
const targetSpecKeys = computed(() => {
    const ct = props.componentTypes.find(t => t.id == form.target_type_id);
    return ct?.specification_keys || [];
});
function submit() { form.put(`/admin/compatibility/${r.id}`); }
</script>
<template>
<AdminLayout title="Sửa quy tắc tương thích">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Sửa quy tắc #{{ rule.id }}</h3><Link href="/admin/compatibility" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Loại nguồn *</label><select v-model="form.source_type_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="t in componentTypes" :value="t.id">{{ t.name }}</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Loại đích *</label><select v-model="form.target_type_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="t in componentTypes" :value="t.id">{{ t.name }}</option></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Thông số nguồn *</label><select v-model="form.source_spec_key" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="sk in sourceSpecKeys" :value="sk.key">{{ sk.label }} ({{ sk.key }})</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Thông số đích *</label><select v-model="form.target_spec_key" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="sk in targetSpecKeys" :value="sk.key">{{ sk.label }} ({{ sk.key }})</option></select></div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Loại quy tắc *</label>
                <select v-model="form.rule_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option v-for="rt in ruleTypes" :value="rt.value">{{ rt.label }}</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">{{ ruleTypes.find(rt => rt.value === form.rule_type)?.desc }}</p>
            </div>
            <div v-if="form.rule_type === 'power_check'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Công suất dự phòng (W)</label>
                <input v-model="form.power_headroom" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="VD: 150">
                <p class="text-xs text-gray-500 mt-1">Số watt dự phòng thêm để đảm bảo nguồn đủ mạnh. Khuyến nghị: 100-200W</p>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Thông báo lỗi</label><input v-model="form.message" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="VD: CPU và Mainboard không cùng socket"><p class="text-xs text-gray-500 mt-1">Thông báo hiển thị cho khách khi 2 linh kiện không tương thích</p></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Kích hoạt</label>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/compatibility" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
