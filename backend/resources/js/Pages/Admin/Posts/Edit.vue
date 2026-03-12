<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import MediaPicker from '@/Components/MediaPicker.vue';
const props = defineProps({ post: Object, categories: Array });
const p = props.post;
const form = useForm({ title: p.title, slug: p.slug, excerpt: p.excerpt || '', body: p.body || '', featured_image: p.featured_image || '', post_category_id: p.post_category_id || '', status: p.status || 'draft', is_featured: p.is_featured ?? false, published_at: p.published_at ? p.published_at.slice(0,16) : '', meta_title: p.meta_title || '', meta_description: p.meta_description || '' });
function submit() { form.put(`/admin/posts/${p.id}`); }
</script>
<template>
<AdminLayout title="Sửa bài viết">
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Sửa: {{ post.title }}</h3><Link href="/admin/posts" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="font-medium text-gray-900">Nội dung</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề *</label><input v-model="form.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tóm tắt</label><textarea v-model="form.excerpt" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Nội dung *</label><RichEditor v-model="form.body" placeholder="Viết nội dung bài viết..." min-height="300px" /></div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="font-medium text-gray-900">Cài đặt</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Danh mục</label>
                        <select v-model="form.post_category_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">-- Chọn danh mục --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái *</label>
                        <select v-model="form.status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="draft">Nháp</option>
                            <option value="published">Xuất bản</option>
                            <option value="archived">Lưu trữ</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ảnh đại diện</label>
                    <MediaPicker v-model="form.featured_image" label="Chọn ảnh đại diện" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Ngày đăng</label><input v-model="form.published_at" type="datetime-local" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                    <div class="flex items-end"><label class="flex items-center gap-2 text-sm pb-2"><input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Nổi bật</label></div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="font-medium text-gray-900">SEO</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label><input v-model="form.meta_title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label><textarea v-model="form.meta_description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
            </div>
            <div class="flex justify-end gap-3">
                <Link href="/admin/posts" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
