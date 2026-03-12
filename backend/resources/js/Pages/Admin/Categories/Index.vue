<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ categories: Array });
const flash = usePage().props.flash;

function destroy(id) { if (confirm('Xóa danh mục này?')) router.delete(`/admin/categories/${id}`); }

function flatList(cats, level = 0) {
    let result = [];
    cats.forEach(c => { result.push({ ...c, level }); if (c.children?.length) result.push(...flatList(c.children, level + 1)); });
    return result;
}
const items = flatList(props.categories);
</script>
<template>
<AdminLayout title="Danh mục">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Danh mục sản phẩm</h3>
        <Link href="/admin/categories/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">+ Thêm danh mục</Link>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loại LK</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Thứ tự</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="c in items" :key="c.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900" :style="{ paddingLeft: (16 + c.level * 24) + 'px' }">
                        <span v-if="c.level" class="text-gray-300 mr-1">└</span>{{ c.name }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ c.slug }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ c.component_type?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ c.sort_order }}</td>
                    <td class="px-4 py-3 text-center"><span :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ c.is_active ? 'Hiển thị' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/categories/${c.id}/edit`" class="text-indigo-600 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(c.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!items.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Chưa có danh mục</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
