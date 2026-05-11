<script setup>
import { computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({
    product: Object,
    categories: Array,
    brands: Array,
    componentTypes: Array,
});

// Get thumbnail and gallery from product images
const primaryImg = (props.product.images || []).find(i => i.is_primary);
const galleryImgs = (props.product.images || []).filter(i => !i.is_primary).map(i => i.url);

// Build existing compatibility specs map
const existingSpecs = {};
(props.product.specifications || []).forEach(s => {
    existingSpecs[s.specification_key_id] = s.value_string || (s.value_numeric !== null ? String(s.value_numeric) : '');
});

const form = useForm({
    name: props.product.name,
    slug: props.product.slug,
    sku: props.product.sku,
    category_id: props.product.category_id || '',
    brand_id: props.product.brand_id || '',
    component_type_id: props.product.component_type_id || '',
    description: props.product.description || '',
    short_description: props.product.short_description || '',
    price: props.product.price,
    sale_price: props.product.sale_price || '',
    stock_quantity: props.product.stock_quantity ?? 0,
    is_active: props.product.is_active ?? true,
    is_featured: props.product.is_featured || false,
    warranty_months: props.product.warranty_months || 12,
    meta_title: props.product.meta_title || '',
    meta_description: props.product.meta_description || '',
    thumbnail: primaryImg ? primaryImg.url : '',
    gallery: galleryImgs,
    specifications_text: props.product.specifications_text || '',
    compatibility_specs: [],
});

// Get spec keys for selected component type
const selectedComponentType = computed(() => {
    if (!form.component_type_id) return null;
    return props.componentTypes.find(t => t.id == form.component_type_id);
});

const specKeys = computed(() => {
    return selectedComponentType.value?.specification_keys || [];
});

// Build compatibility spec entries from spec keys + existing values
function buildCompatibilitySpecs() {
    const keys = specKeys.value;
    form.compatibility_specs = keys.map(key => ({
        specification_key_id: key.id,
        label: key.label,
        key: key.key,
        unit: key.unit || '',
        data_type: key.data_type,
        value: existingSpecs[key.id] || '',
    }));
}

watch(() => form.component_type_id, () => {
    buildCompatibilitySpecs();
});

// Initialize if product already has a component type
if (form.component_type_id) {
    buildCompatibilitySpecs();
}

function submit() { form.put(`/admin/products/${props.product.id}`); }
</script>
<template>
<AdminLayout title="Sửa sản phẩm">
    <div class="max-w-5xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-slate-200">Sửa: {{ product.name }}</h3>
            <Link href="/admin/products" class="text-sm text-slate-400 hover:text-slate-300">← Quay lại</Link>
        </div>
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Thông tin cơ bản -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Thông tin cơ bản</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Tên *</label>
                        <input v-model="form.name" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50">
                        <div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Slug *</label>
                        <input v-model="form.slug" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                        <div v-if="form.errors.slug" class="text-red-400 text-xs mt-1">{{ form.errors.slug }}</div>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">SKU *</label>
                        <input v-model="form.sku" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                        <div v-if="form.errors.sku" class="text-red-400 text-xs mt-1">{{ form.errors.sku }}</div>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Trạng thái</label>
                        <div class="flex items-center gap-4 mt-2">
                            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Đang bán</label>
                            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_featured" type="checkbox" class="rounded border-slate-700/50 text-cyan-500"> Nổi bật</label>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Danh mục *</label>
                        <select v-model="form.category_id" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="c in categories" :value="c.id">{{ c.name }}</option></select>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Thương hiệu</label>
                        <select v-model="form.brand_id" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="b in brands" :value="b.id">{{ b.name }}</option></select>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Loại linh kiện</label>
                        <select v-model="form.component_type_id" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="t in componentTypes" :value="t.id">{{ t.name }}</option></select>
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Mô tả ngắn</label><textarea v-model="form.short_description" rows="2" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Mô tả chi tiết</label><RichEditor v-model="form.description" placeholder="Nhập mô tả chi tiết sản phẩm..." /></div>
            </div>

            <!-- Hình ảnh -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-5">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Hình ảnh sản phẩm
                </h4>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Ảnh đại diện (Thumbnail)</label>
                    <MediaPicker v-model="form.thumbnail" label="Chọn ảnh đại diện" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Thư viện ảnh sản phẩm</label>
                    <MediaPicker v-model="form.gallery" :multiple="true" label="Thêm ảnh vào thư viện" />
                </div>
            </div>

            <!-- Giá & Kho -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Giá & Kho</h4>
                <div class="grid grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Giá gốc *</label><input v-model="form.price" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Giá sale</label><input v-model="form.sale_price" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Tồn kho *</label><input v-model="form.stock_quantity" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-slate-300 mb-1">Bảo hành (tháng)</label><input v-model="form.warranty_months" type="number" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                </div>
            </div>

            <!-- Thông số kỹ thuật -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Thông số kỹ thuật
                </h4>
                <p class="text-xs text-slate-400">Mỗi dòng là một thông số, theo định dạng: <strong>Tên: Giá trị</strong></p>
                <textarea v-model="form.specifications_text" rows="10"
                    placeholder="CPU: Intel Core i7-12700K&#10;RAM: 16GB DDR5&#10;SSD: 512GB NVMe&#10;Card đồ họa: RTX 4060 8GB&#10;Màn hình: 15.6 inch FHD IPS"
                    class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-cyan-500/50"></textarea>
            </div>

            <!-- Thông số tương thích (cho PC Builder) -->
            <div v-if="specKeys.length" class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Thông số tương thích
                    <span class="text-xs font-normal text-slate-500">({{ selectedComponentType?.name }})</span>
                </h4>
                <p class="text-xs text-slate-400">Các thông số này dùng cho <strong>kiểm tra tương thích tự động</strong> trong trang Cấu hình PC. Chỉ cần điền các thông số liên quan đến tương thích (socket, loại RAM, TDP, kích thước...).</p>
                <div class="border border-slate-800/60 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-800/40">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase w-1/3">Thông số</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Giá trị</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase w-20">Đơn vị</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/40">
                            <tr v-for="spec in form.compatibility_specs" :key="spec.specification_key_id" class="hover:bg-slate-800/40">
                                <td class="px-4 py-2">
                                    <span class="font-medium text-slate-300">{{ spec.label }}</span>
                                    <span class="text-slate-500 text-xs ml-1">({{ spec.key }})</span>
                                </td>
                                <td class="px-4 py-2">
                                    <input v-model="spec.value"
                                        :type="spec.data_type === 'integer' || spec.data_type === 'decimal' ? 'number' : 'text'"
                                        :step="spec.data_type === 'decimal' ? '0.01' : undefined"
                                        :placeholder="`Nhập ${spec.label.toLowerCase()}...`"
                                        class="w-full border border-slate-700/50 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                </td>
                                <td class="px-4 py-2 text-slate-400 text-xs">{{ spec.unit }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SEO -->
            <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">SEO</h4>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Meta title</label><input v-model="form.meta_title" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-slate-300 mb-1">Meta description</label><textarea v-model="form.meta_description" rows="2" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea></div>
            </div>

            <div class="flex justify-end gap-3">
                <Link href="/admin/products" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium disabled:opacity-50">
                    <span v-if="form.processing">Đang lưu...</span>
                    <span v-else>Lưu thay đổi</span>
                </button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
