<script setup>
import { computed, onMounted, ref, watch } from 'vue';
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
const selectionChannel = ref('google_sheets');
const selectionProducts = ref([]);
const selectionCursor = ref(null);
const selectionFilters = ref({ keyword: '', image_status: '', price_status: '', price_book_id: '', price_book_status: '', under_repair: '', stock_status: '', visibility: '', google_eligible: '', meta_eligible: '', validation_error: '', sync_status: '' });
const selectionLoading = ref(false);
const selectionError = ref('');
const selectedProductIds = ref(new Set());
const selectionMode = ref('page');
const excludedProductIds = ref(new Set());
const selectionPageSelected = ref(false);
const selectionPreview = ref(null);
const selectionPreviewLoading = ref(false);
const selectionActionLoading = ref(false);
const selectionNotice = ref('');
const canPreviewSelection = computed(() => canManage.value || permissions.value.includes('catalog_channels.preview'));
const canSyncSelection = computed(() => canManage.value || permissions.value.includes('catalog_channels.sync'));
const canBulkManageSelection = computed(() => canManage.value || permissions.value.includes('catalog_channels.bulk_manage'));
const canExportValidation = computed(() => canManage.value || permissions.value.includes('catalog_channels.export_validation'));

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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function selectionPayload() {
    return {
        mode: selectionMode.value,
        filters: { ...selectionFilters.value },
        product_ids: selectionMode.value === 'page' ? [...selectedProductIds.value] : [],
        excluded_product_ids: [...excludedProductIds.value],
    };
}

function selectionPriceSource() {
    return selectionChannel.value === 'google_sheets'
        ? (googleSheetsSources.value[0] || 'retail_price')
        : priceSetting(selectionChannel.value).price_source;
}

function selectionFallback() {
    return selectionChannel.value === 'google_sheets' ? 'none' : priceSetting(selectionChannel.value).fallback_policy;
}

async function loadSelectionProducts(reset = true) {
    selectionLoading.value = true;
    selectionError.value = '';
    if (reset) { selectionCursor.value = null; selectionProducts.value = []; }
    try {
        const params = new URLSearchParams({ channel: selectionChannel.value, per_page: '25' });
        if (selectionCursor.value) params.set('cursor', selectionCursor.value);
        Object.entries(selectionFilters.value).forEach(([key, value]) => { if (value !== '' && value !== null) params.set(`filters[${key}]`, value); });
        const response = await fetch(`/admin/integrations/catalog-products?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        selectionProducts.value = reset ? data.data : [...selectionProducts.value, ...data.data];
        selectionCursor.value = data.next_cursor;
        if (reset) selectionPageSelected.value = false;
    } catch (error) {
        selectionError.value = error.message || 'Unable to load products.';
    } finally { selectionLoading.value = false; }
}

function isProductSelected(product) {
    return selectionMode.value === 'filtered' ? !excludedProductIds.value.has(product.id) : selectedProductIds.value.has(product.id);
}

function toggleProduct(product, checked) {
    if (selectionMode.value === 'filtered') {
        const next = new Set(excludedProductIds.value);
        checked ? next.delete(product.id) : next.add(product.id);
        excludedProductIds.value = next;
        return;
    }
    const next = new Set(selectedProductIds.value);
    checked ? next.add(product.id) : next.delete(product.id);
    selectedProductIds.value = next;
}

function togglePageSelection(checked) {
    if (!checked) { clearSelection(); return; }
    const next = new Set(selectedProductIds.value);
    selectionProducts.value.forEach((product) => next.add(product.id));
    selectedProductIds.value = next;
    selectionPageSelected.value = true;
}

function chooseAllFiltered() {
    selectionMode.value = 'filtered';
    selectedProductIds.value = new Set();
    excludedProductIds.value = new Set();
    selectionPageSelected.value = true;
}

function clearSelection() {
    selectionMode.value = 'page';
    selectedProductIds.value = new Set();
    excludedProductIds.value = new Set();
    selectionPageSelected.value = false;
    selectionPreview.value = null;
}

async function previewSelection() {
    selectionPreviewLoading.value = true;
    selectionError.value = '';
    try {
        const response = await fetch('/admin/integrations/catalog-products/preview', {
            method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ channel: selectionChannel.value, selection: selectionPayload(), price_source: selectionPriceSource(), fallback_policy: selectionFallback() }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Preview failed.');
        selectionPreview.value = data;
    } catch (error) { selectionError.value = error.message || 'Preview failed.'; }
    finally { selectionPreviewLoading.value = false; }
}

async function syncSelection() {
    if (!selectionPreview.value) return;
    const summary = selectionPreview.value.summary;
    if (summary.ELIGIBLE_COUNT === 0 && selectionChannel.value !== 'google_sheets') return;
    if (!window.confirm(`Confirm ${summary.SELECTED_COUNT} selected products for ${label(selectionChannel.value)}?`)) return;
    selectionActionLoading.value = true;
    try {
        const response = await fetch('/admin/integrations/catalog-products/sync', {
            method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ channel: selectionChannel.value, selection: selectionPayload(), price_source: selectionPriceSource(), fallback_policy: selectionFallback(), confirmed: true, preview_token: selectionPreview.value.preview_token }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Bulk sync failed.');
        selectionNotice.value = `Accepted run #${data.run_id}; remote submitted: ${data.remote_submitted ? 'yes' : 'no'}.`;
    } catch (error) { selectionError.value = error.message || 'Bulk sync failed.'; }
    finally { selectionActionLoading.value = false; }
}

async function exportSelectionValidation() {
    selectionActionLoading.value = true;
    try {
        const response = await fetch('/admin/integrations/catalog-products/export-validation', {
            method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ channel: selectionChannel.value, selection: selectionPayload(), price_source: selectionPriceSource(), fallback_policy: selectionFallback() }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Export failed.');
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = `catalog-validation-${selectionChannel.value}.json`; link.click(); URL.revokeObjectURL(link.href);
    } catch (error) { selectionError.value = error.message || 'Export failed.'; }
    finally { selectionActionLoading.value = false; }
}

async function bulkChannelAction(actionName) {
    if (!window.confirm(`Confirm ${actionName} selected products for ${label(selectionChannel.value)}?`)) return;
    selectionActionLoading.value = true;
    try {
        const response = await fetch(`/admin/integrations/catalog-products/bulk/${actionName}`, {
            method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ channel: selectionChannel.value, selection: selectionPayload(), price_source: selectionPriceSource(), fallback_policy: selectionFallback() }),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        selectionNotice.value = `Bulk ${actionName} recorded.`;
    } catch (error) { selectionError.value = error.message || 'Bulk action failed.'; }
    finally { selectionActionLoading.value = false; }
}

watch(selectionChannel, () => loadSelectionProducts());
onMounted(() => loadSelectionProducts());
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

            <section class="rounded-xl border border-cyan-500/30 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-white">Select products to sync</h2>
                        <p class="mt-1 text-sm text-slate-400">Choose a channel and price source, filter products, then preview before any bulk action.</p>
                    </div>
                    <select v-model="selectionChannel" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200">
                        <option value="google_sheets">Google Sheets</option>
                        <option value="google_merchant">Google Merchant</option>
                        <option value="meta_catalog">Facebook / Meta</option>
                    </select>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <input v-model="selectionFilters.keyword" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="SKU or product name">
                    <select v-model="selectionFilters.image_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">All image states</option><option value="has_image">Has image</option><option value="missing">Missing image</option><option value="invalid">Invalid image URL</option></select>
                    <select v-model="selectionFilters.price_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">All prices</option><option value="positive">Price &gt; 0</option><option value="zero">Price = 0</option></select>
                    <select v-model="selectionFilters.visibility" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">All visibility</option><option value="visible">Visible</option><option value="hidden">Hidden</option></select>
                    <select v-model="selectionFilters.stock_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">All stock</option><option value="in_stock">In stock</option><option value="out_of_stock">Out of stock</option></select>
                    <select v-model="selectionFilters.under_repair" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Repair status</option><option value="true">Under repair</option><option value="false">Ready</option></select>
                    <select v-model="selectionFilters.sync_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Sync status</option><option value="synced">Synced</option><option value="not_synced">Not synced</option></select>
                    <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-slate-200" :disabled="selectionLoading" @click="loadSelectionProducts()">{{ selectionLoading ? 'Loading...' : 'Apply filters' }}</button>
                </div>
                <div v-if="selectionError" class="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200">{{ selectionError }} <button class="ml-2 underline" @click="loadSelectionProducts()">Retry</button></div>
                <div v-if="selectionNotice" class="mt-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-200">{{ selectionNotice }}</div>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <button class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200" :disabled="!selectionProducts.length" @click="togglePageSelection(true)">Select all on page</button>
                    <button v-if="selectionPageSelected && selectionMode === 'page'" class="rounded-lg border border-cyan-500/40 px-3 py-2 text-cyan-300" @click="chooseAllFiltered">Select all filtered products</button>
                    <button class="rounded-lg border border-slate-700 px-3 py-2 text-slate-300" :disabled="!selectedProductIds.size && selectionMode !== 'filtered'" @click="clearSelection">Clear selection</button>
                    <span class="text-slate-400">Selected: {{ selectionMode === 'filtered' ? 'all filtered (backend count)' : selectedProductIds.size }}</span>
                    <button v-if="canPreviewSelection" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm text-white disabled:opacity-40" :disabled="selectionPreviewLoading || (!selectedProductIds.size && selectionMode !== 'filtered')" @click="previewSelection">{{ selectionPreviewLoading ? 'Previewing...' : 'Preview sync' }}</button>
                    <button v-if="canExportValidation" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200" :disabled="selectionActionLoading" @click="exportSelectionValidation">Export validation</button>
                    <button v-if="canSyncSelection" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white disabled:opacity-40" :disabled="selectionActionLoading || !selectionPreview || (selectionPreview.summary.ELIGIBLE_COUNT === 0 && selectionChannel !== 'google_sheets')" @click="syncSelection">Sync selected</button>
                    <button v-if="canBulkManageSelection" class="rounded-lg border border-amber-500/40 px-3 py-2 text-sm text-amber-300" :disabled="selectionActionLoading" @click="bulkChannelAction('disable')">Disable selected</button>
                </div>
                <div v-if="selectionMode === 'page' && selectionPageSelected" class="mt-3 rounded-lg border border-cyan-500/30 bg-cyan-500/10 p-3 text-sm text-cyan-100">Selected {{ selectionProducts.length }} products on this page. Select all filtered products to include every matching product without sending all IDs from the browser.</div>
                <div class="mt-4 overflow-x-auto rounded-lg border border-slate-800">
                    <table class="min-w-[1500px] text-xs">
                        <thead class="bg-slate-800/60 text-left uppercase text-slate-500"><tr><th class="px-2 py-2"><input type="checkbox" :checked="selectionProducts.length > 0 && selectionProducts.every(isProductSelected)" @change="togglePageSelection($event.target.checked)"></th><th class="px-2 py-2">SKU</th><th class="px-2 py-2">Name</th><th class="px-2 py-2">Category</th><th class="px-2 py-2">Image</th><th class="px-2 py-2">Retail</th><th class="px-2 py-2">Selected price</th><th class="px-2 py-2">Source</th><th class="px-2 py-2">Stock</th><th class="px-2 py-2">Repair</th><th class="px-2 py-2">Visible</th><th class="px-2 py-2">Google</th><th class="px-2 py-2">Meta</th><th class="px-2 py-2">Errors</th><th class="px-2 py-2">Last sync</th></tr></thead>
                        <tbody class="divide-y divide-slate-800"><tr v-for="product in selectionProducts" :key="product.id"><td class="px-2 py-2"><input type="checkbox" :checked="isProductSelected(product)" @change="toggleProduct(product, $event.target.checked)"></td><td class="px-2 py-2 font-mono text-cyan-300">{{ product.sku }}</td><td class="max-w-[220px] px-2 py-2 text-slate-200">{{ product.name }}</td><td class="px-2 py-2 text-slate-400">{{ product.category || '—' }}</td><td class="px-2 py-2"><img v-if="product.image_status === 'has_image'" :src="product.image_url" class="h-8 w-8 rounded object-cover" :alt="product.sku"><span v-else class="rounded bg-red-500/10 px-2 py-1 text-red-300">{{ product.image_status }}</span></td><td class="px-2 py-2 text-slate-300">{{ product.retail_price }}</td><td class="px-2 py-2 text-slate-300">{{ product.selected_price ?? '—' }}</td><td class="px-2 py-2 text-slate-400">{{ product.price_source }}</td><td class="px-2 py-2 text-slate-300">{{ product.stock }}</td><td class="px-2 py-2" :class="product.repair_status === 'repairing' ? 'text-amber-300' : 'text-slate-400'">{{ product.repair_status }}</td><td class="px-2 py-2">{{ product.is_visible ? 'yes' : 'no' }}</td><td class="px-2 py-2" :class="product.google_eligible ? 'text-emerald-300' : 'text-red-300'">{{ product.google_eligible ? 'yes' : 'no' }}</td><td class="px-2 py-2" :class="product.meta_eligible ? 'text-emerald-300' : 'text-red-300'">{{ product.meta_eligible ? 'yes' : 'no' }}</td><td class="max-w-[220px] px-2 py-2 text-red-300">{{ product.validation_errors.join(', ') || '—' }}</td><td class="px-2 py-2 text-slate-500">{{ formatTime(product.last_sync) }}</td></tr><tr v-if="!selectionLoading && !selectionProducts.length"><td colspan="15" class="px-3 py-8 text-center text-slate-500">No products match these filters.</td></tr></tbody>
                    </table>
                </div>
                <button v-if="selectionCursor" class="mt-3 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300" :disabled="selectionLoading" @click="loadSelectionProducts(false)">Load next page</button>
                <div v-if="selectionPreview" class="mt-5 rounded-lg border border-cyan-500/30 bg-slate-950 p-4"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold text-white">Preview: {{ selectionPreview.summary.CHANNEL }}</h3><span class="text-xs text-slate-400">{{ selectionPreview.summary.PRICE_SOURCE }} · {{ selectionPreview.summary.SELECTION_SCOPE }}</span></div><div class="mt-3 grid gap-2 text-xs text-slate-300 sm:grid-cols-3 md:grid-cols-6"><span>Selected {{ selectionPreview.summary.SELECTED_COUNT }}</span><span>Eligible {{ selectionPreview.summary.ELIGIBLE_COUNT }}</span><span>Invalid {{ selectionPreview.summary.INVALID_COUNT }}</span><span>Missing image {{ selectionPreview.summary.IMAGE_MISSING_COUNT }}</span><span>Zero price {{ selectionPreview.summary.PRICE_ZERO_COUNT }}</span><span>Under repair {{ selectionPreview.summary.UNDER_REPAIR_COUNT }}</span><span>Create {{ selectionPreview.summary.CREATE_COUNT }}</span><span>Update {{ selectionPreview.summary.UPDATE_COUNT }}</span><span>Unchanged {{ selectionPreview.summary.UNCHANGED_COUNT }}</span><span>Skipped {{ selectionPreview.summary.SKIPPED_COUNT }}</span></div><div class="mt-3 max-h-72 overflow-auto"><table class="min-w-full text-xs"><thead class="text-left text-slate-500"><tr><th class="px-2 py-1">SKU</th><th class="px-2 py-1">Image</th><th class="px-2 py-1">Price</th><th class="px-2 py-1">Eligibility</th><th class="px-2 py-1">Errors</th><th class="px-2 py-1">Action</th></tr></thead><tbody class="divide-y divide-slate-800"><tr v-for="item in selectionPreview.items" :key="item.id"><td class="px-2 py-1 font-mono text-cyan-300">{{ item.sku }}</td><td class="px-2 py-1 text-slate-400">{{ item.image_status }}</td><td class="px-2 py-1">{{ item.selected_price ?? '—' }}</td><td class="px-2 py-1" :class="item.eligible ? 'text-emerald-300' : 'text-red-300'">{{ item.eligible ? 'eligible' : 'invalid' }}</td><td class="px-2 py-1 text-red-300">{{ item.validation_errors.join(', ') || '—' }}</td><td class="px-2 py-1 text-slate-300">{{ item.action }}</td></tr></tbody></table></div></div>
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
