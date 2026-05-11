<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
const form = useForm({ title: '', slug: '', body: '', meta_title: '', meta_description: '', is_active: true });
function genSlug() { form.slug = form.title.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/[^a-z0-9]+/g,'-').replace(/(^-|-$)/g,''); }
function submit() { form.post('/admin/pages'); }
</script>
<template>
<AdminLayout title="Thêm trang">
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">Thêm trang mới</h3><Link href="/admin/pages" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Tiêu đề *</label><input v-model="form.title" @blur="!form.slug && genSlug()" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.title" class="text-red-400 text-xs mt-1">{{ form.errors.title }}</div></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</div></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Nội dung *</label><RichEditor v-model="form.body" placeholder="Nhập nội dung trang..." min-height="350px" /><div v-if="form.errors.body" class="text-red-400 text-xs mt-1">{{ form.errors.body }}</div></div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Hiện trang</label>
            </div>
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="font-medium text-slate-200">SEO</h4>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Meta Title</label><input v-model="form.meta_title" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Meta Description</label><textarea v-model="form.meta_description" rows="2" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea></div>
            </div>
            <div class="flex justify-end gap-3">
                <Link href="/admin/pages" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Tạo</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
