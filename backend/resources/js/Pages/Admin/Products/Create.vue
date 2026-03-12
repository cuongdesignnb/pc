<script setup>
import { computed, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import MediaPicker from '@/Components/MediaPicker.vue';

const props = defineProps({
    categories: Array,
    brands: Array,
    componentTypes: Array,
});

const form = useForm({
    name: '', slug: '', sku: '', category_id: '', brand_id: '', component_type_id: '',
    description: '', short_description: '', price: '', sale_price: '', stock_quantity: 0,
    is_active: true, is_featured: false, warranty_months: 12,
    meta_title: '', meta_description: '',
    thumbnail: '',
    gallery: [],
    specifications_text: '',
    compatibility_specs: [],
});

function genSlug() {
    form.slug = form.name.toLowerCase().normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'd')
        .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

// Get spec keys for selected component type
const selectedComponentType = computed(() => {
    if (!form.component_type_id) return null;
    return props.componentTypes.find(t => t.id == form.component_type_id);
});

const specKeys = computed(() => {
    return selectedComponentType.value?.specification_keys || [];
});

// Rebuild compatibility specs when component type changes
watch(() => form.component_type_id, () => {
    const keys = specKeys.value;
    form.compatibility_specs = keys.map(key => {
        const existing = form.compatibility_specs.find(s => s.specification_key_id === key.id);
        return {
            specification_key_id: key.id,
            label: key.label,
            key: key.key,
            unit: key.unit || '',
            data_type: key.data_type,
            value: existing?.value || '',
        };
    });
});

function submit() { form.post('/admin/products'); }
</script>
<template>
<AdminLayout title="Thêm sản phẩm">
    <div class="max-w-5xl">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Thêm sản phẩm mới</h3>
            <Link href="/admin/products" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link>
        </div>
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Thông tin cơ bản -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Thông tin cơ bản</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tên sản phẩm *</label>
                        <input v-model="form.name" @blur="!form.slug && genSlug()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                        <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
                        <input v-model="form.slug" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                        <div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">SKU *</label>
                        <input v-model="form.sku" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                        <div v-if="form.errors.sku" class="text-red-500 text-xs mt-1">{{ form.errors.sku }}</div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
                        <div class="flex items-center gap-4 mt-2">
                            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Đang bán</label>
                            <label class="flex items-center gap-2 text-sm"><input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300 text-indigo-600"> Nổi bật</label>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Danh mục *</label>
                        <select v-model="form.category_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="c in categories" :value="c.id">{{ c.name }}</option></select>
                        <div v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Thương hiệu</label>
                        <select v-model="form.brand_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="b in brands" :value="b.id">{{ b.name }}</option></select>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Loại linh kiện</label>
                        <select v-model="form.component_type_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Chọn...</option><option v-for="t in componentTypes" :value="t.id">{{ t.name }}</option></select>
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Mô tả ngắn</label><textarea v-model="form.short_description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Mô tả chi tiết</label><RichEditor v-model="form.description" placeholder="Nhập mô tả chi tiết sản phẩm..." /></div>
            </div>

            <!-- Hình ảnh -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Hình ảnh sản phẩm
                </h4>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ảnh đại diện (Thumbnail) *</label>
                    <MediaPicker v-model="form.thumbnail" label="Chọn ảnh đại diện" />
                    <div v-if="form.errors.thumbnail" class="text-red-500 text-xs mt-1">{{ form.errors.thumbnail }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thư viện ảnh sản phẩm</label>
                    <MediaPicker v-model="form.gallery" :multiple="true" label="Thêm ảnh vào thư viện" />
                    <div v-if="form.errors.gallery" class="text-red-500 text-xs mt-1">{{ form.errors.gallery }}</div>
                </div>
            </div>

            <!-- Giá & Kho -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Giá & Kho</h4>
                <div class="grid grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Giá gốc *</label><input v-model="form.price" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"><div v-if="form.errors.price" class="text-red-500 text-xs mt-1">{{ form.errors.price }}</div></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Giá sale</label><input v-model="form.sale_price" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tồn kho *</label><input v-model="form.stock_quantity" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Bảo hành (tháng)</label><input v-model="form.warranty_months" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                </div>
            </div>

            <!-- Thông số kỹ thuật -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Thông số kỹ thuật
                </h4>
                <p class="text-xs text-gray-500">Mỗi dòng là một thông số, theo định dạng: <strong>Tên: Giá trị</strong></p>
                <textarea v-model="form.specifications_text" rows="10"
                    placeholder="CPU: Intel Core i7-12700K&#10;RAM: 16GB DDR5&#10;SSD: 512GB NVMe&#10;Card đồ họa: RTX 4060 8GB&#10;Màn hình: 15.6 inch FHD IPS"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <!-- Thông số tương thích (cho PC Builder) -->
            <div v-if="specKeys.length" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Thông số tương thích
                    <span class="text-xs font-normal text-gray-400">({{ selectedComponentType?.name }})</span>
                </h4>
                <p class="text-xs text-gray-500">Các thông số này dùng cho <strong>kiểm tra tương thích tự động</strong> trong trang Cấu hình PC. Chỉ cần điền các thông số liên quan đến tương thích (socket, loại RAM, TDP, kích thước...).</p>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase w-1/3">Thông số</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Giá trị</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase w-20">Đơn vị</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="spec in form.compatibility_specs" :key="spec.specification_key_id" class="hover:bg-gray-50/50">
                                <td class="px-4 py-2">
                                    <span class="font-medium text-gray-700">{{ spec.label }}</span>
                                    <span class="text-gray-400 text-xs ml-1">({{ spec.key }})</span>
                                </td>
                                <td class="px-4 py-2">
                                    <input v-model="spec.value"
                                        :type="spec.data_type === 'integer' || spec.data_type === 'decimal' ? 'number' : 'text'"
                                        :step="spec.data_type === 'decimal' ? '0.01' : undefined"
                                        :placeholder="`Nhập ${spec.label.toLowerCase()}...`"
                                        class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ spec.unit }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SEO -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">SEO</h4>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta title</label><input v-model="form.meta_title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Meta description</label><textarea v-model="form.meta_description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
            </div>

            <div class="flex justify-end gap-3">
                <Link href="/admin/products" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Hủy</Link>
                <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium disabled:opacity-50">
                    <span v-if="form.processing">Đang tạo...</span>
                    <span v-else>Tạo sản phẩm</span>
                </button>
            </div>
        </form>
    </div>
</AdminLayout>
</template>
