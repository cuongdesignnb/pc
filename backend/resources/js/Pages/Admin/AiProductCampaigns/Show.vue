<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ campaign: { type: Object, required: true } });
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const busy = ref(false);
const error = ref('');
const selected = ref(new Set());

const items = computed(() => props.campaign.items || []);
const selectable = computed(() => items.value.filter((item) => ['draft', 'needs_review'].includes(item.status)));
const allSelectable = computed(() => selectable.value.length > 0 && selectable.value.every((item) => selected.value.has(item.id)));

function statusLabel(status) {
    return { pending: 'Chờ chạy', processing: 'Đang chạy', draft: 'Bản nháp', applied: 'Đã áp dụng', needs_review: 'Cần xem lại', failed: 'Lỗi', skipped: 'Bỏ qua' }[status] || status;
}

function statusClass(status) {
    return { applied: 'bg-emerald-500/15 text-emerald-300', draft: 'bg-cyan-500/15 text-cyan-300', needs_review: 'bg-amber-500/15 text-amber-300', failed: 'bg-red-500/15 text-red-300' }[status] || 'bg-slate-800 text-slate-300';
}

function toggleItem(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}

function toggleAll() {
    selected.value = allSelectable.value ? new Set() : new Set(selectable.value.map((item) => item.id));
}

async function post(url, data = {}) {
    busy.value = true;
    error.value = '';
    try {
        await window.axios.post(url, data, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e.response?.data?.message || 'Không thể thực hiện thao tác.';
    } finally {
        busy.value = false;
    }
}

function applySelected() {
    if (selected.value.size) post(`/admin/ai-product-campaigns/${props.campaign.id}/apply`, { item_ids: Array.from(selected.value) });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('vi-VN') : '—';
}
</script>

<template>
<AdminLayout title="Chi tiết campaign sản phẩm">
    <div class="max-w-7xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><a href="/admin/ai-product-campaigns" class="text-sm text-cyan-300 hover:text-cyan-200">← Viết lại sản phẩm</a><h1 class="mt-2 text-2xl font-bold text-slate-100">{{ campaign.name }}</h1><p class="mt-1 text-sm text-slate-400">Tạo lúc {{ formatDate(campaign.created_at) }} · {{ campaign.mode === 'publish' ? 'Cập nhật ngay' : 'Lưu nháp' }} · chạy {{ formatDate(campaign.scheduled_at) }}</p></div><div class="flex flex-wrap gap-2"><button type="button" @click="post(`/admin/ai-product-campaigns/${campaign.id}/run`)" :disabled="busy || campaign.status === 'cancelled'" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Chạy / chạy lại</button><button type="button" @click="post(`/admin/ai-product-campaigns/${campaign.id}/cancel`)" :disabled="busy || ['completed', 'cancelled'].includes(campaign.status)" class="rounded-lg border border-red-500/40 px-4 py-2 text-sm font-semibold text-red-300 disabled:opacity-50">Hủy campaign</button></div></div>
        <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-200">{{ error }}</div>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6"><div class="rounded-xl border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Trạng thái</p><p class="mt-2 font-semibold text-slate-100">{{ campaign.status }}</p></div><div class="rounded-xl border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Tổng</p><p class="mt-2 font-semibold text-slate-100">{{ campaign.total_items }}</p></div><div class="rounded-xl border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Đang chờ</p><p class="mt-2 font-semibold text-slate-100">{{ campaign.pending_items }}</p></div><div class="rounded-xl border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Bản nháp</p><p class="mt-2 font-semibold text-cyan-300">{{ campaign.draft_items }}</p></div><div class="rounded-xl border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Đã áp dụng</p><p class="mt-2 font-semibold text-emerald-300">{{ campaign.applied_items }}</p></div><div class="rounded-xl border border-slate-800 bg-slate-900 p-4"><p class="text-xs text-slate-500">Lỗi / review</p><p class="mt-2 font-semibold text-amber-300">{{ campaign.failed_items }} / {{ campaign.review_items }}</p></div></section>

        <section class="rounded-xl border border-slate-800 bg-slate-900 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold text-slate-100">Các sản phẩm trong campaign</h2><p class="mt-1 text-xs text-slate-500">Snapshot dùng để phát hiện sản phẩm bị sửa trong lúc AI chạy.</p></div><div class="flex gap-2"><button type="button" @click="toggleAll" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300">{{ allSelectable ? 'Bỏ chọn bản nháp' : 'Chọn bản nháp' }}</button><button type="button" @click="applySelected" :disabled="busy || !selected.size" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">Áp dụng {{ selected.size || '' }}</button></div></div>
            <div class="mt-5 space-y-4"><article v-for="item in items" :key="item.id" class="rounded-xl border border-slate-800 bg-slate-950/40 p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div class="flex gap-3"><input v-if="['draft', 'needs_review'].includes(item.status)" :checked="selected.has(item.id)" @change="toggleItem(item.id)" type="checkbox" class="mt-1 rounded border-slate-600 bg-slate-800 text-cyan-500" /><div><h3 class="font-semibold text-slate-100">{{ item.product?.name || `Product #${item.product_id}` }}</h3><p class="mt-1 text-xs text-slate-500">SKU: {{ item.product?.sku || '—' }} · thử {{ item.attempts }}/3 · tạo {{ formatDate(item.generated_at) }}</p></div></div><div class="flex flex-wrap items-center gap-2"><span :class="['rounded-full px-2.5 py-1 text-xs font-medium', statusClass(item.status)]">{{ statusLabel(item.status) }}</span><button v-if="item.status === 'failed' && item.attempts < 3" type="button" @click="post(`/admin/ai-product-campaign-items/${item.id}/retry`)" :disabled="busy" class="text-xs text-cyan-300 hover:text-cyan-200">Retry</button><button v-if="['draft', 'needs_review'].includes(item.status)" type="button" @click="post(`/admin/ai-product-campaign-items/${item.id}/apply`)" :disabled="busy" class="rounded border border-emerald-500/40 px-3 py-1 text-xs text-emerald-300 hover:bg-emerald-500/10">Áp dụng item</button></div></div>
                <div v-if="item.error_message" class="mt-3 rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-200">{{ item.error_message }}</div>
                <div v-if="item.warnings?.length" class="mt-3 rounded-lg border border-amber-500/20 bg-amber-500/10 p-3 text-sm text-amber-200"><p v-for="warning in item.warnings" :key="warning">{{ warning }}</p></div>
                <details class="mt-4 rounded-lg border border-slate-800 p-4"><summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-500">Diff trước / sau</summary><div class="mt-3 grid gap-3 md:grid-cols-2"><div><p class="text-xs font-semibold text-slate-500">Trước khi chạy</p><dl class="mt-2 space-y-2 text-xs text-slate-400"><div><dt class="text-slate-500">Mô tả ngắn</dt><dd class="whitespace-pre-line">{{ item.source_snapshot?.short_description || '—' }}</dd></div><div><dt class="text-slate-500">Meta title</dt><dd>{{ item.source_snapshot?.meta_title || '—' }}</dd></div><div><dt class="text-slate-500">Meta description</dt><dd>{{ item.source_snapshot?.meta_description || '—' }}</dd></div></dl></div><div><p class="text-xs font-semibold text-slate-500">Sau khi AI đề xuất</p><dl class="mt-2 space-y-2 text-xs text-slate-300"><div><dt class="text-slate-500">Mô tả ngắn</dt><dd class="whitespace-pre-line">{{ item.generated_payload.short_description || '—' }}</dd></div><div><dt class="text-slate-500">Meta title</dt><dd>{{ item.generated_payload.meta_title || '—' }}</dd></div><div><dt class="text-slate-500">Meta description</dt><dd>{{ item.generated_payload.meta_description || '—' }}</dd></div></dl></div></div></details><div v-if="item.generated_payload" class="mt-4 grid gap-4 lg:grid-cols-2"><div class="rounded-lg border border-slate-800 p-4"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Nội dung đề xuất</p><div class="prose prose-invert max-w-none text-sm" v-html="item.generated_payload.content"></div><p class="mt-4 text-xs text-slate-500">{{ item.generated_payload.meta_title }}</p><p class="mt-1 text-xs text-slate-500">{{ item.generated_payload.meta_description }}</p></div><div class="rounded-lg border border-slate-800 p-4"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Thông số / nguồn</p><p class="text-sm text-slate-300">Tiêu đề: {{ item.generated_payload.technical_heading || 'Thông số kỹ thuật' }}</p><p class="mt-1 text-sm text-slate-300">Nội dung: {{ item.generated_payload.content_heading || 'Thông tin sản phẩm' }}</p><p v-if="item.generated_payload.research" class="mt-2 text-xs text-slate-400">Đối chiếu nguồn: {{ item.generated_payload.research.verified ? 'Đã xác thực' : 'Chưa xác thực' }} · model khớp: {{ item.generated_payload.research.model_match ? 'Có' : 'Không' }} · nguồn: {{ item.generated_payload.research.source_count || 0 }}</p><ul v-if="item.generated_payload.proposed_specifications?.length" class="mt-3 space-y-1 text-sm text-slate-300"><li v-for="spec in item.generated_payload.proposed_specifications" :key="`${spec.label}-${spec.value}`">{{ spec.label }}: {{ spec.value }}<span v-if="spec.unit"> {{ spec.unit }}</span></li></ul><p v-else class="mt-3 text-sm text-slate-500">Không có thông số mới được xác thực.</p><div v-if="item.research_sources?.length" class="mt-4 border-t border-slate-800 pt-3"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nguồn chính hãng</p><a v-for="source in item.research_sources" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="mt-2 block truncate text-sm text-cyan-300 hover:text-cyan-200">{{ source.title || source.url }}</a></div></div></div>
            </article><div v-if="!items.length" class="py-10 text-center text-slate-500">Campaign chưa có sản phẩm.</div></div>
        </section>
    </div>
</AdminLayout>
</template>
