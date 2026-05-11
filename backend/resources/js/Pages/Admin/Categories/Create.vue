<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({ categories: Array, componentTypes: Array });
const form = useForm({ name: '', slug: '', parent_id: '', component_type_id: '', description: '', image: '', icon: '', sort_order: 0, is_active: true, meta_title: '', meta_description: '' });

function genSlug() { form.slug = form.name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,''); }
function submit() { form.post('/admin/categories'); }
</script>
<template>
<AdminLayout title="Thêm danh mục">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">Thêm danh mục mới</h3><Link href="/admin/categories" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Tên *</label><input v-model="form.name" @blur="!form.slug && genSlug()" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</div></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Danh mục cha</label><select v-model="form.parent_id" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="">Không có</option><option v-for="c in categories" :value="c.id">{{ c.name }}</option></select></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Loại linh kiện</label><select v-model="form.component_type_id" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="">Không có</option><option v-for="t in componentTypes" :value="t.id">{{ t.name }}</option></select></div>
            </div>
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Mô tả</label><RichEditor v-model="form.description" placeholder="Nhập mô tả danh mục..." min-height="120px" /></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Ảnh đại diện</label><MediaPicker v-model="form.image" label="Chọn ảnh đại diện" /></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Icon (SVG/WebP)</label><MediaPicker v-model="form.icon" label="Chọn icon" /></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Thứ tự</label><input v-model="form.sort_order" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div class="flex items-end"><label class="flex items-center gap-2 text-sm pb-2"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Hiển thị</label></div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/categories" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Tạo danh mục</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
