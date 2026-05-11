<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ categories: Array });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa danh mục này?')) router.delete(`/admin/post-categories/${id}`); }
</script>
<template>
<AdminLayout title="Danh mục bài viết">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-slate-200">Danh mục bài viết</h3>
        <Link href="/admin/post-categories/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm danh mục</Link>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tên danh mục</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Slug</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Số bài viết</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Thứ tự</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="cat in categories" :key="cat.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-slate-200">{{ cat.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ cat.slug }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ cat.posts_count }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ cat.sort_order }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/post-categories/${cat.id}/edit`" class="text-cyan-500 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(cat.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!categories?.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có danh mục</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
