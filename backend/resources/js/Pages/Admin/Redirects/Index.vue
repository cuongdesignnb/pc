<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ redirects: { type: Array, default: () => [] } });
const page = usePage();
const blank = () => ({ source_path: '', target: '', status_code: 301, is_active: true, note: '' });
const form = useForm(blank());
const editingId = ref(null);

function edit(row) {
    editingId.value = row.id;
    form.defaults({ source_path: row.source_path, target: row.target, status_code: row.status_code, is_active: row.is_active, note: row.note || '' });
    form.reset();
}
function cancel() {
    editingId.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
}
function submit() {
    const options = { preserveScroll: true, onSuccess: cancel };
    if (editingId.value) form.put(`/admin/redirects/${editingId.value}`, options);
    else form.post('/admin/redirects', options);
}
function destroy(row) {
    if (confirm(`Xoá chuyển hướng ${row.source_path}?`)) router.delete(`/admin/redirects/${row.id}`, { preserveScroll: true });
}
</script>

<template>
<AdminLayout title="Chuyển hướng 301">
    <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-sm text-emerald-400">{{ page.props.flash.success }}</div>
    <h3 class="mb-1 text-lg font-semibold text-slate-200">Chuyển hướng 301</h3>
    <p class="mb-4 text-sm text-slate-400">Khách mở đường dẫn cũ sẽ được chuyển sang đường dẫn mới. Dùng 301 khi trang đã chuyển hẳn (Google chuyển thứ hạng sang trang mới), 302 khi chỉ tạm thời. Thay đổi có hiệu lực sau khoảng 1–5 phút.</p>

    <form class="mb-6 grid gap-3 rounded-lg border border-slate-800 bg-slate-900 p-4 md:grid-cols-[1fr_1fr_110px_1fr_auto]" @submit.prevent="submit">
        <div>
            <label class="mb-1 block text-xs text-slate-400">Đường dẫn cũ</label>
            <input v-model="form.source_path" placeholder="/gioi-thieu" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
            <p v-if="form.errors.source_path" class="mt-1 text-xs text-red-400">{{ form.errors.source_path }}</p>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-400">Đường dẫn mới</label>
            <input v-model="form.target" placeholder="/gioi-thieu-laptop-plus" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
            <p v-if="form.errors.target" class="mt-1 text-xs text-red-400">{{ form.errors.target }}</p>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-400">Kiểu</label>
            <select v-model="form.status_code" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
                <option :value="301">301</option>
                <option :value="302">302</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-400">Ghi chú</label>
            <input v-model="form.note" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
            <label class="mt-2 flex items-center gap-2 text-xs text-slate-300"><input v-model="form.is_active" type="checkbox"> Đang bật</label>
        </div>
        <div class="flex items-end gap-2">
            <button :disabled="form.processing" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-40">{{ editingId ? 'Lưu' : 'Thêm' }}</button>
            <button v-if="editingId" type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300" @click="cancel">Huỷ</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-800/60 bg-slate-900">
        <table class="min-w-full divide-y divide-slate-800/40 text-sm">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Đường dẫn cũ</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-slate-400">Đường dẫn mới</th>
                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-slate-400">Kiểu</th>
                <th class="px-4 py-3 text-center text-xs font-medium uppercase text-slate-400">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-slate-400">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="row in redirects" :key="row.id">
                    <td class="px-4 py-3 text-slate-200">{{ row.source_path }}<div v-if="row.note" class="text-xs text-slate-500">{{ row.note }}</div></td>
                    <td class="break-all px-4 py-3 text-slate-300">{{ row.target }}</td>
                    <td class="px-4 py-3 text-center text-slate-300">{{ row.status_code }}</td>
                    <td class="px-4 py-3 text-center"><span :class="row.is_active ? 'text-emerald-400' : 'text-slate-500'">{{ row.is_active ? 'Bật' : 'Tắt' }}</span></td>
                    <td class="space-x-2 px-4 py-3 text-right">
                        <button class="text-cyan-500" @click="edit(row)">Sửa</button>
                        <button class="text-red-500" @click="destroy(row)">Xoá</button>
                    </td>
                </tr>
                <tr v-if="!redirects.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có chuyển hướng</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
