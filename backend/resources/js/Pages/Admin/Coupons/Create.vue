<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const form = useForm({ code: '', type: 'percentage', value: '', min_order_amount: '', max_uses: '', starts_at: '', expires_at: '', is_active: true });
function submit() { form.post('/admin/coupons'); }
</script>
<template>
<AdminLayout title="Thêm mã giảm giá">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">Thêm mã giảm giá</h3><Link href="/admin/coupons" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Mã *</label><input v-model="form.code" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm uppercase" placeholder="VD: SALE20"><div v-if="form.errors.code" class="text-red-400 text-xs mt-1">{{ form.errors.code }}</div></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Loại giảm *</label><select v-model="form.type" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="percentage">Phần trăm (%)</option><option value="fixed">Số tiền cố định (₫)</option></select></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Giá trị *</label><input v-model="form.value" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.value" class="text-red-400 text-xs mt-1">{{ form.errors.value }}</div></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Đơn tối thiểu</label><input v-model="form.min_order_amount" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Lượt dùng tối đa</label><input v-model="form.max_uses" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Bắt đầu</label><input v-model="form.starts_at" type="datetime-local" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Kết thúc</label><input v-model="form.expires_at" type="datetime-local" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Kích hoạt</label>
            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/coupons" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Tạo</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
