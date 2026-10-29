<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({ activity: Object });
const form = useForm({
    title: props.activity?.title || '',
    type: props.activity?.type || 'image',
    image: props.activity?.image || '',
    video_url: props.activity?.video_url || '',
    sort_order: props.activity?.sort_order ?? 0,
    is_active: props.activity?.is_active ?? true,
});
function submit() {
    if (props.activity) form.put(`/admin/store-activities/${props.activity.id}`);
    else form.post('/admin/store-activities');
}
</script>
<template>
<AdminLayout :title="activity ? 'Sửa hoạt động cửa hàng' : 'Thêm hoạt động cửa hàng'">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">{{ activity ? `Sửa: ${activity.title}` : 'Thêm ảnh / video cửa hàng' }}</h3><Link href="/admin/store-activities" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Tiêu đề *</label><input v-model="form.title" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" placeholder="VD: Kỹ thuật test máy trước khi giao"><div v-if="form.errors.title" class="text-red-400 text-xs mt-1">{{ form.errors.title }}</div></div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Loại *</label>
                <div class="flex gap-4 text-sm text-slate-300">
                    <label class="flex items-center gap-2"><input v-model="form.type" type="radio" value="image"> Hình ảnh</label>
                    <label class="flex items-center gap-2"><input v-model="form.type" type="radio" value="video"> Video</label>
                </div>
            </div>
            <div v-if="form.type === 'video'">
                <label class="block text-sm font-medium text-slate-300 mb-1">Link video *</label>
                <input v-model="form.video_url" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" placeholder="https://www.youtube.com/watch?v=... hoặc link video Facebook hoặc file .mp4">
                <p class="mt-1 text-xs text-slate-500">Hỗ trợ YouTube (cả Shorts), video Facebook, hoặc file .mp4/.webm tải lên thư viện Media (mở Thư viện Media, sao chép link file rồi dán vào đây).</p>
                <div v-if="form.errors.video_url" class="text-red-400 text-xs mt-1">{{ form.errors.video_url }}</div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">{{ form.type === 'video' ? 'Ảnh bìa (không bắt buộc)' : 'Ảnh *' }}</label>
                <MediaPicker v-model="form.image" label="Chọn ảnh" />
                <img v-if="form.image" :src="form.image" class="mt-2 h-32 rounded object-cover">
                <p class="mt-1 text-xs text-slate-500">Ảnh ngang tỉ lệ 4:3, khoảng 800 × 600 px. Video YouTube để trống ảnh bìa thì tự lấy ảnh của video.</p>
                <div v-if="form.errors.image" class="text-red-400 text-xs mt-1">{{ form.errors.image }}</div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Thứ tự</label><input v-model="form.sort_order" type="number" min="0" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><p class="mt-1 text-xs text-slate-500">Số nhỏ hiện trước; mục đầu tiên là ô lớn.</p></div>
                <div class="flex items-end"><label class="flex items-center gap-2 text-sm pb-2 text-slate-300"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Hiển thị trên trang chủ</label></div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/store-activities" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">{{ activity ? 'Lưu thay đổi' : 'Thêm' }}</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
