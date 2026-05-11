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
function statusClass(s) { return s === 'published' ? 'bg-green-100 text-emerald-400' : s === 'draft' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-800/60 text-slate-300'; }

// Import/Export
const showImport = ref(false);
const importFile = ref(null);
const importing = ref(false);
function onFileChange(e) { importFile.value = e.target.files[0]; }
function submitImport() {
    if (!importFile.value) return;
    importing.value = true;
    router.post('/admin/posts-import', { file: importFile.value }, {
        forceFormData: true,
        onFinish: () => { importing.value = false; showImport.value = false; importFile.value = null; }
    });
}
function exportUrl() {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (filter.value) params.set('status', filter.value);
    return '/admin/posts-export?' + params.toString();
}
</script>
<template>
<AdminLayout title="Bài viết">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <input v-model="search" placeholder="Tìm bài viết..." class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm w-48">
            <select v-model="filter" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <option value="">Tất cả</option><option value="published">Đã xuất bản</option><option value="draft">Nháp</option><option value="archived">Lưu trữ</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <Link href="/admin/posts/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Viết bài</Link>
            <a :href="exportUrl()" class="px-3 py-2 bg-emerald-600/80 text-white rounded-lg hover:bg-emerald-600 text-sm font-medium inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Xuất Excel
            </a>
            <button @click="showImport = true" class="px-3 py-2 bg-amber-600/80 text-white rounded-lg hover:bg-amber-600 text-sm font-medium inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Nhập Excel
            </button>
        </div>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tiêu đề</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Danh mục</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Lượt xem</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Ngày đăng</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="p in posts.data" :key="p.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-slate-200">{{ p.title }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ p.category?.name || '—' }}</td>
                    <td class="px-4 py-3 text-center"><span :class="statusClass(p.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel(p.status) }}</span></td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ p.view_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ formatDate(p.published_at || p.created_at) }}</td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a :href="`/tin-tuc/${p.slug}`" target="_blank" class="inline-flex items-center text-slate-400 hover:text-cyan-400 transition-colors" title="Xem trên website">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                        <Link :href="`/admin/posts/${p.id}/edit`" class="text-cyan-500 hover:text-cyan-300 text-sm">Sua</Link>
                        <button @click="destroy(p.id)" class="text-red-400 hover:text-red-300 text-sm">Xoa</button>
                    </td>
                </tr>
                <tr v-if="!posts.data?.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có bài viết</td></tr>
            </tbody>
        </table>
    </div>
    <!-- Import Modal -->
    <Teleport to="body">
        <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="showImport = false"></div>
            <div class="relative bg-slate-900 rounded-xl border border-slate-700/50 p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-lg font-semibold text-slate-200 mb-4">Nhập bài viết từ CSV</h3>
                <p class="text-sm text-slate-400 mb-4">File CSV cần có header: ID, Tiêu đề, Slug, Danh mục, Tóm tắt, Trạng thái, ...</p>
                <p class="text-xs text-slate-500 mb-4">Tip: Xuất Excel trước để lấy mẫu file, sửa rồi import lại.</p>
                <input type="file" accept=".csv,.txt" @change="onFileChange" class="block w-full text-sm text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-cyan-600 file:text-white hover:file:bg-cyan-700 mb-4">
                <div class="flex justify-end gap-2">
                    <button @click="showImport = false" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Hủy</button>
                    <button @click="submitImport" :disabled="!importFile || importing" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-medium hover:bg-cyan-700 disabled:opacity-50">
                        {{ importing ? 'Đang import...' : 'Import' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</AdminLayout>
</template>
