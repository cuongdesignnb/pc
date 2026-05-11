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
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-200">Danh mục sản phẩm</h3>
        <Link href="/admin/categories/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm danh mục</Link>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tên</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Slug</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Loại LK</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Thứ tự</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="c in items" :key="c.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-slate-200" :style="{ paddingLeft: (16 + c.level * 24) + 'px' }">
                        <span v-if="c.level" class="text-slate-500 mr-1">└</span>{{ c.name }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ c.slug }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ c.component_type?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ c.sort_order }}</td>
                    <td class="px-4 py-3 text-center"><span :class="c.is_active ? 'bg-green-100 text-emerald-400' : 'bg-slate-800/60 text-slate-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ c.is_active ? 'Hiển thị' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a :href="`/${c.slug}`" target="_blank" class="inline-flex items-center text-slate-400 hover:text-cyan-400 transition-colors" title="Xem trên website">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                        <Link :href="`/admin/categories/${c.id}/filters`" class="text-emerald-500 hover:text-emerald-300 text-sm" title="Gan bo loc">Bo loc</Link>
                        <Link :href="`/admin/categories/${c.id}/edit`" class="text-cyan-500 hover:text-cyan-300 text-sm">Sua</Link>
                        <button @click="destroy(c.id)" class="text-red-400 hover:text-red-300 text-sm">Xoa</button>
                    </td>
                </tr>
                <tr v-if="!items.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có danh mục</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
