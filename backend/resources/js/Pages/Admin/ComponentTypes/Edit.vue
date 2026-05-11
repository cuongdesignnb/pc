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
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">Sửa: {{ type.name }}</h3><Link href="/admin/component-types" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Tên *</label><input v-model="form.name" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div></div>
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Slug *</label><input v-model="form.slug" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</div></div>
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Thứ tự hiển thị</label><input v-model="form.display_order" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_required" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Bắt buộc khi cấu hình PC</label>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/component-types" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Lưu</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
