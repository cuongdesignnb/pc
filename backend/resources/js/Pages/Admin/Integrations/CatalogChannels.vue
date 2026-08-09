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

const channelDescriptions = {
    website: 'Giá và thông tin hiển thị trên website bán hàng.',
    google_sheets: 'Xuất danh sách sản phẩm ra bảng Google Sheets để quản lý hoặc chia sẻ.',
    google_merchant: 'Nguồn dữ liệu sản phẩm gửi cho Google Merchant Center.',
    meta_catalog: 'Nguồn dữ liệu sản phẩm gửi cho Facebook/Meta Catalog.',
};

const priceSourceLabels = {
    retail_price: 'Giá bán lẻ',
    selected_price: 'Giá đã chọn',
};

const fallbackLabels = {
    none: 'Không dùng giá dự phòng',
    retail_price: 'Dùng giá bán lẻ khi thiếu giá chính',
    selected_price: 'Dùng giá đã chọn khi thiếu giá chính',
};

const statusLabels = {
    configured: 'Đã cấu hình',
    connected: 'Đã kết nối',
    enabled: 'Đang bật',
    disabled: 'Đang tắt',
    not_configured: 'Chưa cấu hình',
    pending: 'Đang chờ',
    running: 'Đang chạy',
    completed: 'Hoàn tất',
    completed_with_warnings: 'Hoàn tất, có cảnh báo',
    failed: 'Thất bại',
    cancelled: 'Đã hủy',
};

const runModeLabels = {
    dry_run: 'Chạy thử',
    sync: 'Đồng bộ',
    bulk_sync: 'Đồng bộ hàng loạt',
    preview: 'Xem trước',
};

const productStatusLabels = {
    has_image: 'Có ảnh',
    missing: 'Thiếu ảnh',
    invalid: 'Ảnh không hợp lệ',
    repairing: 'Đang sửa chữa',
    ready: 'Sẵn sàng',
    synced: 'Đã đồng bộ',
    not_synced: 'Chưa đồng bộ',
    visible: 'Đang hiển thị',
    hidden: 'Đang ẩn',
    in_stock: 'Còn hàng',
    out_of_stock: 'Hết hàng',
};

const validationErrorLabels = {
    PRODUCT_DELETED: 'Sản phẩm đã bị xóa',
    PRODUCT_INACTIVE: 'Sản phẩm đang ngừng hoạt động',
    PRODUCT_HIDDEN: 'Sản phẩm đang ẩn',
    CATEGORY_HIDDEN: 'Danh mục đang ẩn',
    PRICE_MISSING: 'Chưa có giá',
    PRICE_ZERO: 'Giá bằng 0',
    IMAGE_MISSING: 'Thiếu ảnh sản phẩm',
    IMAGE_INVALID: 'URL ảnh không hợp lệ',
    UNDER_REPAIR: 'Sản phẩm đang sửa chữa',
};

const actionLabels = {
    create: 'Tạo mới',
    update: 'Cập nhật',
    unchanged: 'Không thay đổi',
    skip: 'Bỏ qua',
    skipped: 'Bỏ qua',
    disable: 'Tắt',
};

const feedErrorLabels = {
    FEED_EMPTY: 'Không có sản phẩm hợp lệ để tạo feed',
    CHANNEL_DISABLED: 'Kênh đang tắt',
    GOOGLE_SHEETS_NOT_CONFIGURED: 'Google Sheets chưa được cấu hình',
    META_CATALOG_NOT_CONFIGURED: 'Meta Catalog chưa được cấu hình',
    GOOGLE_MERCHANT_NOT_CONFIGURED: 'Google Merchant chưa được cấu hình',
};

const priceOptions = computed(() => [
    { value: 'retail_price', label: priceSourceLabels.retail_price, active: true },
    { value: 'selected_price', label: priceSourceLabels.selected_price, active: true },
    ...props.priceBooks.map((book) => ({
        value: `price_book:${book.id}`,
        label: `Bảng giá: ${book.name}`,
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
    if (channel === 'website' && !window.confirm('Thay đổi nguồn giá website có thể làm thay đổi giá công khai. Bạn có muốn tiếp tục không?')) {
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
        website: 'Website bán hàng',
        google_sheets: 'Google Sheets',
        google_merchant: 'Google Merchant',
        meta_catalog: 'Facebook / Meta Catalog',
    }[channel] || channel;
}

function description(channel) {
    return channelDescriptions[channel] || '';
}

function statusLabel(value) {
    return statusLabels[value] || value || 'Chưa có';
}

function runModeLabel(value) {
    return runModeLabels[value] || value || 'Chưa có';
}

function productStatusLabel(value) {
    return productStatusLabels[value] || value || 'Chưa có';
}

function priceSourceLabel(value) {
    if (priceSourceLabels[value]) return priceSourceLabels[value];
    if (value?.startsWith('price_book:')) {
        const book = props.priceBooks.find((item) => `price_book:${item.id}` === value);
        return book ? `Bảng giá: ${book.name}` : 'Bảng giá không xác định';
    }
    return value || 'Chưa có';
}

function fallbackLabel(value) {
    return fallbackLabels[value] || value || 'Không dùng giá dự phòng';
}

function validationErrorLabel(value) {
    return validationErrorLabels[value] || value?.replaceAll('_', ' ').toLowerCase() || 'Không xác định';
}

function actionLabel(value) {
    return actionLabels[value] || value || 'Không xác định';
}

function feedErrorLabel(value) {
    return feedErrorLabels[value] || value || 'Không xác định';
}

function yesNo(value) {
    return value ? 'Có' : 'Không';
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
        selectionError.value = error.message || 'Không thể tải danh sách sản phẩm.';
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
        if (!response.ok) throw new Error(data.message || 'Không thể tạo bản xem trước.');
        selectionPreview.value = data;
    } catch (error) { selectionError.value = error.message || 'Không thể tạo bản xem trước.'; }
    finally { selectionPreviewLoading.value = false; }
}

async function syncSelection() {
    if (!selectionPreview.value) return;
    const summary = selectionPreview.value.summary;
    if (summary.ELIGIBLE_COUNT === 0 && selectionChannel.value !== 'google_sheets') return;
    if (!window.confirm(`Xác nhận ${summary.SELECTED_COUNT} sản phẩm đã chọn cho ${label(selectionChannel.value)}?`)) return;
    selectionActionLoading.value = true;
    try {
        const response = await fetch('/admin/integrations/catalog-products/sync', {
            method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ channel: selectionChannel.value, selection: selectionPayload(), price_source: selectionPriceSource(), fallback_policy: selectionFallback(), confirmed: true, preview_token: selectionPreview.value.preview_token }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Đồng bộ hàng loạt thất bại.');
        selectionNotice.value = `Đã tiếp nhận lượt chạy #${data.run_id}; gửi sang hệ thống ngoài: ${data.remote_submitted ? 'Có' : 'Không'}.`;
    } catch (error) { selectionError.value = error.message || 'Đồng bộ hàng loạt thất bại.'; }
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
        if (!response.ok) throw new Error(data.message || 'Không thể xuất kết quả kiểm tra.');
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = `catalog-validation-${selectionChannel.value}.json`; link.click(); URL.revokeObjectURL(link.href);
    } catch (error) { selectionError.value = error.message || 'Không thể xuất kết quả kiểm tra.'; }
    finally { selectionActionLoading.value = false; }
}

async function bulkChannelAction(actionName) {
    if (!window.confirm(`Xác nhận thao tác ${actionName === 'disable' ? 'tắt' : actionName} cho các sản phẩm đã chọn trong ${label(selectionChannel.value)}?`)) return;
    selectionActionLoading.value = true;
    try {
        const response = await fetch(`/admin/integrations/catalog-products/bulk/${actionName}`, {
            method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ channel: selectionChannel.value, selection: selectionPayload(), price_source: selectionPriceSource(), fallback_policy: selectionFallback() }),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        selectionNotice.value = `Đã ghi nhận thao tác ${actionName === 'disable' ? 'tắt' : actionName}.`;
    } catch (error) { selectionError.value = error.message || 'Thao tác hàng loạt thất bại.'; }
    finally { selectionActionLoading.value = false; }
}

watch(selectionChannel, () => loadSelectionProducts());
onMounted(() => loadSelectionProducts());
</script>

<template>
    <AdminLayout title="Kênh xuất sản phẩm">
        <div class="space-y-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold text-white">Kênh xuất sản phẩm</h1>
                <p class="mt-1 text-sm text-slate-400">Quản lý các nơi nhận dữ liệu sản phẩm: website, Google Sheets, Google Merchant và Facebook/Meta.</p>
            </div>

            <div v-if="page.props.errors?.catalog" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-200">
                {{ page.props.errors.catalog }}
            </div>
            <div v-if="revealedFeedUrl" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4">
                <p class="text-sm font-medium text-amber-100">Đường dẫn feed mới chỉ hiển thị một lần. Hãy lưu tại nơi quản lý thông tin bảo mật an toàn.</p>
                <div class="mt-3 flex gap-2">
                    <input :value="revealedFeedUrl" readonly class="min-w-0 flex-1 rounded-lg border border-amber-500/30 bg-slate-950 px-3 py-2 font-mono text-xs text-slate-200">
                    <button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-slate-950" @click="copyFeedUrl">Sao chép URL</button>
                </div>
            </div>
            <pre v-if="catalogResult" class="overflow-auto rounded-lg border border-slate-800 bg-slate-950 p-4 text-xs text-cyan-200">{{ JSON.stringify(catalogResult, null, 2) }}</pre>

            <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="font-semibold text-white">Bảng giá từ KIOT</h2><p class="mt-1 text-sm text-slate-400">Đây là các bảng giá KIOT trả về. Hãy đồng bộ trước, sau đó chọn nguồn giá cho từng kênh.</p></div>
                    <div class="flex gap-2"><button v-if="canManage" class="rounded-lg border border-cyan-500/40 px-3 py-2 text-sm text-cyan-300" @click="action('/admin/integrations/catalog-channels/price-books/sync')">Đồng bộ bảng giá</button><button v-if="canManage" class="rounded-lg border border-cyan-500/40 px-3 py-2 text-sm text-cyan-300" @click="action('/admin/integrations/catalog-channels/product-prices/sync')">Đồng bộ giá sản phẩm</button></div>
                </div>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="px-2 py-2">Mã</th><th class="px-2 py-2">Tên bảng giá</th><th class="px-2 py-2">Trạng thái</th><th class="px-2 py-2">Số dòng giá</th><th class="px-2 py-2">Giá dương</th><th class="px-2 py-2">Giá bằng 0</th></tr></thead><tbody class="divide-y divide-slate-800"><tr v-for="book in priceBooks" :key="book.id"><td class="px-2 py-2 text-cyan-300">{{ book.id }}</td><td class="px-2 py-2 text-slate-200">{{ book.name }}</td><td class="px-2 py-2 text-slate-300">{{ book.is_active ? 'Đang dùng' : 'Không dùng' }}</td><td class="px-2 py-2 text-slate-300">{{ book.prices_count || 0 }}</td><td class="px-2 py-2 text-emerald-300">{{ book.positive_prices_count || 0 }}</td><td class="px-2 py-2 text-amber-300">{{ book.zero_prices_count || 0 }}</td></tr><tr v-if="!priceBooks.length"><td colspan="6" class="px-2 py-4 text-slate-500">Chưa có bảng giá nào được đồng bộ.</td></tr></tbody></table></div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-white">Chọn nguồn giá cho từng kênh</h2>
                        <p class="mt-1 text-sm text-slate-400">Website, Google Merchant và Meta dùng một nguồn giá duy nhất. Google Sheets có thể xuất nhiều cột giá độc lập.</p>
                    </div>
                    <span class="rounded-full border border-slate-700 px-3 py-1 text-xs text-slate-400">Mặc định: không dùng giá dự phòng</span>
                    <p class="mt-2 text-xs text-slate-500">Nút tròn = chỉ chọn một nguồn giá. Ô vuông = có thể chọn nhiều cột giá cho Google Sheets.</p>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-2 py-2">Nguồn giá</th>
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
                                    <span v-if="option.book" class="text-xs text-slate-500">Mã từ KIOT: {{ option.book.remote_price_book_id }} · {{ option.book.code || 'chưa có mã' }}</span>
                                    <span v-if="option.active === false" class="ml-2 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs text-amber-300">Không còn hoạt động</span>
                                    <span v-if="inactiveSelected(option)" class="ml-2 text-xs text-amber-300">Đang được chọn</span>
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
                            <button v-if="canManagePricing" class="text-xs text-cyan-300" @click="savePrice(channel, priceSetting(channel))">Lưu</button>
                        </div>
                        <select v-model="priceSetting(channel).fallback_policy" :disabled="!canManagePricing" class="mt-3 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200">
                            <option v-for="fallback in fallbackPolicies" :key="fallback" :value="fallback">{{ fallbackLabel(fallback) }}</option>
                        </select>
                        <p v-if="channel === 'website'" class="mt-3 text-xs text-amber-300">Thay đổi nguồn này có thể làm thay đổi giá đang hiển thị công khai trên website.</p>
                    </div>
                    <div class="rounded-lg border border-slate-800 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm text-slate-200">Các cột giá Google Sheets</span>
                            <button v-if="canManageGoogleSheetsPricing" class="text-xs text-cyan-300" @click="saveGoogleSheetsSources">Lưu</button>
                        </div>
                        <p class="mt-3 text-xs text-slate-400">Mỗi nguồn giá đã chọn sẽ được xuất thành một cột riêng, ổn định. Phần này không dùng giá dự phòng.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-cyan-500/30 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-white">Chọn sản phẩm để đồng bộ</h2>
                        <p class="mt-1 text-sm text-slate-400">Chọn kênh và nguồn giá, lọc sản phẩm, xem trước kết quả rồi mới thực hiện thao tác hàng loạt.</p>
                        <p class="mt-1 text-xs text-cyan-200">{{ description(selectionChannel) }}</p>
                    </div>
                    <select v-model="selectionChannel" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200">
                        <option value="google_sheets">Google Sheets</option>
                        <option value="google_merchant">Google Merchant</option>
                        <option value="meta_catalog">Facebook / Meta Catalog</option>
                    </select>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-4">
                    <input v-model="selectionFilters.keyword" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="SKU hoặc tên sản phẩm">
                    <select v-model="selectionFilters.image_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Tất cả trạng thái ảnh</option><option value="has_image">{{ productStatusLabel('has_image') }}</option><option value="missing">{{ productStatusLabel('missing') }}</option><option value="invalid">URL ảnh không hợp lệ</option></select>
                    <select v-model="selectionFilters.price_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Tất cả mức giá</option><option value="positive">Giá lớn hơn 0</option><option value="zero">Giá bằng 0</option></select>
                    <select v-model="selectionFilters.visibility" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Tất cả trạng thái hiển thị</option><option value="visible">{{ productStatusLabel('visible') }}</option><option value="hidden">{{ productStatusLabel('hidden') }}</option></select>
                    <select v-model="selectionFilters.stock_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Tất cả trạng thái tồn kho</option><option value="in_stock">{{ productStatusLabel('in_stock') }}</option><option value="out_of_stock">{{ productStatusLabel('out_of_stock') }}</option></select>
                    <select v-model="selectionFilters.under_repair" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Trạng thái sửa chữa</option><option value="true">{{ productStatusLabel('repairing') }}</option><option value="false">{{ productStatusLabel('ready') }}</option></select>
                    <select v-model="selectionFilters.sync_status" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"><option value="">Trạng thái đồng bộ</option><option value="synced">{{ productStatusLabel('synced') }}</option><option value="not_synced">{{ productStatusLabel('not_synced') }}</option></select>
                    <button class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-slate-200" :disabled="selectionLoading" @click="loadSelectionProducts()">{{ selectionLoading ? 'Đang tải...' : 'Áp dụng bộ lọc' }}</button>
                </div>
                <div v-if="selectionError" class="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200">{{ selectionError }} <button class="ml-2 underline" @click="loadSelectionProducts()">Thử lại</button></div>
                <div v-if="selectionNotice" class="mt-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-200">{{ selectionNotice }}</div>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <button class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200" :disabled="!selectionProducts.length" @click="togglePageSelection(true)">Chọn tất cả trên trang này</button>
                    <button v-if="selectionPageSelected && selectionMode === 'page'" class="rounded-lg border border-cyan-500/40 px-3 py-2 text-cyan-300" @click="chooseAllFiltered">Chọn tất cả sản phẩm phù hợp bộ lọc</button>
                    <button class="rounded-lg border border-slate-700 px-3 py-2 text-slate-300" :disabled="!selectedProductIds.size && selectionMode !== 'filtered'" @click="clearSelection">Bỏ chọn</button>
                    <span class="text-slate-400">Đã chọn: {{ selectionMode === 'filtered' ? 'tất cả sản phẩm phù hợp bộ lọc' : selectedProductIds.size }}</span>
                    <button v-if="canPreviewSelection" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm text-white disabled:opacity-40" :disabled="selectionPreviewLoading || (!selectedProductIds.size && selectionMode !== 'filtered')" @click="previewSelection">{{ selectionPreviewLoading ? 'Đang xem trước...' : 'Xem trước đồng bộ' }}</button>
                    <button v-if="canExportValidation" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200" :disabled="selectionActionLoading" @click="exportSelectionValidation">Xuất kết quả kiểm tra</button>
                    <button v-if="canSyncSelection" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white disabled:opacity-40" :disabled="selectionActionLoading || !selectionPreview || (selectionPreview.summary.ELIGIBLE_COUNT === 0 && selectionChannel !== 'google_sheets')" @click="syncSelection">Đồng bộ sản phẩm đã chọn</button>
                    <button v-if="canBulkManageSelection" class="rounded-lg border border-amber-500/40 px-3 py-2 text-sm text-amber-300" :disabled="selectionActionLoading" @click="bulkChannelAction('disable')">Tắt sản phẩm đã chọn</button>
                </div>
                <div v-if="selectionMode === 'page' && selectionPageSelected" class="mt-3 rounded-lg border border-cyan-500/30 bg-cyan-500/10 p-3 text-sm text-cyan-100">Đã chọn {{ selectionProducts.length }} sản phẩm trên trang này. Chọn tất cả sản phẩm phù hợp bộ lọc để bao gồm toàn bộ kết quả mà không phải gửi toàn bộ ID từ trình duyệt.</div>
                <div class="mt-4 overflow-x-auto rounded-lg border border-slate-800">
                    <table class="min-w-[1500px] text-xs">
                        <thead class="bg-slate-800/60 text-left uppercase text-slate-500">
                            <tr>
                                <th class="px-2 py-2"><input type="checkbox" :checked="selectionProducts.length > 0 && selectionProducts.every(isProductSelected)" @change="togglePageSelection($event.target.checked)"></th>
                                <th class="px-2 py-2">SKU</th>
                                <th class="px-2 py-2">Tên sản phẩm</th>
                                <th class="px-2 py-2">Danh mục</th>
                                <th class="px-2 py-2">Ảnh</th>
                                <th class="px-2 py-2">Giá bán lẻ</th>
                                <th class="px-2 py-2">Giá đã chọn</th>
                                <th class="px-2 py-2">Nguồn giá</th>
                                <th class="px-2 py-2">Tồn kho</th>
                                <th class="px-2 py-2">Sửa chữa</th>
                                <th class="px-2 py-2">Hiển thị</th>
                                <th class="px-2 py-2">Google</th>
                                <th class="px-2 py-2">Meta</th>
                                <th class="px-2 py-2">Lỗi</th>
                                <th class="px-2 py-2">Đồng bộ gần nhất</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="product in selectionProducts" :key="product.id">
                                <td class="px-2 py-2"><input type="checkbox" :checked="isProductSelected(product)" @change="toggleProduct(product, $event.target.checked)"></td>
                                <td class="px-2 py-2 font-mono text-cyan-300">{{ product.sku }}</td>
                                <td class="max-w-[220px] px-2 py-2 text-slate-200">{{ product.name }}</td>
                                <td class="px-2 py-2 text-slate-400">{{ product.category || '—' }}</td>
                                <td class="px-2 py-2"><img v-if="product.image_status === 'has_image'" :src="product.image_url" class="h-8 w-8 rounded object-cover" :alt="product.sku"><span v-else class="rounded bg-red-500/10 px-2 py-1 text-red-300">{{ productStatusLabel(product.image_status) }}</span></td>
                                <td class="px-2 py-2 text-slate-300">{{ product.retail_price }}</td>
                                <td class="px-2 py-2 text-slate-300">{{ product.selected_price ?? '—' }}</td>
                                <td class="px-2 py-2 text-slate-400">{{ priceSourceLabel(product.price_source) }}</td>
                                <td class="px-2 py-2 text-slate-300">{{ product.stock }}</td>
                                <td class="px-2 py-2" :class="product.repair_status === 'repairing' ? 'text-amber-300' : 'text-slate-400'">{{ productStatusLabel(product.repair_status) }}</td>
                                <td class="px-2 py-2">{{ yesNo(product.is_visible) }}</td>
                                <td class="px-2 py-2" :class="product.google_eligible ? 'text-emerald-300' : 'text-red-300'">{{ yesNo(product.google_eligible) }}</td>
                                <td class="px-2 py-2" :class="product.meta_eligible ? 'text-emerald-300' : 'text-red-300'">{{ yesNo(product.meta_eligible) }}</td>
                                <td class="max-w-[220px] px-2 py-2 text-red-300">{{ product.validation_errors.map(validationErrorLabel).join(', ') || '—' }}</td>
                                <td class="px-2 py-2 text-slate-500">{{ formatTime(product.last_sync) }}</td>
                            </tr>
                            <tr v-if="!selectionLoading && !selectionProducts.length"><td colspan="15" class="px-3 py-8 text-center text-slate-500">Không có sản phẩm phù hợp với bộ lọc.</td></tr>
                        </tbody>
                    </table>
                </div>
                <button v-if="selectionCursor" class="mt-3 rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300" :disabled="selectionLoading" @click="loadSelectionProducts(false)">Tải trang tiếp theo</button>
                <div v-if="selectionPreview" class="mt-5 rounded-lg border border-cyan-500/30 bg-slate-950 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-white">Xem trước: {{ label(selectionPreview.summary.CHANNEL) }}</h3>
                        <span class="text-xs text-slate-400">{{ priceSourceLabel(selectionPreview.summary.PRICE_SOURCE) }} · {{ selectionPreview.summary.SELECTION_SCOPE === 'filtered' ? 'Theo bộ lọc' : 'Theo trang' }}</span>
                    </div>
                    <div class="mt-3 grid gap-2 text-xs text-slate-300 sm:grid-cols-3 md:grid-cols-6">
                        <span>Đã chọn: {{ selectionPreview.summary.SELECTED_COUNT }}</span>
                        <span>Đủ điều kiện: {{ selectionPreview.summary.ELIGIBLE_COUNT }}</span>
                        <span>Không hợp lệ: {{ selectionPreview.summary.INVALID_COUNT }}</span>
                        <span>Thiếu ảnh: {{ selectionPreview.summary.IMAGE_MISSING_COUNT }}</span>
                        <span>Giá bằng 0: {{ selectionPreview.summary.PRICE_ZERO_COUNT }}</span>
                        <span>Đang sửa chữa: {{ selectionPreview.summary.UNDER_REPAIR_COUNT }}</span>
                        <span>Tạo mới: {{ selectionPreview.summary.CREATE_COUNT }}</span>
                        <span>Cập nhật: {{ selectionPreview.summary.UPDATE_COUNT }}</span>
                        <span>Không đổi: {{ selectionPreview.summary.UNCHANGED_COUNT }}</span>
                        <span>Bỏ qua: {{ selectionPreview.summary.SKIPPED_COUNT }}</span>
                    </div>
                    <div class="mt-3 max-h-72 overflow-auto">
                        <table class="min-w-full text-xs">
                            <thead class="text-left text-slate-500"><tr><th class="px-2 py-1">SKU</th><th class="px-2 py-1">Ảnh</th><th class="px-2 py-1">Giá</th><th class="px-2 py-1">Điều kiện</th><th class="px-2 py-1">Lỗi</th><th class="px-2 py-1">Thao tác</th></tr></thead>
                            <tbody class="divide-y divide-slate-800">
                                <tr v-for="item in selectionPreview.items" :key="item.id">
                                    <td class="px-2 py-1 font-mono text-cyan-300">{{ item.sku }}</td>
                                    <td class="px-2 py-1 text-slate-400">{{ productStatusLabel(item.image_status) }}</td>
                                    <td class="px-2 py-1">{{ item.selected_price ?? '—' }}</td>
                                    <td class="px-2 py-1" :class="item.eligible ? 'text-emerald-300' : 'text-red-300'">{{ item.eligible ? 'Đủ điều kiện' : 'Không hợp lệ' }}</td>
                                    <td class="px-2 py-1 text-red-300">{{ item.validation_errors.map(validationErrorLabel).join(', ') || '—' }}</td>
                                    <td class="px-2 py-1 text-slate-300">{{ actionLabel(item.action) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section v-if="false" class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="font-semibold text-white">Chọn nguồn giá theo kênh</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div v-for="channel in ['website', 'google_sheets', 'google_merchant', 'meta_catalog']" :key="channel" class="rounded-lg border border-slate-800 p-4">
                        <div class="flex items-center justify-between"><span class="text-sm text-slate-200">{{ label(channel) }}</span><button v-if="canManage" class="text-xs text-cyan-300" @click="savePrice(channel, priceSetting(channel))">Lưu</button></div>
                        <select v-model="priceSetting(channel).price_source" class="mt-3 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"><option v-for="source in priceSources" :key="source" :value="source">{{ priceSourceLabel(source) }}</option><option v-for="book in priceBooks.filter((entry) => entry.is_active)" :key="priceBookSource(book)" :value="priceBookSource(book)">{{ priceSourceLabel(priceBookSource(book)) }}</option></select>
                        <select v-model="priceSetting(channel).fallback_policy" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"><option v-for="fallback in fallbackPolicies" :key="fallback" :value="fallback">{{ fallbackLabel(fallback) }}</option></select>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="font-semibold text-white">Chi tiết bảng giá</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="book in priceBooks" :key="`details-${book.id}`" class="rounded-lg border border-slate-800 p-4 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-slate-200">{{ book.name }}</span>
                            <span :class="book.is_active ? 'text-emerald-300' : 'text-amber-300'">{{ book.is_active ? 'Đang dùng' : 'Không còn dùng' }}</span>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-400">
                            <div><dt>Mã bảng giá</dt><dd class="text-slate-200">{{ book.code || '—' }}</dd></div>
                            <div><dt>Mã từ KIOT</dt><dd class="text-slate-200">{{ book.remote_price_book_id }}</dd></div>
                            <div><dt>SKU có giá</dt><dd class="text-slate-200">{{ book.prices_count || 0 }}</dd></div>
                            <div><dt>Giá lớn hơn 0</dt><dd class="text-emerald-300">{{ book.positive_prices_count || 0 }}</dd></div>
                            <div><dt>Giá bằng 0</dt><dd class="text-amber-300">{{ book.zero_prices_count || 0 }}</dd></div>
                            <div><dt>Đồng bộ gần nhất</dt><dd class="text-slate-200">{{ formatTime(book.synced_at) }}</dd></div>
                        </dl>
                    </div>
                    <p v-if="!priceBooks.length" class="text-sm text-slate-500">Chưa có bảng giá nào được đồng bộ.</p>
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
                    <p class="mt-1 text-sm text-slate-400">Xuất sản phẩm thành các cột trong bảng tính để theo dõi, kiểm tra hoặc chia sẻ với đội ngũ.</p>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-slate-500">Trạng thái</dt><dd class="text-slate-200">{{ statusLabel(google.status) }}</dd></div>
                        <div><dt class="text-slate-500">Tài khoản dịch vụ</dt><dd class="text-slate-200">{{ google.service_account_configured ? 'Đã cấu hình' : 'Chưa có' }}</dd></div>
                        <div><dt class="text-slate-500">Lần kiểm tra gần nhất</dt><dd class="text-slate-200">{{ formatTime(google.last_tested_at) }}</dd></div>
                        <div><dt class="text-slate-500">Lần đồng bộ gần nhất</dt><dd class="text-slate-200">{{ formatTime(google.last_success_at) }}</dd></div>
                    </dl>
                    <div class="mt-5 space-y-4">
                        <label class="block text-sm text-slate-300">Mã bảng tính<input v-model="googleForm.spreadsheet_id" :disabled="!canManage" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"></label>
                        <label class="block text-sm text-slate-300">Tên trang tính<input v-model="googleForm.worksheet" :disabled="!canManage" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2"></label>
                        <label class="block text-sm text-slate-300">JSON tài khoản dịch vụ<textarea v-model="googleForm.service_account_json" :disabled="!canManage" rows="5" autocomplete="off" placeholder="Để trống để giữ thông tin xác thực hiện tại" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-xs"></textarea></label>
                        <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="googleForm.is_enabled" :disabled="!canManage" type="checkbox"> Bật đồng bộ Google Sheets</label>
                        <button v-if="canManage" :disabled="googleForm.processing" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white">Lưu cấu hình</button>
                    </div>
                </form>

                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="font-semibold text-white">Thao tác</h2>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button :disabled="!canManage" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/google-sheets/test')">Kiểm tra kết nối</button>
                        <button :disabled="!canManage" class="rounded-lg border border-cyan-500/40 px-4 py-2 text-sm text-cyan-300 disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/google-sheets/dry-run')">Chạy thử</button>
                        <button :disabled="!canManage || !google.is_enabled" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm text-white disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/google-sheets/sync')">Đồng bộ ngay</button>
                    </div>
                    <p v-if="google.last_error_code" class="mt-5 rounded-lg bg-red-500/10 p-3 text-sm text-red-200">{{ feedErrorLabel(google.last_error_code) }}<span v-if="google.last_error_message"> · {{ google.last_error_message }}</span></p>
                </div>
            </section>

            <section v-else class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <template v-for="item in [merchant, meta]" :key="item.channel">
                    <div v-if="activeTab === item.channel">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="font-semibold text-white">{{ label(item.channel) }}</h2>
                                <p class="mt-1 text-sm text-slate-400">{{ description(item.channel) }}</p>
                                <p class="mt-1 text-sm text-slate-400">Mã feed: {{ item.feed_token_configured ? 'Đã cấu hình' : 'Chưa có' }}</p>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ item.feed_path }}?token=••••••••</p>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-slate-300"><input :checked="item.is_enabled" :disabled="!canManage" type="checkbox" @change="toggleChannel(item.channel, $event.target.checked)"> Bật kênh</label>
                        </div>
                        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-4">
                            <div><dt class="text-slate-500">Trạng thái</dt><dd class="text-slate-200">{{ statusLabel(item.status) }}</dd></div>
                            <div><dt class="text-slate-500">Hợp lệ</dt><dd class="text-emerald-300">{{ item.last_run?.items_valid || 0 }}</dd></div>
                            <div><dt class="text-slate-500">Không hợp lệ</dt><dd class="text-amber-300">{{ item.last_run?.items_invalid || 0 }}</dd></div>
                            <div><dt class="text-slate-500">Lần tạo feed gần nhất</dt><dd class="text-slate-200">{{ formatTime(item.last_run?.completed_at) }}</dd></div>
                        </dl>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <button :disabled="!canManage" class="rounded-lg border border-amber-500/40 px-4 py-2 text-sm text-amber-300 disabled:opacity-40" @click="action(`/admin/integrations/catalog-channels/${item.channel}/rotate-token`)">Đổi mã feed</button>
                            <button v-if="item.channel === 'meta_catalog'" :disabled="!canManage" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:opacity-40" @click="action('/admin/integrations/catalog-channels/meta_catalog/test-connection')">Kiểm tra kết nối</button>
                            <button :disabled="!canManage" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-200 disabled:opacity-40" @click="action(`/admin/integrations/catalog-channels/${item.channel}/validate`)">Kiểm tra feed</button>
                            <button :disabled="!canManage || !item.is_enabled" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm text-white disabled:opacity-40" @click="action(`/admin/integrations/catalog-channels/${item.channel}/rebuild`)">Tạo lại feed</button>
                        </div>
                    </div>
                </template>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                <div class="border-b border-slate-800 px-5 py-4"><h2 class="font-semibold text-white">Lịch sử chạy gần đây</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-800/40 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Kênh</th><th class="px-4 py-3">Kiểu chạy</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Hợp lệ / Không hợp lệ</th><th class="px-4 py-3">Thời gian</th></tr></thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="run in recentRuns" :key="run.id"><td class="px-4 py-3 text-cyan-300">{{ label(run.channel) }}</td><td class="px-4 py-3 text-slate-300">{{ runModeLabel(run.mode) }}</td><td class="px-4 py-3 text-slate-300">{{ statusLabel(run.status) }}</td><td class="px-4 py-3 text-slate-400">{{ run.items_valid }} / {{ run.items_invalid }}</td><td class="px-4 py-3 text-slate-500">{{ formatTime(run.created_at) }}</td></tr>
                            <tr v-if="!recentRuns.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có lượt chạy nào.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
