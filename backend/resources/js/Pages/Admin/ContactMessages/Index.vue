<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ messages: Object, filters: Object, counts: Object, canManage: Boolean });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const boxes = [
    { key: 'inbox', label: 'Hộp thư' },
    { key: 'unread', label: 'Chưa đọc' },
    { key: 'read', label: 'Đã đọc' },
    { key: 'archived', label: 'Lưu trữ' },
    { key: 'all', label: 'Tất cả' },
];

function go(params) {
    const query = { box: props.filters?.box || 'inbox', search: search.value, ...params };
    Object.keys(query).forEach(key => { if (!query[key] || (key === 'box' && query[key] === 'inbox')) delete query[key]; });
    router.get('/admin/contact-messages', query, { preserveState: true, replace: true });
}
function setStatus(message, status) {
    router.patch(`/admin/contact-messages/${message.id}/status`, { status }, { preserveScroll: true });
}
function destroy(message) {
    if (confirm(`Xóa tin nhắn của ${message.name}? Không khôi phục được.`)) router.delete(`/admin/contact-messages/${message.id}`, { preserveScroll: true });
}
function formatDate(value) {
    return value ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : '';
}
</script>

<template>
<AdminLayout title="Hộp thư liên hệ">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-200">Hộp thư liên hệ</h3>
            <p class="text-xs text-slate-500">Tin khách gửi từ form trang Liên hệ. Mở tin để tự đánh dấu đã đọc.</p>
        </div>
        <form class="flex gap-2" @submit.prevent="go({ page: null })">
            <input v-model="search" type="search" placeholder="Tìm tên, SĐT, email, nội dung…" class="w-72 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
            <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm">Tìm</button>
        </form>
    </div>
    <div class="flex flex-wrap gap-2 mb-4">
        <button v-for="box in boxes" :key="box.key" type="button" class="px-3 py-1.5 rounded-lg text-sm border"
            :class="(filters?.box || 'inbox') === box.key ? 'bg-cyan-600 border-cyan-600 text-white' : 'border-slate-700/50 text-slate-300 hover:bg-slate-800/60'"
            @click="go({ box: box.key, page: null })">
            {{ box.label }}
            <span v-if="box.key === 'unread' && counts?.unread" class="ml-1 rounded-full bg-orange-500 px-1.5 text-[10px] font-bold text-white">{{ counts.unread }}</span>
            <span v-if="box.key === 'archived' && counts?.archived" class="ml-1 text-xs text-slate-400">{{ counts.archived }}</span>
        </button>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Người gửi</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Nội dung</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Thời gian</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="message in messages.data" :key="message.id" class="hover:bg-slate-800/40" :class="message.status === 'new' ? 'bg-cyan-500/5' : ''">
                    <td class="px-4 py-3 align-top">
                        <Link :href="`/admin/contact-messages/${message.id}`" class="text-sm text-slate-200 hover:text-cyan-400" :class="message.status === 'new' ? 'font-bold' : 'font-medium'">
                            <span v-if="message.status === 'new'" class="mr-1 inline-block h-2 w-2 rounded-full bg-orange-500" aria-label="Chưa đọc"></span>{{ message.name }}
                        </Link>
                        <p class="text-xs text-slate-500">{{ message.phone }}<span v-if="message.email"> · {{ message.email }}</span></p>
                    </td>
                    <td class="px-4 py-3 align-top max-w-xl">
                        <Link :href="`/admin/contact-messages/${message.id}`" class="block text-sm text-slate-300 hover:text-cyan-400">
                            <span v-if="message.subject" class="font-medium text-slate-200">{{ message.subject }} — </span>{{ message.message.length > 140 ? `${message.message.slice(0, 140)}…` : message.message }}
                        </Link>
                        <span v-if="message.status === 'archived'" class="mt-1 inline-block rounded-full bg-slate-800/60 px-2 py-0.5 text-[10px] text-slate-400">Lưu trữ</span>
                    </td>
                    <td class="px-4 py-3 align-top text-xs text-slate-400 whitespace-nowrap">{{ formatDate(message.created_at) }}</td>
                    <td class="px-4 py-3 align-top text-right whitespace-nowrap space-x-2">
                        <template v-if="canManage">
                            <button v-if="message.status === 'new'" type="button" class="text-cyan-500 hover:text-cyan-300 text-xs" @click="setStatus(message, 'read')">Đã đọc</button>
                            <button v-else-if="message.status === 'read'" type="button" class="text-cyan-500 hover:text-cyan-300 text-xs" @click="setStatus(message, 'new')">Chưa đọc</button>
                            <button v-if="message.status !== 'archived'" type="button" class="text-slate-400 hover:text-slate-200 text-xs" @click="setStatus(message, 'archived')">Lưu trữ</button>
                            <button v-else type="button" class="text-slate-400 hover:text-slate-200 text-xs" @click="setStatus(message, 'read')">Bỏ lưu trữ</button>
                            <button type="button" class="text-red-600 hover:text-red-800 text-xs" @click="destroy(message)">Xóa</button>
                        </template>
                    </td>
                </tr>
                <tr v-if="!messages.data?.length"><td colspan="4" class="px-4 py-8 text-center text-slate-500">Không có tin nhắn</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="messages.last_page > 1" class="mt-4 flex flex-wrap gap-1">
        <template v-for="link in messages.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" preserve-state class="px-3 py-1.5 text-sm rounded-lg border border-slate-700/50" :class="link.active ? 'bg-cyan-600 text-white' : 'text-slate-300'" v-html="link.label" />
            <span v-else class="px-3 py-1.5 text-sm text-slate-600" v-html="link.label" />
        </template>
    </div>
</AdminLayout>
</template>
