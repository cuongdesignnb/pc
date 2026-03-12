<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaPicker from '@/Components/MediaPicker.vue';
const form = useForm({ name: '', slug: '', logo: '', description: '', website: '', is_active: true });
function genSlug() { form.slug = form.name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,''); }
function submit() { form.post('/admin/brands'); }
</script>
<template>
<AdminLayout title="Thêm thương hiệu">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">Thêm thương hiệu</h3><Link href="/admin/brands" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Tên *</label><input v-model="form.name" @blur="!form.slug && genSlug()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</div></div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                <MediaPicker v-model="form.logo" label="Chọn logo thương hiệu" />
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Website</label><input v-model="form.website" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://..."></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label><textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Hiển thị</label>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/brands" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">Tạo</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
