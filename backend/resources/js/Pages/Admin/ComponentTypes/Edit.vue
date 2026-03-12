<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ type: Object });
const form = useForm({ name: props.type.name, slug: props.type.slug, display_order: props.type.display_order || 0, is_required: props.type.is_required ?? false });
function submit() { form.put(`/admin/component-types/${props.type.id}`); }
</script>
<template>
<AdminLayout title="Sửa loại linh kiện">
    <div class="max-w-lg">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Sửa: {{ type.name }}</h3><Link href="/admin/component-types" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Tên *</label><input v-model="form.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</div></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự hiển thị</label><input v-model="form.display_order" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_required" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Bắt buộc khi cấu hình PC</label>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/component-types" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
