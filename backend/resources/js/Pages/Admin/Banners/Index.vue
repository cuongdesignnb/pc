<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ banners: Object });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa banner?')) router.delete(`/admin/banners/${id}`); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
const posLabel = { hero: 'Hero', sidebar: 'Sidebar', footer: 'Footer', popup: 'Popup', category: 'Danh mục' };
</script>
<template>
<AdminLayout title="Banner">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-200">Banner</h3>
        <Link href="/admin/banners/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm banner</Link>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Ảnh</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tiêu đề</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Vị trí</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Thứ tự</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Hiệu lực</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="b in banners.data" :key="b.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3"><img v-if="b.image" :src="b.image" class="w-20 h-12 object-cover rounded" /><span v-else class="text-slate-500 text-xs">—</span></td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-200">{{ b.title || '(Không tiêu đề)' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ posLabel[b.position] || b.position }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ b.sort_order }}</td>
                    <td class="px-4 py-3 text-center"><span :class="b.is_active ? 'bg-green-100 text-emerald-400' : 'bg-slate-800/60 text-slate-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ b.is_active ? 'Hiện' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ formatDate(b.starts_at) }} → {{ formatDate(b.ends_at) }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/banners/${b.id}/edit`" class="text-cyan-500 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(b.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!banners.data?.length"><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có banner</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
