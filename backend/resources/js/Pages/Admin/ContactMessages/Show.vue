<script setup>
import { computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ message: Object, canManage: Boolean });
const flash = computed(() => usePage().props.flash);
const statusLabel = computed(() => ({ new: 'Chưa đọc', read: 'Đã đọc', archived: 'Lưu trữ' }[props.message.status] || props.message.status));
const telHref = computed(() => props.message.phone ? `tel:${props.message.phone.replace(/[^0-9+]/g, '')}` : '');
const zaloHref = computed(() => {
    const digits = (props.message.phone || '').replace(/\D/g, '');
    return digits ? `https://zalo.me/${digits.startsWith('84') ? `0${digits.slice(2)}` : digits}` : '';
});

function setStatus(status) {
    router.patch(`/admin/contact-messages/${props.message.id}/status`, { status }, { preserveScroll: true });
}
function destroy() {
    if (confirm('Xóa tin nhắn này? Không khôi phục được.')) router.delete(`/admin/contact-messages/${props.message.id}`);
}
function formatDate(value) {
    return value ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }) : '';
}
</script>

<template>
<AdminLayout title="Tin nhắn liên hệ">
    <div class="max-w-3xl">
        <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-slate-200">{{ message.subject || 'Tin nhắn liên hệ' }}</h3>
            <Link href="/admin/contact-messages" class="text-sm text-slate-400 hover:text-slate-300">← Hộp thư</Link>
        </div>
        <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><dt class="text-xs text-slate-500">Họ tên</dt><dd class="text-slate-200 font-medium">{{ message.name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Điện thoại</dt><dd class="text-slate-200"><a v-if="telHref" :href="telHref" class="hover:text-cyan-400">{{ message.phone }}</a><a v-if="zaloHref" :href="zaloHref" target="_blank" rel="noopener noreferrer" class="ml-2 text-xs text-cyan-400 hover:underline">Zalo</a></dd></div>
                <div><dt class="text-xs text-slate-500">Email</dt><dd class="text-slate-200"><a v-if="message.email" :href="`mailto:${message.email}`" class="hover:text-cyan-400">{{ message.email }}</a><span v-else class="text-slate-500">—</span></dd></div>
                <div><dt class="text-xs text-slate-500">Thời gian gửi</dt><dd class="text-slate-200">{{ formatDate(message.created_at) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Trạng thái</dt><dd class="text-slate-200">{{ statusLabel }}</dd></div>
                <div v-if="message.page_url"><dt class="text-xs text-slate-500">Gửi từ trang</dt><dd class="text-slate-400 break-all">{{ message.page_url }}</dd></div>
            </dl>
            <div>
                <p class="text-xs text-slate-500 mb-1">Nội dung</p>
                <div class="whitespace-pre-line rounded-lg border border-slate-800/60 bg-slate-800/30 p-4 text-sm leading-6 text-slate-200">{{ message.message }}</div>
            </div>
            <div v-if="canManage" class="flex flex-wrap gap-2 pt-2">
                <button v-if="message.status !== 'new'" type="button" class="px-3 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/60" @click="setStatus('new')">Đánh dấu chưa đọc</button>
                <button v-if="message.status === 'new'" type="button" class="px-3 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/60" @click="setStatus('read')">Đánh dấu đã đọc</button>
                <button v-if="message.status !== 'archived'" type="button" class="px-3 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/60" @click="setStatus('archived')">Lưu trữ</button>
                <button v-else type="button" class="px-3 py-2 rounded-lg border border-slate-700/50 text-sm text-slate-300 hover:bg-slate-800/60" @click="setStatus('read')">Bỏ lưu trữ</button>
                <button type="button" class="ml-auto px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-sm text-red-600 hover:bg-red-100" @click="destroy">Xóa</button>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
