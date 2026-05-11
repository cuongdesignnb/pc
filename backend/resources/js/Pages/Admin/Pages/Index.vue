<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ pages: Object });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa trang?')) router.delete(`/admin/pages/${id}`); }
</script>
<template>
<AdminLayout title="Trang tĩnh">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-200">Trang tĩnh</h3>
        <Link href="/admin/pages/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm trang</Link>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tiêu đề</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Slug</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="pg in pages.data" :key="pg.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-slate-200">{{ pg.title }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">/{{ pg.slug }}</td>
                    <td class="px-4 py-3 text-center"><span :class="pg.is_active ? 'bg-green-100 text-emerald-400' : 'bg-slate-800/60 text-slate-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ pg.is_active ? 'Hiện' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/pages/${pg.id}/edit`" class="text-cyan-500 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(pg.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!pages.data?.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Chưa có trang</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
