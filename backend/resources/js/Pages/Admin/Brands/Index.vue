<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ brands: Array });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa thương hiệu này?')) router.delete(`/admin/brands/${id}`); }
</script>
<template>
<AdminLayout title="Thương hiệu">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-200">Thương hiệu</h3>
        <Link href="/admin/brands/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm thương hiệu</Link>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Thương hiệu</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Slug</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="b in brands" :key="b.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3"><div class="flex items-center gap-3"><div class="w-8 h-8 bg-slate-800/60 rounded overflow-hidden flex-shrink-0"><img v-if="b.logo" :src="b.logo" class="w-full h-full object-contain"></div><span class="text-sm font-medium text-slate-200">{{ b.name }}</span></div></td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ b.slug }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ b.products_count }}</td>
                    <td class="px-4 py-3 text-center"><span :class="b.is_active ? 'bg-green-100 text-emerald-400' : 'bg-slate-800/60 text-slate-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ b.is_active ? 'Hiển thị' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/brands/${b.id}/edit`" class="text-cyan-500 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(b.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!brands?.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có thương hiệu</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
