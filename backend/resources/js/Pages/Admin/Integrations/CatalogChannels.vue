<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    connections: { type: Array, required: true },
    recentRuns: { type: Array, default: () => [] },
    recentEvents: { type: Array, default: () => [] },
    priceBooks: { type: Array, default: () => [] },
    priceSettings: { type: Object, default: () => ({}) },
    googleSheetsPriceColumns: { type: Array, default: () => [] },
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
const canManagePricing = computed(() => canManage.value || permissions.value.includes('catalog_channels.manage_pricing'));
const canManageGoogleSheetsPricing = computed(() => canManage.value || permissions.value.includes('catalog_channels.manage_google_sheets'));
const revealedFeedUrl = computed(() => page.props.flash?.feed_url || '');
const catalogResult = computed(() => page.props.flash?.catalog_result || null);
const fallbackPolicies = ['none', 'retail_price', 'selected_price'];
const priceSources = ['retail_price', 'selected_price'];
const singlePriceChannels = ['website', 'google_merchant', 'meta_catalog'];
const googleSheetsSources = ref([]);

const priceOptions = computed(() => [
    { value: 'retail_price', label: 'Retail price', active: true },
    { value: 'selected_price', label: 'Selected price', active: true },
    ...props.priceBooks.map((book) => ({
        value: `price_book:${book.id}`,
        label: `Price book: ${book.name}`,
        active: Boolean(book.is_active),
        book,
    })),
]);

watch(() => props.googleSheetsPriceColumns, (columns) => {
    googleSheetsSources.value = columns?.length
        ? columns.map((column) => column.price_source)
        : ['retail_price'];
}, { immediate: true });

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

function savePrice(channel, item) {
    if (channel === 'website' && !window.confirm('Changing the Website price source can change public prices. Continue?')) {
        return;
    }
    router.patch(`/admin/integrations/catalog-channels/${channel}/price`, {
        price_source: item.price_source,
        fallback_policy: item.fallback_policy,
    }, { preserveScroll: true });
}

function saveGoogleSheetsSources() {
    router.patch('/admin/integrations/catalog-channels/google-sheets/price-columns', {
        sources: googleSheetsSources.value,
    }, { preserveScroll: true });
}

function priceSetting(channel) {
    return props.priceSettings[channel] || { price_source: 'retail_price', fallback_policy: 'none' };
}

function priceBookSource(book) {
    return `price_book:${book.id}`;
}

function isSelected(channel, source) {
    return channel === 'google_sheets'
        ? googleSheetsSources.value.includes(source)
        : priceSetting(channel).price_source === source;
}

function setSingleSource(channel, source) {
    if (priceOptions.value.find((option) => option.value === source)?.active !== false) {
        priceSetting(channel).price_source = source;
    }
}

function toggleGoogleSource(source, checked) {
    if (checked && !googleSheetsSources.value.includes(source)) {
        googleSheetsSources.value = [...googleSheetsSources.value, source];
    }
    if (!checked && googleSheetsSources.value.length > 1) {
        googleSheetsSources.value = googleSheetsSources.value.filter((item) => item !== source);
    }
}

function inactiveSelected(option) {
    return option.active === false && ['website', 'google_sheets', 'google_merchant', 'meta_catalog'].some((channel) => isSelected(channel, option.value));
}

async function copyFeedUrl() {
    if (revealedFeedUrl.value) {
        await navigator.clipboard.writeText(revealedFeedUrl.value);
    }
}

function label(channel) {
    return {
        website: 'Website storefront',
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

            <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="font-semibold text-white">KIOT Price Books</h2><p class="mt-1 text-sm text-slate-400">Chỉ lưu giá provider trả về; không tự chọn bảng giá chính thức.</p></div>
                    <div class="flex gap-2"><button v-if="canManage" class="rounded-lg border border-cyan-500/40 px-3 py-2 text-sm text-cyan-300" @click="action('/admin/integrations/catalog-channels/price-books/sync')">Sync price books</button><button v-if="canManage" class="rounded-lg border border-cyan-500/40 px-3 py-2 text-sm text-cyan-300" @click="action('/admin/integrations/catalog-channels/product-prices/sync')">Sync product prices</button></div>
                </div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-2 py-2">ID</th><th class="px-2 py-2">Name</th><th class="px-2 py-2">Active</th><th class="px-2 py-2">Rows</th><th class="px-2 py-2">Positive</th><th class="px-2 py-2">Zero</th></tr></thead><tbody class="divide-y divide-slate-800"><tr v-for="book in priceBooks" :key="book.id"><td class="px-2 py-2 text-cyan-300">{{ book.id }}</td><td class="px-2 py-2 text-slate-200">{{ book.name }}</td><td class="px-2 py-2 text-slate-300">{{ book.is_active ? 'yes' : 'no' }}</td><td class="px-2 py-2 text-slate-300">{{ book.prices_count || 0 }}</td><td class="px-2 py-2 text-emerald-300">{{ book.positive_prices_count || 0 }}</td><td class="px-2 py-2 text-amber-300">{{ book.zero_prices_count || 0 }}</td></tr><tr v-if="!priceBooks.length"><td colspan="6" class="px-2 py-4 text-slate-500">Chưa có price book.</td></tr></tbody></table></div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-white">Channel price source matrix</h2>
                        <p class="mt-1 text-sm text-slate-400">Website, Google Merchant and Meta use one source; Google Sheets can export several independent columns.</p>
                    </div>
                    <span class="rounded-full border border-slate-700 px-3 py-1 text-xs text-slate-400">Fallback default: none</span>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-2 py-2">Price source</th>
                                <th class="px-2 py-2">{{ label('website') }}</th>
                                <th class="px-2 py-2">{{ label('google_sheets') }}</th>
                                <th class="px-2 py-2">{{ label('google_merchant') }}</th>
                                <th class="px-2 py-2">{{ label('meta_catalog') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="option in priceOptions" :key="option.value">
                                <td class="px-2 py-3 text-slate-200">
                                    <div>{{ option.label }}</div>
                                    <span v-if="option.book" class="text-xs text-slate-500">ID {{ option.book.remote_price_book_id }} · {{ option.book.code || 'no code' }}</span>
                                    <span v-if="option.active === false" class="ml-2 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs text-amber-300">Inactive</span>
                                    <span v-if="inactiveSelected(option)" class="ml-2 text-xs text-amber-300">Currently selected</span>
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <input
                                        name="price-source-website"
                                        :checked="isSelected('website', option.value)"
                                        :disabled="!canManagePricing || option.active === false"
                                        type="radio"
                                        @change="setSingleSource('website', option.value)"
                                    >
                                </td>
                                <td class="px-2 py-3 text-center">
                                    <input
                                        :checked="isSelected('google_sheets', option.value)"
                                        :disabled="!canManageGoogleSheetsPricing || option.active === false || (googleSheetsSources.length === 1 && isSelected('google_sheets', option.value))"
                                        type="checkbox"
                                        @change="toggleGoogleSource(option.value, $event.target.checked)"
                                    >
                                </td>
                                <td v-for="channel in ['google_merchant', 'meta_catalog']" :key="channel" class="px-2 py-3 text-center">
                                    <input
                                        :name="`price-source-${channel}`"
                                        :checked="isSelected(channel, option.value)"
                                        :disabled="!canManagePricing || option.active === false"
                                        type="radio"
                                        @change="setSingleSource(channel, option.value)"
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div v-for="channel in singlePriceChannels" :key="channel" class="rounded-lg border border-slate-800 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm text-slate-200">{{ label(channel) }}</span>
                            <button v-if="canManagePricing" class="text-xs text-cyan-300" @click="savePrice(channel, priceSetting(channel))">Save</button>
                        </div>
                        <select v-model="priceSetting(channel).fallback_policy" :disabled="!canManagePricing" class="mt-3 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200">
                            <option v-for="fallback in fallbackPolicies" :key="fallback" :value="fallback">Fallback: {{ fallback }}</option>
                        </select>
                        <p v-if="channel === 'website'" class="mt-3 text-xs text-amber-300">Changing this source can change public prices.</p>
                    </div>
                    <div class="rounded-lg border border-slate-800 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm text-slate-200">Google Sheets columns</span>
                            <button v-if="canManageGoogleSheetsPricing" class="text-xs text-cyan-300" @click="saveGoogleSheetsSources">Save</button>
                        </div>
                        <p class="mt-3 text-xs text-slate-400">Selected sources are exported as separate stable columns. Fallback is not used.</p>
                    </div>
                </div>
            </section>

            <section v-if="false" class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="font-semibold text-white">Channel Price Selection</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div v-for="channel in ['website', 'google_sheets', 'google_merchant', 'meta_catalog']" :key="channel" class="rounded-lg border border-slate-800 p-4">
                        <div class="flex items-center justify-between"><span class="text-sm text-slate-200">{{ label(channel) }}</span><button v-if="canManage" class="text-xs text-cyan-300" @click="savePrice(channel, priceSetting(channel))">Save</button></div>
                        <select v-model="priceSetting(channel).price_source" class="mt-3 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"><option v-for="source in priceSources" :key="source" :value="source">{{ source }}</option><option v-for="book in priceBooks.filter((entry) => entry.is_active)" :key="priceBookSource(book)" :value="priceBookSource(book)">{{ priceBookSource(book) }} · {{ book.name }}</option></select>
                        <select v-model="priceSetting(channel).fallback_policy" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"><option v-for="fallback in fallbackPolicies" :key="fallback" :value="fallback">fallback: {{ fallback }}</option></select>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="font-semibold text-white">Price book details</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="book in priceBooks" :key="`details-${book.id}`" class="rounded-lg border border-slate-800 p-4 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-slate-200">{{ book.name }}</span>
                            <span :class="book.is_active ? 'text-emerald-300' : 'text-amber-300'">{{ book.is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-400">
                            <div><dt>Code</dt><dd class="text-slate-200">{{ book.code || '—' }}</dd></div>
                            <div><dt>Remote ID</dt><dd class="text-slate-200">{{ book.remote_price_book_id }}</dd></div>
                            <div><dt>SKUs with price</dt><dd class="text-slate-200">{{ book.prices_count || 0 }}</dd></div>
                            <div><dt>Price &gt; 0</dt><dd class="text-emerald-300">{{ book.positive_prices_count || 0 }}</dd></div>
                            <div><dt>Price = 0</dt><dd class="text-amber-300">{{ book.zero_prices_count || 0 }}</dd></div>
                            <div><dt>Last sync</dt><dd class="text-slate-200">{{ formatTime(book.synced_at) }}</dd></div>
                        </dl>
                    </div>
                    <p v-if="!priceBooks.length" class="text-sm text-slate-500">No price books synced.</p>
                </div>
            </section>

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
                            <button v-if="item.channel === 'meta_catalog'" :disabled="!canManage" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/meta_catalog/test-connection')">Test connection</button>
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
