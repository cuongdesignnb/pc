<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ posts: Object, filters: Object, categories: Array });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const filter = ref(props.filters?.status || '');

let timer;
watch([search, filter], () => { clearTimeout(timer); timer = setTimeout(() => router.get('/admin/posts', { search: search.value || undefined, status: filter.value || undefined }, { preserveState: true, replace: true }), 400); });

function destroy(id) { if (confirm('Xóa bài viết?')) router.delete(`/admin/posts/${id}`); }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
function statusLabel(s) { return s === 'published' ? 'Đã đăng' : s === 'draft' ? 'Nháp' : 'Lưu trữ'; }
function statusClass(s) { return s === 'published' ? 'bg-green-100 text-green-700' : s === 'draft' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'; }
</script>
<template>
<AdminLayout title="Bài viết">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <input v-model="search" placeholder="Tìm bài viết..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48">
            <select v-model="filter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tất cả</option><option value="published">Đã xuất bản</option><option value="draft">Nháp</option><option value="archived">Lưu trữ</option>
            </select>
        </div>
        <Link href="/admin/posts/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">+ Viết bài</Link>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tiêu đề</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Danh mục</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Lượt xem</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ngày đăng</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="p in posts.data" :key="p.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ p.title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ p.category?.name || '—' }}</td>
                    <td class="px-4 py-3 text-center"><span :class="statusClass(p.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(p.status) }}</span></td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ p.view_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(p.published_at || p.created_at) }}</td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/posts/${p.id}/edit`" class="text-indigo-600 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(p.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!posts.data?.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">Chưa có bài viết</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
