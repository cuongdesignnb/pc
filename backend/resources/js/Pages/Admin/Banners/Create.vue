<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const showMeta = ref(false);
const form = useForm({
    title: '', description: '', badge: '', image: '', link: '',
    position: 'hero', sort_order: 0, is_active: true, starts_at: '', ends_at: '',
    metadata: { gradient: 'from-indigo-600 via-purple-600 to-pink-500', cta_label: '', cta_link: '', cta2_label: '', cta2_link: '', glow_a: 'bg-white', glow_b: 'bg-yellow-300' },
});
function submit() { form.post('/admin/banners'); }
</script>
<template>
<AdminLayout title="Thêm banner">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Thêm banner</h3><Link href="/admin/banners" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề *</label><input v-model="form.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Mô tả ngắn</label><textarea v-model="form.description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Mô tả hiển thị trên banner"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Badge</label><input v-model="form.badge" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="VD: Hot Deal, Flash Sale"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Ảnh (URL) *</label><input v-model="form.image" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.image" class="text-red-500 text-xs mt-1">{{ form.errors.image }}</div></div>
            </div>
            <div v-if="form.image" class="mt-1"><img :src="form.image" class="w-40 h-24 object-cover rounded border" /></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Link</label><input v-model="form.link" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Vị trí *</label><select v-model="form.position" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="hero">Hero</option><option value="sidebar">Sidebar</option><option value="footer">Footer</option><option value="popup">Popup</option><option value="category">Danh mục</option></select></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự</label><input v-model="form.sort_order" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Bắt đầu</label><input v-model="form.starts_at" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Kết thúc</label><input v-model="form.ends_at" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Hiện banner</label>

            <!-- Metadata section (collapsible) -->
            <div class="border-t border-gray-200 pt-4 mt-4">
                <button type="button" @click="showMeta = !showMeta" class="flex items-center gap-2 text-sm font-medium text-gray-700 hover:text-indigo-600">
                    <svg :class="showMeta ? 'rotate-90' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    Tùy chỉnh nâng cao (Hero Banner)
                </button>
                <div v-show="showMeta" class="mt-3 space-y-3 pl-4 border-l-2 border-indigo-100">
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Gradient CSS</label><input v-model="form.metadata.gradient" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="from-indigo-600 via-purple-600 to-pink-500"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs font-medium text-gray-500 mb-1">CTA chính - Label</label><input v-model="form.metadata.cta_label" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Build PC ngay"></div>
                        <div><label class="block text-xs font-medium text-gray-500 mb-1">CTA chính - Link</label><input v-model="form.metadata.cta_link" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="/configurator"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs font-medium text-gray-500 mb-1">CTA phụ - Label</label><input v-model="form.metadata.cta2_label" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Xem sản phẩm"></div>
                        <div><label class="block text-xs font-medium text-gray-500 mb-1">CTA phụ - Link</label><input v-model="form.metadata.cta2_link" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="/products"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs font-medium text-gray-500 mb-1">Glow A CSS</label><input v-model="form.metadata.glow_a" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="bg-white"></div>
                        <div><label class="block text-xs font-medium text-gray-500 mb-1">Glow B CSS</label><input v-model="form.metadata.glow_b" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="bg-yellow-300"></div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/banners" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Tạo</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
