<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({ category: Object, categories: Array, componentTypes: Array });
const form = useForm({
    name: props.category.name, slug: props.category.slug, parent_id: props.category.parent_id || '',
    component_type_id: props.category.component_type_id || '', description: props.category.description || '',
    image: props.category.image || '', icon: props.category.icon || '', sort_order: props.category.sort_order || 0,
    is_active: props.category.is_active ?? true, meta_title: props.category.meta_title || '', meta_description: props.category.meta_description || '',
});
function submit() { form.put(`/admin/categories/${props.category.id}`); }
</script>
<template>
<AdminLayout title="Sửa danh mục">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Sửa: {{ category.name }}</h3><Link href="/admin/categories" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tên *</label><input v-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</div></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Danh mục cha</label><select v-model="form.parent_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Không có</option><option v-for="c in categories" :value="c.id">{{ c.name }}</option></select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Loại linh kiện</label><select v-model="form.component_type_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Không có</option><option v-for="t in componentTypes" :value="t.id">{{ t.name }}</option></select></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label><RichEditor v-model="form.description" placeholder="Nhập mô tả danh mục..." min-height="120px" /></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Ảnh đại diện</label><MediaPicker v-model="form.image" label="Chọn ảnh đại diện" /></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Icon (SVG/WebP)</label><MediaPicker v-model="form.icon" label="Chọn icon" /></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự</label><input v-model="form.sort_order" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div class="flex items-end"><label class="flex items-center gap-2 text-sm pb-2"><input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Hiển thị</label></div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/categories" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
