<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ types: Array });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa loại linh kiện này?')) router.delete(`/admin/component-types/${id}`); }
</script>
<template>
<AdminLayout title="Loại linh kiện">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-200">Loại linh kiện</h3>
        <Link href="/admin/component-types/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm loại</Link>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tên</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Slug</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Thứ tự</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Bắt buộc</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="t in types" :key="t.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-slate-200">{{ t.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ t.slug }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ t.display_order }}</td>
                    <td class="px-4 py-3 text-center"><span :class="t.is_required ? 'bg-red-100 text-red-700' : 'bg-slate-800/60 text-slate-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ t.is_required ? 'Bắt buộc' : 'Tùy chọn' }}</span></td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ t.products_count }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/component-types/${t.id}/edit`" class="text-cyan-500 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(t.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!types?.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có loại linh kiện</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
