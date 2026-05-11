<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    batches: Object,
});

const statusLabels = {
    pending: 'Cho xu ly',
    processing: 'Dang xu ly',
    completed: 'Hoan thanh',
    failed: 'Loi',
};
const statusColors = {
    pending: 'bg-amber-500/15 text-amber-400 border-amber-500/20',
    processing: 'bg-blue-500/15 text-blue-400 border-blue-500/20',
    completed: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/20',
    failed: 'bg-red-500/15 text-red-400 border-red-500/20',
};

function runBatch(id) {
    if (!confirm('Ban co chac chan muon chay batch nay?')) return;
    router.post(`/admin/ai-articles/${id}/run`, {}, { preserveScroll: true });
}

function deleteBatch(id) {
    if (!confirm('Ban co chac chan muon xoa batch nay?')) return;
    router.delete(`/admin/ai-articles/${id}`, { preserveScroll: true });
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('vi-VN');
}
</script>

<template>
<AdminLayout title="AI Bai viet">
    <div class="max-w-6xl">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-slate-200">AI Bai viet - Tao hang loat</h3>
                <p class="text-xs text-slate-500 mt-1">Tao bai viet tu dong tu danh sach tu khoa bang AI</p>
            </div>
            <Link href="/admin/ai-articles/create" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 text-white text-sm font-semibold rounded-lg hover:from-cyan-600 hover:to-blue-700 transition-all shadow-lg shadow-cyan-500/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tao batch moi
            </Link>
        </div>

        <!-- Table -->
        <div class="bg-slate-900 rounded-xl border border-slate-800/60 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-800/40">
                            <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Ten batch</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Provider</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Tien do</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Trang thai</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Lich hen</th>
                            <th class="px-5 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Tu dong dang</th>
                            <th class="px-5 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Thao tac</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/30">
                        <tr v-for="batch in batches?.data || []" :key="batch.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="px-5 py-3">
                                <Link :href="`/admin/ai-articles/${batch.id}`" class="text-sm font-medium text-cyan-400 hover:text-cyan-300">{{ batch.name }}</Link>
                                <p class="text-xs text-slate-500 mt-0.5">{{ batch.total_items }} tu khoa</p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-xs font-mono text-slate-400 bg-slate-800 px-2 py-1 rounded uppercase">{{ batch.ai_provider }}</span>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-20 h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-gradient-to-r from-cyan-500 to-blue-500 rounded-full transition-all"
                                            :style="{ width: batch.total_items ? ((batch.completed_items / batch.total_items) * 100) + '%' : '0%' }"
                                        ></div>
                                    </div>
                                    <span class="text-xs text-slate-400 tabular-nums">{{ batch.completed_items }}/{{ batch.total_items }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span :class="[statusColors[batch.status], 'inline-flex px-2.5 py-1 text-[11px] font-medium rounded-full border']">
                                    {{ statusLabels[batch.status] }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-center text-xs text-slate-400">{{ fmtDate(batch.schedule_at) }}</td>
                            <td class="px-5 py-3 text-center">
                                <span v-if="batch.auto_publish" class="text-xs text-emerald-400">Co</span>
                                <span v-else class="text-xs text-slate-500">Khong</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        v-if="batch.status === 'pending' || batch.status === 'failed'"
                                        @click="runBatch(batch.id)"
                                        class="p-1.5 text-cyan-400 hover:bg-cyan-500/10 rounded transition-colors"
                                        title="Chay batch"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                    <Link :href="`/admin/ai-articles/${batch.id}`" class="p-1.5 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded transition-colors" title="Chi tiet">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </Link>
                                    <button @click="deleteBatch(batch.id)" class="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded transition-colors" title="Xoa">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!batches?.data?.length">
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-600">Chua co batch nao. Bam "Tao batch moi" de bat dau.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
