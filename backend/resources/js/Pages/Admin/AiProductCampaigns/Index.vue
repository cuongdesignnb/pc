<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    campaigns: { type: Object, default: () => ({ data: [] }) },
    products: { type: Object, default: () => ({ data: [], links: [] }) },
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    configured: Boolean,
    researchEnabled: Boolean,
    contactFooterConfigured: Boolean,
    maxItems: { type: Number, default: 100 },
    activeFilters: { type: Object, default: () => ({}) },
});

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const filters = ref({
    search: props.activeFilters.search || '',
    category_id: props.activeFilters.category_id || '',
    brand_id: props.activeFilters.brand_id || '',
    status: props.activeFilters.status || 'all',
    missing_description: Boolean(props.activeFilters.missing_description),
    missing_specifications: Boolean(props.activeFilters.missing_specifications),
});
const selected = ref(new Set());
const submitting = ref(false);
const message = ref('');
const error = ref('');
const campaign = ref({
    name: 'Viết lại nội dung sản phẩm',
    mode: 'draft',
    technical_heading: 'auto',
    use_web_research: false,
    append_contact_footer: false,
    scheduled_at: new Date(Date.now() + 60_000).toISOString().slice(0, 16),
});

const pageProducts = computed(() => props.products?.data || []);
const selectedCount = computed(() => selected.value.size);
const pageSelected = computed(() => pageProducts.value.length > 0 && pageProducts.value.every((product) => selected.value.has(product.id)));

function reloadProducts() {
    router.get('/admin/ai-product-campaigns', { ...filters.value }, { preserveState: true, replace: true, only: ['products', 'activeFilters'] });
}

function toggleProduct(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}

function togglePage() {
    const next = new Set(selected.value);
    if (pageSelected.value) pageProducts.value.forEach((product) => next.delete(product.id));
    else pageProducts.value.forEach((product) => next.add(product.id));
    selected.value = next;
}

function clearSelection() {
    selected.value = new Set();
}

function runNow() {
    campaign.value.scheduled_at = new Date().toISOString().slice(0, 16);
    submitCampaign();
}

async function submitCampaign() {
    message.value = '';
    error.value = '';
    if (!selectedCount.value) {
        error.value = 'Hãy chọn ít nhất một sản phẩm.';
        return;
    }
    if (selectedCount.value > props.maxItems) {
        error.value = `Mỗi campaign tối đa ${props.maxItems} sản phẩm.`;
        return;
    }
    submitting.value = true;
    try {
        const response = await window.axios.post('/admin/ai-product-campaigns', {
            ...campaign.value,
            selected_product_ids: Array.from(selected.value),
            filters: filters.value,
        }, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        router.visit(`/admin/ai-product-campaigns/${response.data.campaign.id}`);
    } catch (e) {
        error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat()[0] || 'Không thể tạo campaign.';
    } finally {
        submitting.value = false;
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('vi-VN') : '—';
}

function statusLabel(status) {
    return { pending: 'Chờ chạy', processing: 'Đang chạy', completed: 'Hoàn tất', partial_failed: 'Cần xem lại', cancelled: 'Đã hủy' }[status] || status;
}
</script>

<template>
<AdminLayout title="Viết lại sản phẩm">
    <div class="max-w-7xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-cyan-400">AI nội dung sản phẩm</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-100">Viết lại sản phẩm hàng loạt</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Chọn sản phẩm xuyên nhiều trang, tạo bản nháp hoặc cập nhật có kiểm soát. Giá, tồn kho, SKU, slug, ảnh, variant, danh mục, KIOT và thông số PC Builder không bị AI thay đổi.</p>
            </div>
            <a href="/admin/ai-writer" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:border-cyan-500 hover:text-cyan-300">← AI Writer cũ</a>
        </div>

        <div v-if="!configured" class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">Chưa cấu hình AI nội dung. Campaign vẫn có thể tạo để chuẩn bị, nhưng worker sẽ cần API key trước khi chạy.</div>
        <div v-if="!researchEnabled" class="rounded-xl border border-slate-700 bg-slate-900/70 p-4 text-sm text-slate-400">Tra cứu thông số web đang tắt. Chỉ dữ liệu mô tả/thông số đã có sẵn sẽ được dùng; bật tính năng và cấu hình domain chính hãng trong Cài đặt AI khi cần.</div>
        <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-200">{{ error }}</div>
        <div v-if="message" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">{{ message }}</div>

        <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-lg font-semibold text-slate-100">1. Lọc và chọn sản phẩm</h2><p class="mt-1 text-xs text-slate-500">Đã chọn {{ selectedCount }}/{{ maxItems }}. Lựa chọn được giữ khi chuyển trang.</p></div>
                <button v-if="selectedCount" type="button" @click="clearSelection" class="text-sm text-red-300 hover:text-red-200">Bỏ chọn tất cả</button>
            </div>
            <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                <input v-model="filters.search" @keyup.enter="reloadProducts" placeholder="Tên hoặc SKU" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 xl:col-span-2" />
                <select v-model="filters.category_id" @change="reloadProducts" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100"><option value="">Tất cả danh mục</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                <select v-model="filters.brand_id" @change="reloadProducts" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100"><option value="">Tất cả thương hiệu</option><option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option></select>
                <select v-model="filters.status" @change="reloadProducts" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100"><option value="all">Mọi trạng thái</option><option value="active">Đang hiển thị</option><option value="hidden">Ẩn / ngừng bán</option></select>
                <button type="button" @click="reloadProducts" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-500">Lọc</button>
            </div>
            <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-300"><label class="flex items-center gap-2"><input v-model="filters.missing_description" @change="reloadProducts" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500" /> Thiếu mô tả</label><label class="flex items-center gap-2"><input v-model="filters.missing_specifications" @change="reloadProducts" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500" /> Thiếu thông số</label></div>

            <div class="mt-5 overflow-x-auto rounded-lg border border-slate-800">
                <table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-800/70 text-xs uppercase text-slate-400"><tr><th class="w-12 px-4 py-3"><input :checked="pageSelected" @change="togglePage" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500" /></th><th class="px-4 py-3">Sản phẩm</th><th class="px-4 py-3">Danh mục / hãng</th><th class="px-4 py-3">Nội dung</th><th class="px-4 py-3">Thông số</th></tr></thead><tbody class="divide-y divide-slate-800"><tr v-for="product in pageProducts" :key="product.id" class="hover:bg-slate-800/40"><td class="px-4 py-3"><input :checked="selected.has(product.id)" @change="toggleProduct(product.id)" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500" /></td><td class="px-4 py-3"><div class="font-medium text-slate-100">{{ product.name }}</div><div class="mt-1 text-xs text-slate-500">{{ product.sku }}</div></td><td class="px-4 py-3 text-slate-400">{{ product.category?.name || '—' }}<br /><span class="text-xs">{{ product.brand?.name || 'Chưa có hãng' }}</span></td><td class="px-4 py-3"><span :class="product.description ? 'text-emerald-300' : 'text-amber-300'">{{ product.description ? 'Đã có' : 'Thiếu' }}</span></td><td class="px-4 py-3"><span :class="product.specifications_text || product.specifications?.length ? 'text-emerald-300' : 'text-amber-300'">{{ product.specifications_text || product.specifications?.length ? 'Có dữ liệu' : 'Thiếu' }}</span></td></tr><tr v-if="!pageProducts.length"><td colspan="5" class="px-4 py-10 text-center text-slate-500">Không có sản phẩm phù hợp.</td></tr></tbody></table>
            </div>
            <div class="mt-4 flex flex-wrap gap-2"><template v-for="link in products.links || []" :key="link.label"><a v-if="link.url" :href="link.url" @click.prevent="router.get(link.url, {}, { preserveState: true, only: ['products', 'activeFilters'] })" :class="['rounded border px-3 py-1 text-xs', link.active ? 'border-cyan-500 bg-cyan-500/10 text-cyan-300' : 'border-slate-700 text-slate-400 hover:text-slate-200']" v-html="link.label"></a></template></div>
        </section>

        <section class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4"><h2 class="text-lg font-semibold text-slate-100">2. Tạo campaign</h2><p class="mt-1 text-xs text-slate-500">Mặc định là lưu nháp. Chế độ cập nhật ngay chỉ áp dụng các trường nội dung được cho phép và vẫn kiểm tra snapshot.</p></div>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-sm text-slate-300 md:col-span-2">Tên campaign<input v-model="campaign.name" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100" /></label>
                <label class="text-sm text-slate-300">Chế độ<select v-model="campaign.mode" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100"><option value="draft">Lưu nháp — không sửa sản phẩm</option><option value="publish">Cập nhật ngay — có kiểm soát</option></select></label>
                <label class="text-sm text-slate-300">Thời điểm chạy<input v-model="campaign.scheduled_at" required type="datetime-local" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100" /></label>
                <label class="text-sm text-slate-300">Tiêu đề phần thông số<select v-model="campaign.technical_heading" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-slate-100"><option value="auto">Tự động theo danh mục</option><option value="configuration">Cấu hình chi tiết</option><option value="specifications">Thông số kỹ thuật</option></select></label>
                <div class="space-y-3 pt-6 text-sm text-slate-300"><label class="flex items-center gap-2"><input v-model="campaign.use_web_research" :disabled="!researchEnabled" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500" /> Tra cứu thêm nguồn chính hãng đã whitelist</label><label class="flex items-center gap-2"><input v-model="campaign.append_contact_footer" :disabled="!contactFooterConfigured" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-cyan-500" /> Thêm footer liên hệ cuối bài</label><p v-if="campaign.append_contact_footer && contactFooterConfigured" class="text-xs text-emerald-300">Footer liên hệ đã đủ cấu hình và sẽ dùng settings hiện tại của storefront.</p><p v-else-if="!contactFooterConfigured" class="text-xs text-slate-500">Footer đang tắt hoặc chưa đủ dữ liệu trong Cài đặt → Liên hệ.</p></div>
            </div>
            <div v-if="campaign.mode === 'publish'" class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-200">Publish sẽ chỉ ghi mô tả ngắn, mô tả, SEO và thông số văn bản khi đủ điều kiện. Thông số structured/PC Builder/KIOT không bị ghi đè.</div>
            <div class="mt-5 flex flex-wrap justify-end gap-3"><button type="button" @click="runNow" :disabled="submitting" class="rounded-lg bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-cyan-500 disabled:opacity-50">{{ submitting ? 'Đang tạo…' : 'Chạy ngay' }}</button><button type="button" @click="submitCampaign" :disabled="submitting" class="rounded-lg border border-slate-600 px-5 py-2.5 text-sm font-semibold text-slate-200 hover:border-cyan-500 disabled:opacity-50">Đặt lịch campaign</button></div>
        </section>

        <section class="rounded-xl border border-slate-800 bg-slate-900 p-5"><div class="mb-4 flex items-center justify-between"><div><h2 class="text-lg font-semibold text-slate-100">Campaign gần đây</h2><p class="mt-1 text-xs text-slate-500">Mỗi item có bản snapshot, kết quả AI, nguồn trích dẫn và trạng thái riêng.</p></div></div><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="border-b border-slate-800 text-xs uppercase text-slate-500"><tr><th class="px-3 py-3">Tên</th><th class="px-3 py-3">Chế độ</th><th class="px-3 py-3">Tiến độ</th><th class="px-3 py-3">Lịch chạy</th><th class="px-3 py-3">Trạng thái</th></tr></thead><tbody class="divide-y divide-slate-800"><tr v-for="item in campaigns?.data || []" :key="item.id"><td class="px-3 py-3"><a :href="`/admin/ai-product-campaigns/${item.id}`" class="font-medium text-cyan-300 hover:text-cyan-200">{{ item.name }}</a></td><td class="px-3 py-3 text-slate-400">{{ item.mode === 'publish' ? 'Cập nhật ngay' : 'Lưu nháp' }}</td><td class="px-3 py-3 text-slate-400">{{ item.applied_items + item.draft_items }}/{{ item.total_items }} · lỗi {{ item.failed_items }}</td><td class="px-3 py-3 text-slate-400">{{ formatDate(item.scheduled_at) }}</td><td class="px-3 py-3"><span class="rounded-full bg-slate-800 px-2 py-1 text-xs text-slate-300">{{ statusLabel(item.status) }}</span></td></tr><tr v-if="!(campaigns?.data || []).length"><td colspan="5" class="px-3 py-8 text-center text-slate-500">Chưa có campaign.</td></tr></tbody></table></div></section>
    </div>
</AdminLayout>
</template>
