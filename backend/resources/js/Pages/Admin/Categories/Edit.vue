<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import MediaPicker from '@/Components/MediaPicker.vue';
import AffiliateCommissionField, { commissionForm } from '@/Components/AffiliateCommissionField.vue';
import ProductComplianceFields from '@/Components/ProductComplianceFields.vue';

const props = defineProps({ category: Object, categories: Array, componentTypes: Array });
const form = useForm({
    name: props.category.name, slug: props.category.slug, parent_id: props.category.parent_id || '',
    component_type_id: props.category.component_type_id || '', description: props.category.description || '',
    image: props.category.image || '', icon: props.category.icon || '', sort_order: props.category.sort_order || 0,
    is_active: props.category.is_active ?? true, show_on_pc_website: props.category.show_on_pc_website ?? true, meta_title: props.category.meta_title || '', meta_description: props.category.meta_description || '', technical_heading: props.category.technical_heading || 'auto',
    warranty_packages: (props.category.warranty_packages || []).map(p => ({ months: p.months, price: p.price ?? 0 })),
    affiliate_commission: commissionForm(props.category.affiliate_commission),
    is_refurbished: props.category.is_refurbished ?? false, warning_info: props.category.warning_info || '', usage_instructions: props.category.usage_instructions || '',
});
function addWarrantyPackage() { form.warranty_packages.push({ months: '', price: 0 }); }
function submit() { form.put(`/admin/categories/${props.category.id}`); }
</script>
<template>
<AdminLayout title="Sửa danh mục">
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-slate-200">Sửa: {{ category.name }}</h3><Link href="/admin/categories" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link></div>
        <form @submit.prevent="submit" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Tên *</label><input v-model="form.name" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div></div>
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
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-2 text-sm pb-2"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Đang hoạt động</label>
                    <label class="flex items-center gap-2 text-sm pb-2"><input v-model="form.show_on_pc_website" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Hiển thị trên website</label>
                </div>
            </div>
            <div><label class="block text-sm font-medium text-slate-300 mb-1">Tiêu đề phần thông tin kỹ thuật</label><select v-model="form.technical_heading" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="auto">Tự động</option><option value="configuration">Cấu hình chi tiết</option><option value="specifications">Thông số kỹ thuật</option></select><p class="mt-1 text-xs text-slate-500">Dùng cho phần thông tin kỹ thuật trên trang sản phẩm thuộc danh mục này.</p></div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium text-slate-300">Gói bảo hành khách chọn khi mua</label>
                    <button type="button" @click="addWarrantyPackage" class="text-xs text-cyan-500 hover:text-cyan-400">+ Thêm gói</button>
                </div>
                <div v-for="(pkg, i) in form.warranty_packages" :key="i" class="flex items-center gap-2 mb-2">
                    <input v-model.number="pkg.months" type="number" min="1" max="120" placeholder="Số tháng" class="w-28 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <span class="text-sm text-slate-400">tháng, cộng thêm</span>
                    <input v-model.number="pkg.price" type="number" min="0" step="1000" class="w-40 border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <span class="text-sm text-slate-400">đ</span>
                    <button type="button" @click="form.warranty_packages.splice(i, 1)" class="text-xs text-red-400 hover:text-red-300 ml-auto">Xóa</button>
                </div>
                <p v-if="!form.warranty_packages.length" class="text-xs text-slate-500">Chưa có gói: sản phẩm dùng thời hạn bảo hành mặc định của từng máy.</p>
                <p v-else class="text-xs text-slate-500">Áp dụng cho sản phẩm thuộc danh mục này và các danh mục con chưa có gói riêng. Giá cộng thêm có thể để 0đ.</p>
                <div v-for="(msg, key) in form.errors" :key="key"><p v-if="String(key).startsWith('warranty_packages')" class="text-red-400 text-xs mt-1">{{ msg }}</p></div>
            </div>
            <AffiliateCommissionField v-model="form.affiliate_commission" :errors="form.errors" />
            <ProductComplianceFields :form="form" category />

            <div class="flex justify-end gap-3 pt-2">
                <Link href="/admin/categories" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
