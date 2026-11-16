<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({ author: { type: Object, default: null }, socialKeys: { type: Array, default: () => [] } });
const editing = computed(() => Boolean(props.author?.id));
const socialLabels = { website: 'Website', facebook: 'Facebook', youtube: 'YouTube', tiktok: 'TikTok', linkedin: 'LinkedIn', x: 'X (Twitter)' };

const form = useForm({
    name: props.author?.name || '',
    slug: props.author?.slug || '',
    avatar: props.author?.avatar || '',
    job_title: props.author?.job_title || '',
    bio: props.author?.bio || '',
    social_links: Object.fromEntries(props.socialKeys.map(key => [key, props.author?.social_links?.[key] || ''])),
    is_active: props.author?.is_active ?? true,
});

function submit() {
    if (editing.value) form.put(`/admin/authors/${props.author.id}`);
    else form.post('/admin/authors');
}
</script>

<template>
<AdminLayout :title="editing ? 'Sửa tác giả' : 'Thêm tác giả'">
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-slate-200">{{ editing ? `Sửa: ${author.name}` : 'Thêm tác giả' }}</h3>
            <Link href="/admin/authors" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link>
        </div>
        <form class="space-y-6" @submit.prevent="submit">
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Tên tác giả *</label>
                        <input v-model="form.name" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                        <p v-if="form.errors.name" class="text-xs text-red-400 mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Slug</label>
                        <input v-model="form.slug" placeholder="Để trống: tạo từ tên" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                        <p v-if="form.errors.slug" class="text-xs text-red-400 mt-1">{{ form.errors.slug }}</p>
                        <p class="text-xs text-slate-500 mt-1">Trang tác giả: /tin-tuc/tac-gia/{{ form.slug || '…' }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Chức danh</label>
                    <input v-model="form.job_title" placeholder="Ví dụ: Kỹ thuật viên phần cứng" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <p v-if="form.errors.job_title" class="text-xs text-red-400 mt-1">{{ form.errors.job_title }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Ảnh đại diện</label>
                    <MediaPicker v-model="form.avatar" label="Chọn ảnh tác giả" />
                    <p v-if="form.errors.avatar" class="text-xs text-red-400 mt-1">{{ form.errors.avatar }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Giới thiệu</label>
                    <textarea v-model="form.bio" rows="5" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea>
                    <p v-if="form.errors.bio" class="text-xs text-red-400 mt-1">{{ form.errors.bio }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Hiển thị (ẩn thì bài viết hiện tên tài khoản admin và trang tác giả trả 404)</label>
            </div>
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-3">
                <h4 class="font-medium text-slate-200">Mạng xã hội</h4>
                <p class="text-xs text-slate-500">Không bắt buộc. Link https://... dùng cho trang tác giả và dữ liệu có cấu trúc (schema Person).</p>
                <div v-for="key in socialKeys" :key="key">
                    <label class="block text-sm font-medium text-slate-300 mb-1">{{ socialLabels[key] || key }}</label>
                    <input v-model="form.social_links[key]" type="url" placeholder="https://..." class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <p v-if="form.errors[`social_links.${key}`]" class="text-xs text-red-400 mt-1">{{ form.errors[`social_links.${key}`] }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <Link href="/admin/authors" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
