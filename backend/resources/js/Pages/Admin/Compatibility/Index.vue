<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ rules: Object, componentTypes: Array, filters: Object });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa quy tắc này?')) router.delete(`/admin/compatibility/${id}`); }
function typeName(id) { return props.componentTypes?.find(t => t.id === id)?.name || '—'; }
const ruleLabel = {
    must_match: 'Khớp chính xác',
    must_fit: 'Phải nằm trong DS',
    must_fit_dimension: 'Kích thước phải vừa',
    must_contain: 'Phải chứa giá trị',
    power_check: 'Kiểm tra công suất',
};
</script>
<template>
<AdminLayout title="Tương thích">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Quy tắc tương thích</h3>
        <Link href="/admin/compatibility/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">+ Thêm quy tắc</Link>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nguồn</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Đích</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loại quy tắc</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Spec key</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="r in rules.data" :key="r.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.source_type?.name || typeName(r.source_type_id) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ r.target_type?.name || typeName(r.target_type_id) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ ruleLabel[r.rule_type] || r.rule_type }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ r.source_spec_key }} → {{ r.target_spec_key }}</td>
                    <td class="px-4 py-3 text-center"><span :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ r.is_active ? 'Bật' : 'Tắt' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/compatibility/${r.id}/edit`" class="text-indigo-600 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(r.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!rules.data?.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Chưa có quy tắc</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
