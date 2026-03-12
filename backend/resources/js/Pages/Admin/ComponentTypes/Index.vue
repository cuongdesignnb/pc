<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ types: Array });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa loại linh kiện này?')) router.delete(`/admin/component-types/${id}`); }
</script>
<template>
<AdminLayout title="Loại linh kiện">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Loại linh kiện</h3>
        <Link href="/admin/component-types/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">+ Thêm loại</Link>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Thứ tự</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Bắt buộc</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="t in types" :key="t.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ t.name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ t.slug }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ t.display_order }}</td>
                    <td class="px-4 py-3 text-center"><span :class="t.is_required ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ t.is_required ? 'Bắt buộc' : 'Tùy chọn' }}</span></td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ t.products_count }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/component-types/${t.id}/edit`" class="text-indigo-600 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(t.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!types?.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Chưa có loại linh kiện</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
