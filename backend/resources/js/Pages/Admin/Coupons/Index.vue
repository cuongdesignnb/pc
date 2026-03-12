<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ coupons: Object, filters: Object });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(() => router.get('/admin/coupons', { search: search.value || undefined }, { preserveState: true, replace: true }), 400); });

function destroy(id) { if (confirm('Xóa mã giảm giá này?')) router.delete(`/admin/coupons/${id}`); }
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(p) + '₫'; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
</script>
<template>
<AdminLayout title="Mã giảm giá">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <input v-model="search" placeholder="Tìm mã..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48">
        </div>
        <Link href="/admin/coupons/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">+ Thêm mã</Link>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mã</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loại</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Giá trị</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Đã dùng</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hiệu lực</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="c in coupons.data" :key="c.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono font-medium text-gray-900">{{ c.code }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ c.type === 'percentage' ? 'Phần trăm' : 'Cố định' }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium">{{ c.type === 'percentage' ? c.value + '%' : formatPrice(c.value) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ c.used_count }}{{ c.max_uses ? '/' + c.max_uses : '' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(c.starts_at) }} → {{ formatDate(c.expires_at) }}</td>
                    <td class="px-4 py-3 text-center"><span :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ c.is_active ? 'Hoạt động' : 'Tắt' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/coupons/${c.id}/edit`" class="text-indigo-600 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(c.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!coupons.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Chưa có mã giảm giá</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
