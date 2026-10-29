<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ affiliates: Object, filters: Object });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 400); });
function applyFilters() {
    router.get('/admin/affiliates', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
}
function setStatus(affiliate, value) {
    if (value === 'locked' && !confirm(`Khoá CTV ${affiliate.code || affiliate.user?.name}? Khách của CTV này sẽ không còn tính hoa hồng.`)) return;
    router.patch(`/admin/affiliates/${affiliate.id}/status`, { status: value }, { preserveScroll: true });
}
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(Number(p || 0)) + '₫'; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }

const statusLabel = { pending: 'Chờ duyệt', active: 'Đang hoạt động', locked: 'Đã khoá' };
const statusColor = { pending: 'bg-yellow-100 text-yellow-700', active: 'bg-green-100 text-emerald-700', locked: 'bg-red-100 text-red-700' };
</script>
<template>
<AdminLayout title="Cộng tác viên">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <h3 class="text-lg font-semibold text-slate-200">Cộng tác viên</h3>
        <div class="flex gap-2">
            <select v-model="status" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <option value="">Tất cả trạng thái</option>
                <option v-for="(label, key) in statusLabel" :key="key" :value="key">{{ label }}</option>
            </select>
            <input v-model="search" placeholder="Mã CTV, tên, SĐT, email..." class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm w-56">
        </div>
    </div>
    <div class="bg-slate-900 rounded-lg border border-slate-800/60 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Mã</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">CTV</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">SĐT</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Khách</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Chờ duyệt</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Được rút</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Đăng ký</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="a in affiliates.data" :key="a.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 text-sm font-medium text-cyan-500">{{ a.code || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-200">{{ a.user?.name }}<div class="text-xs text-slate-500">{{ a.user?.email }}</div></td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ a.phone }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ a.customers_count }}</td>
                    <td class="px-4 py-3 text-sm text-slate-300 text-right">{{ formatPrice(a.pending_amount) }}</td>
                    <td class="px-4 py-3 text-sm text-emerald-400 text-right">{{ formatPrice(a.approved_amount) }}</td>
                    <td class="px-4 py-3 text-center"><span :class="statusColor[a.status]" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel[a.status] }}</span></td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ formatDate(a.created_at) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-3 text-sm">
                        <button v-if="a.status !== 'active'" type="button" @click="setStatus(a, 'active')" class="text-emerald-400 hover:text-emerald-300">{{ a.status === 'pending' ? 'Duyệt' : 'Mở khoá' }}</button>
                        <button v-if="a.status === 'active'" type="button" @click="setStatus(a, 'locked')" class="text-red-400 hover:text-red-300">Khoá</button>
                        <Link :href="`/admin/affiliates/${a.id}`" class="text-cyan-500 hover:text-cyan-400">Chi tiết</Link>
                    </td>
                </tr>
                <tr v-if="!affiliates.data?.length"><td colspan="9" class="px-4 py-8 text-center text-slate-500">Chưa có cộng tác viên</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="affiliates.last_page > 1" class="mt-4 flex justify-end gap-1">
        <button v-for="link in affiliates.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })"
            :disabled="!link.url" :class="link.active ? 'bg-cyan-600 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800/40'"
            class="px-3 py-1.5 text-sm border border-slate-700/50 rounded disabled:opacity-40" v-html="link.label"/>
    </div>
</AdminLayout>
</template>
