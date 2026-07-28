<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    connections: { type: Array, required: true },
    recentRuns: { type: Array, default: () => [] },
    recentEvents: { type: Array, default: () => [] },
});

const page = usePage();
const activeTab = ref('google_sheets');
const connection = (channel) => props.connections.find((item) => item.channel === channel) || {};
const google = computed(() => connection('google_sheets'));
const merchant = computed(() => connection('google_merchant'));
const meta = computed(() => connection('meta_catalog'));
const permissions = computed(() => page.props.auth?.user?.permissions || []);
const roles = computed(() => page.props.auth?.user?.roles || []);
const canManage = computed(() => roles.value.includes('super-admin') || permissions.value.includes('catalog-channels.manage'));
const revealedFeedUrl = computed(() => page.props.flash?.feed_url || '');
const catalogResult = computed(() => page.props.flash?.catalog_result || null);

const googleForm = useForm({
    spreadsheet_id: google.value.spreadsheet_id || '',
    worksheet: google.value.worksheet || 'Products',
    service_account_json: '',
    is_enabled: Boolean(google.value.is_enabled),
});

function saveGoogle() {
    googleForm.patch('/admin/integrations/catalog-channels/google-sheets/config', {
        preserveScroll: true,
        onSuccess: () => googleForm.reset('service_account_json'),
    });
}

function action(path) {
    router.post(path, {}, { preserveScroll: true });
}

function toggleChannel(channel, enabled) {
    router.patch(`/admin/integrations/catalog-channels/${channel}/flags`, { is_enabled: enabled }, { preserveScroll: true });
}

async function copyFeedUrl() {
    if (revealedFeedUrl.value) {
        await navigator.clipboard.writeText(revealedFeedUrl.value);
    }
}

function label(channel) {
    return {
        google_sheets: 'Google Sheets',
        google_merchant: 'Google Merchant',
        meta_catalog: 'Facebook / Meta Catalog',
    }[channel] || channel;
}

function formatTime(value) {
    return value ? new Date(value).toLocaleString('vi-VN') : 'Chưa có';
}
</script>

<template>
    <AdminLayout title="Catalog Channels">
        <div class="space-y-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-white">Catalog Channels</h1>
                <p class="mt-1 text-sm text-slate-400">Một catalog projection dùng chung cho Google Sheets, Google Merchant và Meta.</p>
            </div>

            <div v-if="page.props.errors?.catalog" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-200">
                {{ page.props.errors.catalog }}
            </div>
            <div v-if="revealedFeedUrl" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4">
                <p class="text-sm font-medium text-amber-100">Feed URL mới chỉ hiển thị một lần. Hãy lưu tại nơi quản lý secret an toàn.</p>
                <div class="mt-3 flex gap-2">
                    <input :value="revealedFeedUrl" readonly class="min-w-0 flex-1 rounded-lg border border-amber-500/30 bg-slate-950 px-3 py-2 font-mono text-xs text-slate-200">
                    <button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-slate-950" @click="copyFeedUrl">Copy URL</button>
                </div>
            </div>
            <pre v-if="catalogResult" class="overflow-auto rounded-lg border border-slate-800 bg-slate-950 p-4 text-xs text-cyan-200">{{ JSON.stringify(catalogResult, null, 2) }}</pre>

            <div class="flex flex-wrap gap-2 border-b border-slate-800 pb-3">
                <button
                    v-for="channel in ['google_sheets', 'google_merchant', 'meta_catalog']"
                    :key="channel"
                    class="rounded-lg px-4 py-2 text-sm"
                    :class="activeTab === channel ? 'bg-cyan-600 text-white' : 'bg-slate-900 text-slate-300'"
                    @click="activeTab = channel"
                >
                    {{ label(channel) }}
                </button>
            </div>

            <section v-if="activeTab === 'google_sheets'" class="grid gap-5 lg:grid-cols-2">
                <form class="rounded-xl border border-slate-800 bg-slate-900 p-5" @submit.prevent="saveGoogle">
                    <h2 class="font-semibold text-white">Google Sheets</h2>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-slate-500">Status</dt><dd class="text-slate-200">{{ google.status }}</dd></div>
                        <div><dt class="text-slate-500">Service account</dt><dd class="text-slate-200">{{ google.service_account_configured ? 'Configured' : 'Missing' }}</dd></div>
                        <div><dt class="text-slate-500">Last test</dt><dd class="text-slate-200">{{ formatTime(google.last_tested_at) }}</dd></div>
                        <div><dt class="text-slate-500">Last sync</dt><dd class="text-slate-200">{{ formatTime(google.last_success_at) }}</dd></div>
                    </dl>
                    <div class="mt-5 space-y-4">
                        <label class="block text-sm text-slate-300">Spreadsheet ID<input v-model="googleForm.spreadsheet_id" :disabled="!canManage" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"></label>
                        <label class="block text-sm text-slate-300">Worksheet<input v-model="googleForm.worksheet" :disabled="!canManage" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"></label>
                        <label class="block text-sm text-slate-300">Service Account JSON<textarea v-model="googleForm.service_account_json" :disabled="!canManage" rows="5" autocomplete="off" placeholder="Để trống để giữ credential hiện tại" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-xs"></textarea></label>
                        <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="googleForm.is_enabled" :disabled="!canManage" type="checkbox"> Enable Google Sheets sync</label>
                        <button v-if="canManage" :disabled="googleForm.processing" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white">Lưu cấu hình</button>
                    </div>
                </form>

                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="font-semibold text-white">Actions</h2>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button :disabled="!canManage" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/google-sheets/test')">Test connection</button>
                        <button :disabled="!canManage" class="rounded-lg border border-cyan-500/40 px-4 py-2 text-sm text-cyan-300 disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/google-sheets/dry-run')">Dry-run</button>
                        <button :disabled="!canManage || !google.is_enabled" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm text-white disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/google-sheets/sync')">Sync now</button>
                    </div>
                    <p v-if="google.last_error_code" class="mt-5 rounded-lg bg-red-500/10 p-3 text-sm text-red-200">{{ google.last_error_code }} · {{ google.last_error_message }}</p>
                </div>
            </section>

            <section v-else class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <template v-for="item in [merchant, meta]" :key="item.channel">
                    <div v-if="activeTab === item.channel">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="font-semibold text-white">{{ label(item.channel) }}</h2>
                                <p class="mt-1 text-sm text-slate-400">Feed token: {{ item.feed_token_configured ? 'Configured' : 'Missing' }}</p>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ item.feed_path }}?token=••••••••</p>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-slate-300"><input :checked="item.is_enabled" :disabled="!canManage" type="checkbox" @change="toggleChannel(item.channel, $event.target.checked)"> Enabled</label>
                        </div>
                        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-4">
                            <div><dt class="text-slate-500">Status</dt><dd class="text-slate-200">{{ item.status }}</dd></div>
                            <div><dt class="text-slate-500">Valid</dt><dd class="text-emerald-300">{{ item.last_run?.items_valid || 0 }}</dd></div>
                            <div><dt class="text-slate-500">Invalid</dt><dd class="text-amber-300">{{ item.last_run?.items_invalid || 0 }}</dd></div>
                            <div><dt class="text-slate-500">Last build</dt><dd class="text-slate-200">{{ formatTime(item.last_run?.completed_at) }}</dd></div>
                        </dl>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <button :disabled="!canManage" class="rounded-lg border border-amber-500/40 px-4 py-2 text-sm text-amber-300 disabled:opacity-40" @click="action(`/admin/integrations/catalog-channels/${item.channel}/rotate-token`)">Rotate token</button>
                            <button :disabled="!canManage" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:opacity-40" @click="action(`/admin/integrations/catalog-channels/${item.channel}/validate`)">Validate feed</button>
                            <button :disabled="!canManage || !item.is_enabled" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm text-white disabled:opacity-40" @click="action(`/admin/integrations/catalog-channels/${item.channel}/rebuild`)">Rebuild feed</button>
                        </div>
                    </div>
                </template>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                <div class="border-b border-slate-800 px-5 py-4"><h2 class="font-semibold text-white">Recent runs</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-800/40 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Channel</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Valid / Invalid</th><th class="px-4 py-3">Time</th></tr></thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="run in recentRuns" :key="run.id"><td class="px-4 py-3 text-cyan-300">{{ label(run.channel) }}</td><td class="px-4 py-3 text-slate-300">{{ run.mode }}</td><td class="px-4 py-3 text-slate-300">{{ run.status }}</td><td class="px-4 py-3 text-slate-400">{{ run.items_valid }} / {{ run.items_invalid }}</td><td class="px-4 py-3 text-slate-500">{{ formatTime(run.created_at) }}</td></tr>
                            <tr v-if="!recentRuns.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có catalog run.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
