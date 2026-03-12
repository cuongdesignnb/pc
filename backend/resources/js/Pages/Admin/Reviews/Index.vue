<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ reviews: Object, filters: Object });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const filterApproved = ref(props.filters?.is_approved ?? '');
const filterRating = ref(props.filters?.rating || '');
const replyingTo = ref(null);
const replyText = ref('');

let timer;
watch([search, filterApproved, filterRating], () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/reviews', {
        search: search.value || undefined,
        is_approved: filterApproved.value !== '' ? filterApproved.value : undefined,
        rating: filterRating.value || undefined,
    }, { preserveState: true, replace: true }), 400);
});

function approve(id) { router.patch(`/admin/reviews/${id}/approve`, {}, { preserveState: true }); }
function reject(id) { router.patch(`/admin/reviews/${id}/reject`, {}, { preserveState: true }); }
function destroy(id) { if (confirm('Xóa đánh giá?')) router.delete(`/admin/reviews/${id}`); }
function submitReply(id) {
    router.post(`/admin/reviews/${id}/reply`, { admin_reply: replyText.value }, { preserveState: true, onSuccess: () => { replyingTo.value = null; replyText.value = ''; } });
}
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
</script>
<template>
<AdminLayout title="Đánh giá">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <input v-model="search" placeholder="Tìm sản phẩm..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48">
            <select v-model="filterApproved" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tất cả</option><option value="1">Đã duyệt</option><option value="0">Chờ duyệt</option>
            </select>
            <select v-model="filterRating" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Mọi sao</option>
                <option v-for="s in [5,4,3,2,1]" :value="s">{{ s }} sao</option>
            </select>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Khách hàng</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Đánh giá</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nội dung</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ngày</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <template v-for="r in reviews.data" :key="r.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 max-w-[200px] truncate">{{ r.product?.name || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ r.user?.name || '—' }}</td>
                        <td class="px-4 py-3 text-center text-yellow-400 text-sm">{{ '★'.repeat(r.rating) }}{{ '☆'.repeat(5 - r.rating) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 max-w-[300px]">
                            <p v-if="r.title" class="font-medium">{{ r.title }}</p>
                            <p class="truncate">{{ r.body }}</p>
                            <p v-if="r.admin_reply" class="text-xs text-indigo-600 mt-1 italic">Đã trả lời: {{ r.admin_reply }}</p>
                        </td>
                        <td class="px-4 py-3 text-center"><span :class="r.is_approved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ r.is_approved ? 'Đã duyệt' : 'Chờ duyệt' }}</span></td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(r.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button v-if="!r.is_approved" @click="approve(r.id)" class="text-green-600 hover:text-green-800 text-xs px-1">Duyệt</button>
                                <button v-if="r.is_approved" @click="reject(r.id)" class="text-yellow-600 hover:text-yellow-800 text-xs px-1">Bỏ duyệt</button>
                                <button @click="replyingTo = replyingTo === r.id ? null : r.id; replyText = r.admin_reply || ''" class="text-indigo-600 hover:text-indigo-800 text-xs px-1">Trả lời</button>
                                <button @click="destroy(r.id)" class="text-red-600 hover:text-red-800 text-xs px-1">Xóa</button>
                            </div>
                        </td>
                    </tr>
                    <!-- Reply row -->
                    <tr v-if="replyingTo === r.id">
                        <td colspan="7" class="px-4 py-3 bg-gray-50">
                            <div class="flex gap-2 max-w-xl">
                                <input v-model="replyText" placeholder="Nhập phản hồi..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <button @click="submitReply(r.id)" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Gửi</button>
                                <button @click="replyingTo = null" class="px-3 py-2 text-gray-500 text-sm hover:bg-gray-200 rounded-lg">Hủy</button>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr v-if="!reviews.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Chưa có đánh giá</td></tr>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div v-if="reviews.last_page > 1" class="mt-4 flex justify-center gap-1">
        <template v-for="link in reviews.links" :key="link.label">
            <button v-if="link.url" @click="router.get(link.url, {}, { preserveState: true })" :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-3 py-1 border rounded text-sm" v-html="link.label"></button>
            <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label"></span>
        </template>
    </div>
</AdminLayout>
</template>
