<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ products: Object, categories: Array, brands: Array, filters: Object });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const categoryId = ref(props.filters?.category_id || '');
const brandId = ref(props.filters?.brand_id || '');
const status = ref(props.filters?.status || '');

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 400); });
function applyFilters() {
    router.get('/admin/products', {
        search: search.value || undefined, category_id: categoryId.value || undefined,
        brand_id: brandId.value || undefined, status: status.value || undefined,
    }, { preserveState: true, replace: true });
}

function destroy(id) {
    if (!confirm('Xóa sản phẩm này?')) return;
    router.delete(`/admin/products/${id}`);
}

function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(p) + '₫'; }
const statusMap = { active: 'Đang bán', inactive: 'Ẩn', out_of_stock: 'Hết hàng' };
const statusColor = { active: 'bg-green-100 text-green-700', inactive: 'bg-gray-100 text-gray-600', out_of_stock: 'bg-red-100 text-red-700' };
</script>
<template>
<AdminLayout title="Sản phẩm">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link href="/admin/products/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">+ Thêm sản phẩm</Link>
            </div>
            <div class="flex items-center gap-3">
                <input v-model="search" placeholder="Tìm tên, SKU..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-indigo-500">
                <select v-model="categoryId" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tất cả danh mục</option>
                    <option v-for="c in categories" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="brandId" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tất cả thương hiệu</option>
                    <option v-for="b in brands" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="status" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active">Đang bán</option>
                    <option value="inactive">Ẩn</option>
                    <option value="out_of_stock">Hết hàng</option>
                </select>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Danh mục</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Giá</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tồn kho</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="p in products.data" :key="p.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-100 rounded flex-shrink-0 overflow-hidden">
                                <img v-if="p.images?.[0]" :src="p.images[0].url" class="w-full h-full object-cover">
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ p.name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ p.sku }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ p.category?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span v-if="p.sale_price" class="text-red-600 font-medium">{{ formatPrice(p.sale_price) }}</span>
                        <span :class="p.sale_price ? 'line-through text-gray-400 text-xs ml-1' : 'text-gray-900'">{{ formatPrice(p.price) }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm" :class="p.quantity < 5 ? 'text-red-600 font-medium' : 'text-gray-500'">{{ p.quantity }}</td>
                    <td class="px-4 py-3"><span :class="statusColor[p.status]" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusMap[p.status] }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <Link :href="`/admin/products/${p.id}/edit`" class="text-indigo-600 hover:text-indigo-800 text-sm">Sửa</Link>
                        <button @click="destroy(p.id)" class="text-red-600 hover:text-red-800 text-sm">Xóa</button>
                    </td>
                </tr>
                <tr v-if="!products.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Chưa có sản phẩm nào</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="products.last_page > 1" class="mt-4 flex justify-end gap-1">
        <button v-for="link in products.links" :key="link.label" @click="link.url && router.get(link.url, {}, {preserveState:true})"
            :disabled="!link.url" :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
            class="px-3 py-1.5 text-sm border border-gray-300 rounded disabled:opacity-40" v-html="link.label"/>
    </div>
</AdminLayout>
</template>
