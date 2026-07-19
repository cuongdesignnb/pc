<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    configuration: Object,
    syncState: Object,
    counts: Object,
    recentErrors: Array,
});

const submit = (path) => router.post(path, {}, { preserveScroll: true });
const formatTime = (value) => value ? new Date(value).toLocaleString('vi-VN') : 'Chưa có';
</script>

<template>
    <AdminLayout title="Tích hợp KIOT">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-100">Đồng bộ KIOT</h3>
                    <p class="text-sm text-slate-400">Sản phẩm từ KIOT và đơn hàng từ website.</p>
                </div>
                <span :class="configuration.enabled ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'" class="rounded-full px-3 py-1 text-xs font-semibold">
                    {{ configuration.enabled ? 'Đang bật' : 'Đang tắt' }}
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Cấu hình</p><p class="mt-1 font-semibold" :class="configuration.configured ? 'text-emerald-300' : 'text-amber-300'">{{ configuration.configured ? 'Đầy đủ' : 'Chưa đầy đủ' }}</p></div>
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Product sync</p><p class="mt-1 font-semibold text-slate-200">{{ configuration.product_sync_enabled ? 'Bật' : 'Tắt' }}</p></div>
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Order sync</p><p class="mt-1 font-semibold text-slate-200">{{ configuration.order_sync_enabled ? 'Bật' : 'Tắt' }}</p></div>
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Client ID</p><p class="mt-1 font-mono text-sm text-slate-200">{{ configuration.client_id }}</p></div>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
                <dl class="grid gap-4 text-sm md:grid-cols-3">
                    <div><dt class="text-slate-500">Base URL</dt><dd class="mt-1 text-slate-200">{{ configuration.base_url || 'Chưa cấu hình' }}</dd></div>
                    <div><dt class="text-slate-500">Lần đồng bộ gần nhất</dt><dd class="mt-1 text-slate-200">{{ formatTime(syncState?.last_completed_at) }}</dd></div>
                    <div><dt class="text-slate-500">Trạng thái</dt><dd class="mt-1 text-slate-200">{{ syncState?.status || 'idle' }}</dd></div>
                    <div><dt class="text-slate-500">Matched</dt><dd class="mt-1 text-slate-200">{{ syncState?.items_matched || 0 }}</dd></div>
                    <div><dt class="text-slate-500">Unmatched</dt><dd class="mt-1 text-slate-200">{{ syncState?.items_unmatched || 0 }}</dd></div>
                    <div><dt class="text-slate-500">Product lỗi</dt><dd class="mt-1 text-slate-200">{{ counts.product_errors }}</dd></div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button @click="submit('/admin/integrations/kiot/dry-run')" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">Product dry-run</button>
                    <button @click="submit('/admin/integrations/kiot/sync')" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">Đồng bộ sản phẩm</button>
                    <button @click="submit('/admin/integrations/kiot/retry')" class="rounded-lg border border-amber-500/40 px-4 py-2 text-sm text-amber-300 hover:bg-amber-500/10">Retry đơn lỗi</button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(value, key) in { 'Đơn đang chờ': counts.orders_pending, 'Đơn retry': counts.orders_retrying, 'Đơn bị từ chối': counts.orders_rejected, 'Dead letter': counts.dead_letter }" :key="key" class="rounded-lg border border-slate-800 bg-slate-900 p-4">
                    <p class="text-xs text-slate-500">{{ key }}</p><p class="mt-1 text-2xl font-bold text-slate-100">{{ value }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
                <div class="border-b border-slate-800 px-5 py-4"><h4 class="font-semibold text-slate-200">Lỗi gần nhất</h4></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-800/40 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Event</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Lỗi</th><th class="px-4 py-3">Attempt</th><th class="px-4 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="event in recentErrors" :key="event.id"><td class="px-4 py-3 text-slate-300">{{ event.event_type }}</td><td class="px-4 py-3 text-slate-300">#{{ event.aggregate_id }}</td><td class="px-4 py-3"><p class="font-mono text-xs text-amber-300">{{ event.last_error_code }}</p><p class="mt-1 max-w-xl truncate text-slate-400">{{ event.last_error_message }}</p></td><td class="px-4 py-3 text-slate-400">{{ event.attempt_count }}</td><td class="px-4 py-3 text-right"><button @click="submit(`/admin/integrations/kiot/events/${event.id}/retry`)" class="text-cyan-400 hover:text-cyan-300">Retry</button></td></tr>
                            <tr v-if="!recentErrors.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có lỗi đồng bộ.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
