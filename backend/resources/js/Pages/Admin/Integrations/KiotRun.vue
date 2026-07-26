<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ run: Object, conflicts: Array });
const formatTime = (value) => value ? new Date(value).toLocaleString('vi-VN') : '—';
</script>

<template>
    <AdminLayout :title="`KIOT sync #${run.id}`">
        <div class="space-y-6">
            <Link href="/admin/integrations/kiot" class="text-sm text-cyan-400">← Quay lại tích hợp KIOT</Link>

            <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-slate-100">Product sync #{{ run.id }}</h3>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-300">{{ run.status }}</span>
                </div>
                <dl class="mt-5 grid gap-4 text-sm md:grid-cols-3">
                    <div><dt class="text-slate-500">Mode</dt><dd class="mt-1 text-slate-200">{{ run.mode }}</dd></div>
                    <div><dt class="text-slate-500">Bắt đầu</dt><dd class="mt-1 text-slate-200">{{ formatTime(run.started_at) }}</dd></div>
                    <div><dt class="text-slate-500">Hoàn tất</dt><dd class="mt-1 text-slate-200">{{ formatTime(run.completed_at || run.failed_at) }}</dd></div>
                    <div><dt class="text-slate-500">Pages</dt><dd class="mt-1 text-slate-200">{{ run.pages_processed }}</dd></div>
                    <div><dt class="text-slate-500">Remote</dt><dd class="mt-1 text-slate-200">{{ run.remote_processed }}</dd></div>
                    <div><dt class="text-slate-500">Created / Updated / Unchanged</dt><dd class="mt-1 text-slate-200">{{ run.created }} / {{ run.updated }} / {{ run.unchanged }}</dd></div>
                </dl>
                <div v-if="run.error_code" class="mt-5 rounded-md border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"><p class="font-mono">{{ run.error_code }}</p><p>{{ run.error_message }}</p></div>
            </section>

            <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
                <h4 class="font-semibold text-slate-100">Báo cáo</h4>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(value, key) in (run.totals_json || {})" :key="key" class="rounded bg-slate-950/60 p-3"><dt class="font-mono text-xs text-slate-500">{{ key }}</dt><dd class="mt-1 break-words text-slate-200">{{ typeof value === 'object' ? JSON.stringify(value) : value }}</dd></div>
                </dl>
            </section>

            <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
                <h4 class="font-semibold text-slate-100">Warnings</h4>
                <pre class="mt-4 overflow-auto whitespace-pre-wrap rounded bg-slate-950 p-4 text-xs text-amber-200">{{ JSON.stringify(run.warnings_json || [], null, 2) }}</pre>
            </section>

            <section v-if="conflicts?.length" class="rounded-lg border border-amber-500/30 bg-slate-900 p-5">
                <h4 class="font-semibold text-amber-200">Conflicts</h4>
                <pre class="mt-4 overflow-auto whitespace-pre-wrap rounded bg-slate-950 p-4 text-xs text-slate-300">{{ JSON.stringify(conflicts, null, 2) }}</pre>
            </section>
        </div>
    </AdminLayout>
</template>
