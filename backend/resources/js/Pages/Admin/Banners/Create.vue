<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const form = useForm({
    title: '', description: '', badge: '', image: '', link: '',
    position: 'hero', sort_order: 0, is_active: true, starts_at: '', ends_at: '',
    metadata: { text_in_image: 'true', cta_label: '', cta_link: '' },
});
function submit() { form.post('/admin/banners'); }
</script>
<template>
<AdminLayout title="Thêm banner">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">Thêm banner</h3><Link href="/admin/banners" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Tiêu đề <span class="text-slate-500">(để trống nếu ảnh đã có chữ)</span></label><input v-model="form.title" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.title" class="text-red-400 text-xs mt-1">{{ form.errors.title }}</div></div>
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Mô tả ngắn</label><textarea v-model="form.description" rows="2" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" placeholder="Mô tả hiển thị trên banner"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Badge</label><input v-model="form.badge" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" placeholder="VD: Hot Deal, Flash Sale"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Ảnh (URL) *</label><input v-model="form.image" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.image" class="text-red-400 text-xs mt-1">{{ form.errors.image }}</div></div>
            </div>
            <div v-if="form.image" class="mt-1"><img :src="form.image" class="w-40 h-24 object-cover rounded border" /></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Link</label><input v-model="form.link" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Vị trí *</label><select v-model="form.position" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="hero">Hero</option><option value="sidebar">Sidebar</option><option value="footer">Footer</option><option value="popup">Popup</option><option value="category">Danh mục</option></select><p v-if="form.position === 'hero'" class="mt-1 text-xs text-slate-500">Ảnh chuẩn 1688 × 920 px (tỉ lệ khoảng 1,83 : 1; trên máy tính hiển thị 844 × 460). Ô Hero luôn giữ đúng tỉ lệ này nên ảnh không bị cắt.</p><p v-else-if="form.position === 'sidebar'" class="mt-1 text-xs text-slate-500">Ảnh chuẩn 600 × 448 px (hiển thị 300 × 224). Trang chủ hiện 2 banner Sidebar xếp dọc bên phải ô Hero; trên màn hình nhỏ ảnh có thể bị cắt nhẹ ở mép, nên đặt chữ cách mép khoảng 30 px.</p></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Thứ tự</label><input v-model="form.sort_order" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Bắt đầu</label><input v-model="form.starts_at" type="datetime-local" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Kết thúc</label><input v-model="form.ends_at" type="datetime-local" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Hiện banner</label>

            <!-- Display options -->
            <div class="border-t border-slate-800/60 pt-4 mt-4 space-y-3">
                <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="form.metadata.text_in_image" type="checkbox" true-value="true" false-value="false" class="rounded border-slate-700/50 text-cyan-500"> Ảnh đã có sẵn chữ (không phủ tiêu đề, mô tả lên ảnh)</label>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Chữ trên nút (banner danh mục)</label><input v-model="form.metadata.cta_label" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" placeholder="Xem ngay"></div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Link khi bấm (để trống = dùng Link ở trên)</label><input v-model="form.metadata.cta_link" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm" placeholder="/laptop-cu"></div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/banners" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Tạo</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
