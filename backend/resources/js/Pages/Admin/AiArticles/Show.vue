<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    batch: Object,
});

const statusLabels = { pending: 'Cho xu ly', generating: 'Dang tao', completed: 'Hoan thanh', failed: 'Loi' };
const statusColors = {
    pending: 'bg-amber-500/15 text-amber-400 border-amber-500/20',
    generating: 'bg-blue-500/15 text-blue-400 border-blue-500/20',
    completed: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/20',
    failed: 'bg-red-500/15 text-red-400 border-red-500/20',
};
const batchStatusLabels = { pending: 'Cho xu ly', processing: 'Dang xu ly', completed: 'Hoan thanh', failed: 'Loi' };

function runBatch() {
    if (!confirm('Chay batch nay?')) return;
    router.post(`/admin/ai-articles/${props.batch.id}/run`, {}, { preserveScroll: true });
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('vi-VN');
}

const progress = ((props.batch?.completed_items || 0) / (props.batch?.total_items || 1) * 100).toFixed(0);
</script>

<template>
<AdminLayout title="Chi tiet batch">
    <div class="max-w-5xl">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
            <Link href="/admin/ai-articles" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <div class="flex-1">
                <h3 class="text-lg font-bold text-slate-200">{{ batch.name }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">{{ batch.total_items }} tu khoa &middot; {{ batch.ai_provider.toUpperCase() }}</p>
            </div>
            <button
                v-if="batch.status === 'pending' || batch.status === 'failed'"
                @click="runBatch"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-sm font-semibold rounded-lg hover:from-cyan-600 hover:to-blue-700 transition-all shadow-lg shadow-cyan-500/20"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                Chay batch
            </button>
        </div>

        <!-- Progress bar -->
        <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-5 mb-6">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-slate-400">Tien do: {{ batch.completed_items }}/{{ batch.total_items }}</span>
                <span :class="[statusColors[batch.status] || '', 'inline-flex px-2.5 py-1 text-[11px] font-medium rounded-full border']">
                    {{ batchStatusLabels[batch.status] }}
                </span>
            </div>
            <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
                <div
                    class="h-full bg-gradient-to-r from-cyan-500 to-blue-500 rounded-full transition-all duration-500"
                    :style="{ width: progress + '%' }"
                ></div>
            </div>
        </div>

        <!-- Items table -->
        <div class="bg-slate-900 rounded-xl border border-slate-800/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-800/40">
                <h4 class="text-sm font-semibold text-slate-200">Danh sach tu khoa</h4>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-800/40">
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-8">#</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Tu khoa</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Trang thai</th>
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Bai viet</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Thoi gian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/30">
                        <tr v-for="(item, i) in batch.items" :key="item.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="px-5 py-3 text-sm text-slate-500">{{ i + 1 }}</td>
                            <td class="px-5 py-3 text-sm text-slate-300 font-medium">{{ item.keyword }}</td>
                            <td class="px-5 py-3 text-center">
                                <span :class="[statusColors[item.status], 'inline-flex px-2.5 py-1 text-[11px] font-medium rounded-full border']">
                                    {{ statusLabels[item.status] }}
                                </span>
                                <p v-if="item.error_message" class="text-xs text-red-400/70 mt-1 max-w-[200px] truncate" :title="item.error_message">{{ item.error_message }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <Link v-if="item.post" :href="`/admin/posts/${item.post.id}/edit`" class="text-sm text-cyan-400 hover:text-cyan-300">{{ item.post.title }}</Link>
                                <span v-else class="text-xs text-slate-600">—</span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 text-right">{{ fmtDate(item.generated_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
