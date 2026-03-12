<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ category: Object });
const form = useForm({ name: props.category.name, slug: props.category.slug, description: props.category.description || '', sort_order: props.category.sort_order || 0 });
function submit() { form.put(`/admin/post-categories/${props.category.id}`); }
</script>
<template>
<AdminLayout title="Sửa danh mục bài viết">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Sửa: {{ category.name }}</h3><Link href="/admin/post-categories" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tên *</label><input v-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</div></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label><textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự</label><input v-model.number="form.sort_order" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/post-categories" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
