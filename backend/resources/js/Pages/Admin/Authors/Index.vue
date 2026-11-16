<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ authors: Object, filters: Object });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');

function applySearch() {
    router.get('/admin/authors', search.value ? { search: search.value } : {}, { preserveState: true, replace: true });
}
function destroy(author) {
    const note = author.posts_count ? ` ${author.posts_count} bài viết sẽ trở về hiển thị tên tài khoản admin.` : '';
    if (confirm(`Xóa tác giả "${author.name}"?${note}`)) router.delete(`/admin/authors/${author.id}`);
}
</script>

<template>
<AdminLayout title="Tác giả">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-200">Tác giả bài viết</h3>
            <p class="text-xs text-slate-500">Chọn tác giả trong form bài viết. Mỗi tác giả có trang riêng /tin-tuc/tac-gia/[slug].</p>
        </div>
        <div class="flex gap-2">
            <form class="flex gap-2" @submit.prevent="applySearch">
                <input v-model="search" type="search" placeholder="Tìm theo tên…" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
            </form>
            <Link href="/admin/authors/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm tác giả</Link>
        </div>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tác giả</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Slug</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Bài viết</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="author in authors.data" :key="author.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-slate-800/60 overflow-hidden flex-shrink-0"><img v-if="author.avatar" :src="author.avatar" :alt="author.name" class="w-full h-full object-cover"></div>
                            <div>
                                <p class="text-sm font-medium text-slate-200">{{ author.name }}</p>
                                <p v-if="author.job_title" class="text-xs text-slate-500">{{ author.job_title }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ author.slug }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ author.posts_count }}</td>
                    <td class="px-4 py-3 text-center"><span :class="author.is_active ? 'bg-green-100 text-emerald-400' : 'bg-slate-800/60 text-slate-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ author.is_active ? 'Hiển thị' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/authors/${author.id}/edit`" class="text-cyan-500 hover:text-cyan-300 text-sm">Sửa</Link>
                        <button type="button" class="text-red-600 hover:text-red-800 text-sm" @click="destroy(author)">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!authors.data?.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có tác giả</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="authors.last_page > 1" class="mt-4 flex flex-wrap gap-1">
        <template v-for="link in authors.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" class="px-3 py-1.5 text-sm rounded-lg border border-slate-700/50" :class="link.active ? 'bg-cyan-600 text-white' : 'text-slate-300'" v-html="link.label" />
            <span v-else class="px-3 py-1.5 text-sm text-slate-600" v-html="link.label" />
        </template>
    </div>
</AdminLayout>
</template>
