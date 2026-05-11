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
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">Sửa: {{ page.title }}</h3><Link href="/admin/pages" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Tiêu đề *</label><input v-model="form.title" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.title" class="text-red-400 text-xs mt-1">{{ form.errors.title }}</div></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Nội dung *</label><RichEditor v-model="form.body" placeholder="Nhập nội dung trang..." min-height="350px" /></div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Hiện trang</label>
            </div>
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="font-medium text-slate-200">SEO</h4>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Meta Title</label><input v-model="form.meta_title" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Meta Description</label><textarea v-model="form.meta_description" rows="2" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea></div>
            </div>
            <div class="flex justify-end gap-3">
                <Link href="/admin/pages" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
