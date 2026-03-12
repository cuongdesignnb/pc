<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ brands: Array });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa thương hiệu này?')) router.delete(`/admin/brands/${id}`); }
</script>
<template>
<AdminLayout title="Thương hiệu">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Thương hiệu</h3>
        <Link href="/admin/brands/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">+ Thêm thương hiệu</Link>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thương hiệu</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="b in brands" :key="b.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3"><div class="flex items-center gap-3"><div class="w-8 h-8 bg-gray-100 rounded overflow-hidden flex-shrink-0"><img v-if="b.logo" :src="b.logo" class="w-full h-full object-contain"></div><span class="text-sm font-medium text-gray-900">{{ b.name }}</span></div></td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ b.slug }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ b.products_count }}</td>
                    <td class="px-4 py-3 text-center"><span :class="b.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ b.is_active ? 'Hiển thị' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/brands/${b.id}/edit`" class="text-indigo-600 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(b.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!brands?.length"><td colspan="5" class="px-4 py-8 text-center text-gray-400">Chưa có thương hiệu</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
