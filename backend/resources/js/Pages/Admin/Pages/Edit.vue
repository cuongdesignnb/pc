<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
const props = defineProps({ page: Object });
const pg = props.page;
const form = useForm({ title: pg.title, slug: pg.slug, body: pg.body || '', meta_title: pg.meta_title || '', meta_description: pg.meta_description || '', is_active: pg.is_active ?? true });
function submit() { form.put(`/admin/pages/${pg.id}`); }
</script>
<template>
<AdminLayout title="Sửa trang">
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Sửa: {{ page.title }}</h3><Link href="/admin/pages" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề *</label><input v-model="form.title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Nội dung *</label><RichEditor v-model="form.body" placeholder="Nhập nội dung trang..." min-height="350px" /></div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Hiện trang</label>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="font-medium text-gray-900">SEO</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label><input v-model="form.meta_title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label><textarea v-model="form.meta_description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
            </div>
            <div class="flex justify-end gap-3">
                <Link href="/admin/pages" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
