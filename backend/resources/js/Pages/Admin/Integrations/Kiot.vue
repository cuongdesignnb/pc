<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    configuration: Object,
    syncState: Object,
    counts: Object,
    recentErrors: Array,
    history: Array,
    skuPreview: Object,
});

const page = usePage();
const activeTab = ref('pairing');
const pairForm = useForm({
    base_url: props.configuration.base_url || '',
    reference: '',
    pairing_code: '',
    replace_existing_credentials: false,
});
const manualForm = useForm({
    base_url: props.configuration.base_url || '',
    client_id: props.configuration.client_id || 'pc-website',
    secret: '',
    api_version: props.configuration.api_version || 'v1',
});
const flagsForm = useForm({
    is_enabled: props.configuration.enabled,
    product_sync_enabled: props.configuration.product_sync_enabled,
    order_sync_enabled: props.configuration.order_sync_enabled,
    confirm_order_sync: false,
});
const skuForm = useForm({ sku: '' });
const disconnectForm = useForm({ confirm_disconnect: false });

watch(() => props.configuration, (configuration) => {
    flagsForm.is_enabled = configuration.enabled;
    flagsForm.product_sync_enabled = configuration.product_sync_enabled;
    flagsForm.order_sync_enabled = configuration.order_sync_enabled;
}, { deep: true });

watch(() => flagsForm.is_enabled, (enabled) => {
    if (!enabled) {
        flagsForm.product_sync_enabled = false;
        flagsForm.order_sync_enabled = false;
    }
});

const canPilot = computed(() => props.configuration.configured && props.configuration.connected);
const canProductWrite = computed(() => props.configuration.enabled && props.configuration.product_sync_enabled);
const canOrderRetry = computed(() => props.configuration.enabled && props.configuration.order_sync_enabled);
const tabs = [
    ['pairing', 'Ghép nối tự động'],
    ['manual', 'Cấu hình thủ công'],
    ['environment', 'Nhập từ môi trường'],
];

const submitPair = () => pairForm.post('/admin/integrations/kiot/pair', {
    preserveScroll: true,
    onFinish: () => pairForm.reset('pairing_code'),
});
const submitManual = () => manualForm.post('/admin/integrations/kiot/manual', {
    preserveScroll: true,
    onFinish: () => manualForm.reset('secret'),
});
const submitFlags = () => flagsForm.patch('/admin/integrations/kiot/flags', { preserveScroll: true });
const submit = (path, data = {}) => router.post(path, data, { preserveScroll: true });
const formatTime = (value) => value ? new Date(value).toLocaleString('vi-VN') : 'Chưa có';
const capabilityLabel = (enabled) => enabled ? 'Có' : 'Không';
</script>

<template>
    <AdminLayout title="Tích hợp KIOT">
        <div class="space-y-6">
            <div v-if="page.props.flash?.success" role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.errors?.kiot" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                {{ page.props.errors.kiot }}
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-100">Đồng bộ KIOT</h3>
                    <p class="text-sm text-slate-400">Sản phẩm từ KIOT và đơn hàng từ website.</p>
                </div>
                <span :class="configuration.connected ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'" class="rounded-full px-3 py-1 text-xs font-semibold">
                    {{ configuration.connected ? 'Đã kết nối' : 'Chưa kết nối' }}
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Cấu hình</p><p class="mt-1 font-semibold" :class="configuration.configured ? 'text-emerald-300' : 'text-amber-300'">{{ configuration.configured ? 'Đầy đủ' : 'Chưa đầy đủ' }}</p></div>
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Product sync</p><p class="mt-1 font-semibold text-slate-200">{{ configuration.product_sync_enabled ? 'Bật' : 'Tắt' }}</p></div>
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Order sync</p><p class="mt-1 font-semibold text-slate-200">{{ configuration.order_sync_enabled ? 'Bật' : 'Tắt' }}</p></div>
                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Client ID</p><p class="mt-1 break-all font-mono text-sm text-slate-200">{{ configuration.client_id || 'Chưa có' }}</p></div>
            </div>

            <section class="rounded-lg border border-slate-800 bg-slate-900 p-5" aria-labelledby="connection-overview-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h4 id="connection-overview-title" class="font-semibold text-slate-100">Tổng quan kết nối</h4>
                    <button :disabled="!configuration.configured" @click="submit('/admin/integrations/kiot/test-connection')" class="rounded-lg border border-cyan-500/40 px-4 py-2 text-sm text-cyan-300 disabled:cursor-not-allowed disabled:opacity-40">Kiểm tra kết nối</button>
                </div>
                <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div><dt class="text-slate-500">Trạng thái</dt><dd class="mt-1 text-slate-200">{{ configuration.connection_status }}</dd></div>
                    <div><dt class="text-slate-500">Nguồn cấu hình</dt><dd class="mt-1 text-slate-200">{{ configuration.source }}</dd></div>
                    <div><dt class="text-slate-500">API version</dt><dd class="mt-1 text-slate-200">{{ configuration.api_version }}</dd></div>
                    <div><dt class="text-slate-500">Fingerprint</dt><dd class="mt-1 font-mono text-slate-200">{{ configuration.secret_fingerprint || 'Chưa có' }}</dd></div>
                    <div class="md:col-span-2"><dt class="text-slate-500">Base URL</dt><dd class="mt-1 break-all text-slate-200">{{ configuration.base_url || 'Chưa cấu hình' }}</dd></div>
                    <div><dt class="text-slate-500">Lần kiểm tra</dt><dd class="mt-1 text-slate-200">{{ formatTime(configuration.last_tested_at) }}</dd></div>
                    <div><dt class="text-slate-500">Lần kết nối</dt><dd class="mt-1 text-slate-200">{{ formatTime(configuration.last_connected_at) }}</dd></div>
                </dl>
                <div v-if="configuration.last_error_code" class="mt-4 rounded-md border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-200">
                    <p class="font-mono">{{ configuration.last_error_code }}</p>
                    <p class="mt-1">{{ configuration.last_error_message }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900" aria-labelledby="connection-wizard-title">
                <div class="border-b border-slate-800 px-5 py-4">
                    <h4 id="connection-wizard-title" class="font-semibold text-slate-100">Trình hướng dẫn kết nối</h4>
                    <p class="mt-1 text-sm text-slate-400">Credential được mã hoá và các cờ luôn giữ OFF sau khi lưu.</p>
                </div>
                <div class="flex overflow-x-auto border-b border-slate-800 px-3" role="tablist">
                    <button v-for="tab in tabs" :key="tab[0]" type="button" role="tab" :aria-selected="activeTab === tab[0]" @click="activeTab = tab[0]" :class="activeTab === tab[0] ? 'border-cyan-400 text-cyan-300' : 'border-transparent text-slate-400'" class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium">
                        {{ tab[1] }}
                    </button>
                </div>

                <form v-if="activeTab === 'pairing'" @submit.prevent="submitPair" class="grid gap-4 p-5 md:grid-cols-2">
                    <label class="text-sm text-slate-300">KIOT URL
                        <input v-model="pairForm.base_url" required type="url" autocomplete="url" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" placeholder="https://kiot.example.com">
                    </label>
                    <label class="text-sm text-slate-300">Website URL
                        <input :value="configuration.website_url" readonly class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-950/60 px-3 py-2 text-slate-400">
                    </label>
                    <label class="text-sm text-slate-300">Reference
                        <input v-model="pairForm.reference" required autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                    </label>
                    <label class="text-sm text-slate-300">Pairing code
                        <input v-model="pairForm.pairing_code" required type="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                    </label>
                    <label v-if="configuration.database_history" class="flex items-start gap-2 text-sm text-amber-200 md:col-span-2">
                        <input v-model="pairForm.replace_existing_credentials" type="checkbox" class="mt-1">
                        Tôi xác nhận thay thế credential hiện tại và tắt toàn bộ cờ tích hợp.
                    </label>
                    <div class="md:col-span-2">
                        <button :disabled="pairForm.processing || (configuration.database_history && !pairForm.replace_existing_credentials)" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-40">
                            {{ pairForm.processing ? 'Đang ghép nối...' : 'Ghép nối và kiểm tra' }}
                        </button>
                    </div>
                </form>

                <form v-else-if="activeTab === 'manual'" @submit.prevent="submitManual" class="grid gap-4 p-5 md:grid-cols-2">
                    <label class="text-sm text-slate-300">KIOT URL
                        <input v-model="manualForm.base_url" required type="url" autocomplete="url" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                    </label>
                    <label class="text-sm text-slate-300">Client ID
                        <input v-model="manualForm.client_id" required autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                    </label>
                    <label class="text-sm text-slate-300">Secret
                        <input v-model="manualForm.secret" :required="!configuration.database_history" type="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" :placeholder="configuration.database_history ? 'Để trống để giữ secret hiện tại' : 'Nhập secret'">
                    </label>
                    <label class="text-sm text-slate-300">API version
                        <select v-model="manualForm.api_version" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100"><option value="v1">v1</option></select>
                    </label>
                    <div class="md:col-span-2">
                        <button :disabled="manualForm.processing" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-40">{{ manualForm.processing ? 'Đang lưu...' : 'Lưu cấu hình thủ công' }}</button>
                    </div>
                </form>

                <div v-else class="p-5">
                    <p class="text-sm text-slate-400">Chỉ khả dụng khi chưa từng có cấu hình database và cấu hình môi trường đầy đủ.</p>
                    <button :disabled="!configuration.environment_import_available" @click="submit('/admin/integrations/kiot/import-environment')" class="mt-4 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-40">Nhập cấu hình hiện tại từ môi trường</button>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-lg border border-slate-800 bg-slate-900 p-5" aria-labelledby="capabilities-title">
                    <h4 id="capabilities-title" class="font-semibold text-slate-100">Capabilities</h4>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-md bg-slate-950/60 p-3"><dt class="text-slate-500">Products</dt><dd class="mt-1 text-slate-200">{{ capabilityLabel(configuration.capabilities?.products) }}</dd></div>
                        <div class="rounded-md bg-slate-950/60 p-3"><dt class="text-slate-500">Orders</dt><dd class="mt-1 text-slate-200">{{ capabilityLabel(configuration.capabilities?.orders) }}</dd></div>
                        <div class="rounded-md bg-slate-950/60 p-3"><dt class="text-slate-500">Price books</dt><dd class="mt-1 text-slate-400">Chưa hỗ trợ trong Phase hiện tại</dd></div>
                        <div class="rounded-md bg-slate-950/60 p-3"><dt class="text-slate-500">Google Sheets</dt><dd class="mt-1 text-slate-400">Chưa hỗ trợ trong Phase hiện tại</dd></div>
                    </dl>
                </section>

                <form @submit.prevent="submitFlags" class="rounded-lg border border-slate-800 bg-slate-900 p-5" aria-labelledby="flags-title">
                    <h4 id="flags-title" class="font-semibold text-slate-100">Feature flags</h4>
                    <div class="mt-4 space-y-4 text-sm">
                        <label class="flex items-center justify-between gap-4 text-slate-300"><span>Tích hợp chính</span><input v-model="flagsForm.is_enabled" :disabled="!configuration.connected" type="checkbox"></label>
                        <label class="flex items-center justify-between gap-4 text-slate-300"><span>Đồng bộ sản phẩm</span><input v-model="flagsForm.product_sync_enabled" :disabled="!flagsForm.is_enabled || !configuration.capabilities?.products" type="checkbox"></label>
                        <label class="flex items-center justify-between gap-4 text-slate-300"><span>Đồng bộ đơn hàng</span><input v-model="flagsForm.order_sync_enabled" :disabled="!flagsForm.is_enabled || !configuration.capabilities?.orders" type="checkbox"></label>
                        <div v-if="flagsForm.order_sync_enabled && !configuration.order_sync_enabled" class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-amber-200">
                            <p>Bật Order Sync sẽ cho phép website tạo và huỷ đơn tại KIOT.</p>
                            <label class="mt-2 flex items-center gap-2"><input v-model="flagsForm.confirm_order_sync" type="checkbox"> Tôi xác nhận bật Order Sync.</label>
                        </div>
                    </div>
                    <button :disabled="flagsForm.processing || (flagsForm.order_sync_enabled && !configuration.order_sync_enabled && !flagsForm.confirm_order_sync)" class="mt-5 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-40">Lưu feature flags</button>
                </form>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
                <h4 class="mb-4 font-semibold text-slate-100">Product pilot và trạng thái đồng bộ</h4>
                <dl class="grid gap-4 text-sm md:grid-cols-3">
                    <div><dt class="text-slate-500">Base URL</dt><dd class="mt-1 text-slate-200">{{ configuration.base_url || 'Chưa cấu hình' }}</dd></div>
                    <div><dt class="text-slate-500">Lần đồng bộ gần nhất</dt><dd class="mt-1 text-slate-200">{{ formatTime(syncState?.last_completed_at) }}</dd></div>
                    <div><dt class="text-slate-500">Trạng thái</dt><dd class="mt-1 text-slate-200">{{ syncState?.status || 'idle' }}</dd></div>
                    <div><dt class="text-slate-500">Matched</dt><dd class="mt-1 text-slate-200">{{ syncState?.items_matched || 0 }}</dd></div>
                    <div><dt class="text-slate-500">Unmatched</dt><dd class="mt-1 text-slate-200">{{ syncState?.items_unmatched || 0 }}</dd></div>
                    <div><dt class="text-slate-500">Product lỗi</dt><dd class="mt-1 text-slate-200">{{ counts.product_errors }}</dd></div>
                    <div><dt class="text-slate-500">Dữ liệu tồn đã cũ</dt><dd class="mt-1" :class="counts.products_stale ? 'text-amber-300' : 'text-emerald-300'">{{ counts.products_stale }}</dd></div>
                </dl>
                <p v-if="counts.products_stale" class="mt-4 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
                    Storefront vẫn đọc cache gần nhất; KIOT tiếp tục là cổng kiểm tra tồn cuối khi checkout. Hãy kiểm tra product sync trước khi bật integration.
                </p>
                <div class="mt-5 grid gap-3 md:grid-cols-[1fr_auto_auto]">
                    <label class="sr-only" for="kiot-sku">SKU cần kiểm tra</label>
                    <input id="kiot-sku" v-model="skuForm.sku" placeholder="Nhập chính xác SKU" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
                    <button :disabled="!canPilot || !skuForm.sku || skuForm.processing" @click="skuForm.post('/admin/integrations/kiot/test-one', { preserveScroll: true })" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:cursor-not-allowed disabled:opacity-40">Test one SKU</button>
                    <button :disabled="!canProductWrite || !skuForm.sku || skuForm.processing" @click="skuForm.post('/admin/integrations/kiot/sync-one', { preserveScroll: true })" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-40">Sync one SKU</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-3">
                    <button :disabled="!canPilot" @click="submit('/admin/integrations/kiot/dry-run')" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:cursor-not-allowed disabled:opacity-40">Product dry-run</button>
                    <button :disabled="!canProductWrite" @click="submit('/admin/integrations/kiot/sync')" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-40">Full product sync</button>
                    <button :disabled="!canOrderRetry" @click="submit('/admin/integrations/kiot/retry')" class="rounded-lg border border-amber-500/40 px-4 py-2 text-sm text-amber-300 disabled:cursor-not-allowed disabled:opacity-40">Retry đơn lỗi</button>
                </div>
                <dl v-if="skuPreview" class="mt-5 grid gap-3 rounded-lg border border-slate-800 bg-slate-950/60 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-slate-500">SKU</dt><dd class="mt-1 text-slate-200">{{ skuPreview.sku }}</dd></div>
                    <div><dt class="text-slate-500">Tên sản phẩm</dt><dd class="mt-1 text-slate-200">{{ skuPreview.product_name || 'Chưa có' }}</dd></div>
                    <div><dt class="text-slate-500">Tồn KIOT</dt><dd class="mt-1 text-slate-200">{{ skuPreview.provider_stock ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Tồn khả dụng</dt><dd class="mt-1 text-slate-200">{{ skuPreview.available_stock ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Giá KIOT</dt><dd class="mt-1 text-slate-200">{{ skuPreview.provider_price ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Khớp local</dt><dd class="mt-1 text-slate-200">{{ skuPreview.local_match ? 'Có' : 'Không' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">Checksum</dt><dd class="mt-1 break-all font-mono text-xs text-slate-300">{{ skuPreview.checksum || '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-4"><dt class="text-slate-500">Thay đổi dự kiến</dt><dd class="mt-1 font-mono text-xs text-slate-300">{{ JSON.stringify(skuPreview.proposed_changes || {}) }}</dd></div>
                </dl>
                <div v-if="configuration.database_history" class="mt-6 border-t border-slate-800 pt-4">
                    <label class="flex items-start gap-2 text-sm text-red-200"><input v-model="disconnectForm.confirm_disconnect" type="checkbox" class="mt-1"> Tôi hiểu thao tác này sẽ xoá credential, tắt mọi cờ và không khôi phục fallback `.env`.</label>
                    <button :disabled="!disconnectForm.confirm_disconnect || disconnectForm.processing" @click="disconnectForm.post('/admin/integrations/kiot/disconnect', { preserveScroll: true, onFinish: () => disconnectForm.reset() })" class="mt-3 rounded-lg border border-red-500/40 px-4 py-2 text-sm text-red-300 disabled:cursor-not-allowed disabled:opacity-40">Ngắt kết nối</button>
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
                            <tr v-for="event in recentErrors" :key="event.id"><td class="px-4 py-3 text-slate-300">{{ event.event_type }}</td><td class="px-4 py-3 text-slate-300">#{{ event.aggregate_id }}</td><td class="px-4 py-3"><p class="font-mono text-xs text-amber-300">{{ event.last_error_code }}</p><p class="mt-1 max-w-xl truncate text-slate-400">{{ event.last_error_message }}</p></td><td class="px-4 py-3 text-slate-400">{{ event.attempt_count }}</td><td class="px-4 py-3 text-right"><button v-if="['retrying', 'dead_letter'].includes(event.status)" :disabled="!canOrderRetry" @click="submit(`/admin/integrations/kiot/events/${event.id}/retry`)" class="text-cyan-400 disabled:cursor-not-allowed disabled:opacity-40">Retry</button><span v-else class="text-xs text-slate-500">Không retry</span></td></tr>
                            <tr v-if="!recentErrors.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có lỗi đồng bộ.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <section class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900" aria-labelledby="history-title">
                <div class="border-b border-slate-800 px-5 py-4"><h4 id="history-title" class="font-semibold text-slate-200">Lịch sử cấu hình và kết nối</h4></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-800/40 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Thời gian</th><th class="px-4 py-3">Thao tác</th><th class="px-4 py-3">Người thực hiện</th><th class="px-4 py-3">Chi tiết an toàn</th></tr></thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="event in history" :key="event.id"><td class="whitespace-nowrap px-4 py-3 text-slate-400">{{ formatTime(event.created_at) }}</td><td class="px-4 py-3 font-mono text-xs text-cyan-300">{{ event.event }}</td><td class="px-4 py-3 text-slate-300">{{ event.actor?.name || 'Hệ thống' }}</td><td class="px-4 py-3 font-mono text-xs text-slate-400">{{ JSON.stringify(event.metadata || {}) }}</td></tr>
                            <tr v-if="!history.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Chưa có lịch sử.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
