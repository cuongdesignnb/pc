<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ activities: Array });
const flash = usePage().props.flash;
function destroy(id) { if (confirm('Xóa mục này?')) router.delete(`/admin/store-activities/${id}`); }
</script>
<template>
<AdminLayout title="Hoạt động cửa hàng">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-2">
        <h3 class="text-lg font-semibold text-slate-200">Hoạt động tại cửa hàng</h3>
        <Link href="/admin/store-activities/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm ảnh / video</Link>
    </div>
    <p class="mb-4 text-sm text-slate-400">Hiện ở khối "Hoạt động tại cửa hàng" trên trang chủ: mục đầu tiên (thứ tự nhỏ nhất) là ô lớn, 6 mục tiếp theo là các ô nhỏ.</p>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Ảnh</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tiêu đề</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Loại</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Thứ tự</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="a in activities" :key="a.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3"><img v-if="a.preview?.image" :src="a.preview.image" class="w-20 h-12 object-cover rounded" /><span v-else class="text-slate-500 text-xs">—</span></td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-200">
                        {{ a.title }}
                        <p v-if="a.type === 'video' && !a.preview" class="text-xs text-red-400">Link video không phát được, hãy sửa lại.</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ a.type === 'video' ? `Video${a.preview?.video ? ` (${a.preview.video.provider === 'file' ? 'MP4' : a.preview.video.provider === 'youtube' ? 'YouTube' : 'Facebook'})` : ''}` : 'Hình ảnh' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400 text-center">{{ a.sort_order }}</td>
                    <td class="px-4 py-3 text-center"><span :class="a.is_active ? 'bg-green-100 text-emerald-400' : 'bg-slate-800/60 text-slate-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ a.is_active ? 'Hiện' : 'Ẩn' }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/store-activities/${a.id}/edit`" class="text-cyan-500 hover:text-cyan-400 text-sm">Sửa</Link>
                        <button @click="destroy(a.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!activities.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có ảnh hoặc video nào. Khối này được ẩn trên trang chủ khi trống.</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>
