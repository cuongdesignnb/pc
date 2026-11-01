<script setup>
import { computed, ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ products: Object, categories: Array, brands: Array, filters: Object, currentYear: Number });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const categoryId = ref(props.filters?.category_id || '');
const brandId = ref(props.filters?.brand_id || '');
const status = ref(props.filters?.status || '');
const manufactureYear = ref(props.filters?.manufacture_year || '');
const yearOptions = computed(() => Array.from({ length: 16 }, (_, i) => (props.currentYear || new Date().getFullYear()) + 1 - i));

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 400); });
function applyFilters() {
    router.get('/admin/products', {
        search: search.value || undefined, category_id: categoryId.value || undefined,
        brand_id: brandId.value || undefined, status: status.value || undefined,
        manufacture_year: manufactureYear.value || undefined,
    }, { preserveState: true, replace: true });
}

function destroy(id) {
    if (!confirm('Xóa sản phẩm này?')) return;
    router.delete(`/admin/products/${id}`);
}

function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(p) + '₫'; }
const statusMap = { active: 'Đang bán', inactive: 'Ẩn', out_of_stock: 'Hết hàng' };
const statusColor = { active: 'bg-green-100 text-emerald-400', inactive: 'bg-slate-800/60 text-slate-400', out_of_stock: 'bg-red-100 text-red-700' };

// Import
const showImport = ref(false);
const importFile = ref(null);
const importing = ref(false);
function onFileChange(e) { importFile.value = e.target.files[0]; }
function submitImport() {
    if (!importFile.value) return;
    importing.value = true;
    router.post('/admin/products-import', { file: importFile.value }, {
        forceFormData: true,
        onFinish: () => { importing.value = false; showImport.value = false; importFile.value = null; }
    });
}
function currentFilters() {
    const filters = {};
    if (search.value) filters.search = search.value;
    if (categoryId.value) filters.category_id = categoryId.value;
    if (brandId.value) filters.brand_id = brandId.value;
    if (status.value) filters.status = status.value;
    if (manufactureYear.value) filters.manufacture_year = manufactureYear.value;
    return filters;
}

// Năm sản xuất hàng loạt
const selected = ref([]);
const allOnPage = computed({
    get: () => props.products.data?.length > 0 && props.products.data.every(p => selected.value.includes(p.id)),
    set: (value) => { selected.value = value ? props.products.data.map(p => p.id) : []; },
});
watch(() => props.products.data, () => { selected.value = []; });
const bulkYear = ref('');
const bulkScope = ref('selected');
const bulkSaving = ref(false);
function applyBulkYear() {
    const year = bulkYear.value === '' ? null : Number(bulkYear.value);
    const target = bulkScope.value === 'all' ? `toàn bộ ${props.products.total} sản phẩm theo bộ lọc hiện tại` : `${selected.value.length} sản phẩm đã chọn`;
    if (!confirm(year ? `Đặt năm sản xuất ${year} cho ${target}?` : `Xoá năm sản xuất của ${target}?`)) return;
    bulkSaving.value = true;
    const payload = bulkScope.value === 'all'
        ? { ...currentFilters(), year, all_filtered: true }
        : { year, product_ids: selected.value };
    router.post('/admin/products/manufacture-year', payload, {
        preserveScroll: true,
        onFinish: () => { bulkSaving.value = false; },
    });
}
const showYearImport = ref(false);
const yearFile = ref(null);
const yearImporting = ref(false);
function onYearFileChange(e) { yearFile.value = e.target.files[0]; }
function submitYearImport() {
    if (!yearFile.value) return;
    yearImporting.value = true;
    router.post('/admin/products/manufacture-year/import', { file: yearFile.value }, {
        forceFormData: true,
        onFinish: () => { yearImporting.value = false; showYearImport.value = false; yearFile.value = null; },
    });
}
function yearTemplateUrl() {
    return '/admin/products/manufacture-year/template?' + new URLSearchParams(currentFilters()).toString();
}

function exportUrl() {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (categoryId.value) params.set('category_id', categoryId.value);
    if (brandId.value) params.set('brand_id', brandId.value);
    return '/admin/products-export?' + params.toString();
}
</script>
<template>
<AdminLayout title="Sản phẩm">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Link href="/admin/products/create" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">+ Thêm sản phẩm</Link>
                <a :href="exportUrl()" class="px-3 py-2 bg-emerald-600/80 text-white rounded-lg hover:bg-emerald-600 text-sm font-medium inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Xuất Excel
                </a>
                <button @click="showImport = true" class="px-3 py-2 bg-amber-600/80 text-white rounded-lg hover:bg-amber-600 text-sm font-medium inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Nhập Excel
                </button>
                <button @click="showYearImport = true" class="px-3 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-600 text-sm font-medium">Năm sản xuất (Excel)</button>
            </div>
            <div class="flex items-center gap-3">
                <input v-model="search" placeholder="Tìm tên, SKU..." class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-cyan-500/50">
                <select v-model="categoryId" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tất cả danh mục</option>
                    <option v-for="c in categories" :value="c.id">{{ c.name }}</option>
                </select>
                <select v-model="brandId" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tất cả thương hiệu</option>
                    <option v-for="b in brands" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="status" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active">Đang bán</option>
                    <option value="inactive">Ẩn</option>
                    <option value="out_of_stock">Hết hàng</option>
                </select>
                <select v-model="manufactureYear" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option value="">Mọi năm sản xuất</option>
                    <option value="missing">Chưa có năm sản xuất</option>
                    <option v-for="y in yearOptions" :key="y" :value="String(y)">Năm {{ y }}</option>
                </select>
            </div>
        </div>
    </div>
    <div class="bg-slate-900 rounded-lg border border-slate-800/60 p-3 mb-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-slate-300 font-medium">Đặt năm sản xuất cho</span>
        <select v-model="bulkScope" class="border border-slate-700/50 rounded-lg px-3 py-1.5 text-sm">
            <option value="selected">{{ selected.length }} sản phẩm đã chọn</option>
            <option value="all">Toàn bộ {{ products.total }} sản phẩm theo bộ lọc</option>
        </select>
        <select v-model="bulkYear" class="border border-slate-700/50 rounded-lg px-3 py-1.5 text-sm">
            <option value="">— Xoá năm —</option>
            <option v-for="y in yearOptions" :key="y" :value="String(y)">{{ y }}</option>
        </select>
        <button @click="applyBulkYear" :disabled="bulkSaving || (bulkScope === 'selected' && !selected.length) || (bulkScope === 'all' && !products.total)"
            class="px-3 py-1.5 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 font-medium disabled:opacity-40">Áp dụng</button>
        <span class="text-xs text-slate-500">Mẹo: lọc theo danh mục và "Chưa có năm sản xuất" rồi áp dụng cho toàn bộ kết quả lọc.</span>
    </div>
    <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800/40">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 w-8"><input type="checkbox" v-model="allOnPage" aria-label="Chọn tất cả trên trang"></th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">SKU</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Danh mục</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Năm SX</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Giá</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tồn kho</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="p in products.data" :key="p.id" class="hover:bg-slate-800/40">
                    <td class="px-4 py-3"><input type="checkbox" :value="p.id" v-model="selected" :aria-label="`Chọn ${p.name}`"></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-slate-800/60 rounded flex-shrink-0 overflow-hidden">
                                <img v-if="p.images?.[0]" :src="p.images[0].url" class="w-full h-full object-cover">
                            </div>
                            <span class="text-sm font-medium text-slate-200">{{ p.name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ p.sku }}</td>
                    <td class="px-4 py-3 text-sm text-slate-400">{{ p.category?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm" :class="p.manufacture_year ? 'text-slate-300' : 'text-amber-400'">{{ p.manufacture_year || 'Chưa có' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span v-if="p.sale_price" class="text-red-600 font-medium">{{ formatPrice(p.sale_price) }}</span>
                        <span :class="p.sale_price ? 'line-through text-slate-500 text-xs ml-1' : 'text-slate-200'">{{ formatPrice(p.price) }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm" :class="p.quantity < 5 ? 'text-red-600 font-medium' : 'text-slate-400'">{{ p.quantity }}</td>
                    <td class="px-4 py-3"><span :class="statusColor[p.status]" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusMap[p.status] }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a :href="`/${p.category?.slug || 'san-pham'}/${p.slug}`" target="_blank" class="inline-flex items-center text-slate-400 hover:text-cyan-400 transition-colors" title="Xem trên website">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                        <Link :href="`/admin/products/${p.id}/edit`" class="text-cyan-500 hover:text-cyan-300 text-sm">Sua</Link>
                        <button @click="destroy(p.id)" class="text-red-400 hover:text-red-300 text-sm">Xoa</button>
                    </td>
                </tr>
                <tr v-if="!products.data?.length"><td colspan="9" class="px-4 py-8 text-center text-slate-500">Chưa có sản phẩm nào</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="products.last_page > 1" class="mt-4 flex justify-end gap-1">
        <button v-for="link in products.links" :key="link.label" @click="link.url && router.get(link.url, {}, {preserveState:true})"
            :disabled="!link.url" :class="link.active ? 'bg-cyan-600 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800/40'"
            class="px-3 py-1.5 text-sm border border-slate-700/50 rounded disabled:opacity-40" v-html="link.label"/>
    </div>
    <!-- Import Modal -->
    <Teleport to="body">
        <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="showImport = false"></div>
            <div class="relative bg-slate-900 rounded-xl border border-slate-700/50 p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-lg font-semibold text-slate-200 mb-4">Nhập sản phẩm từ CSV</h3>
                <p class="text-sm text-slate-400 mb-4">File CSV cần có header: ID, Tên sản phẩm, SKU, Slug, Danh mục, Thương hiệu, Giá gốc, Giá sale, Tồn kho, Trạng thái, ...</p>
                <p class="text-xs text-slate-500 mb-4">Tip: Xuất Excel trước để lấy mẫu file, sửa rồi import lại.</p>
                <input type="file" accept=".csv,.txt" @change="onFileChange" class="block w-full text-sm text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-cyan-600 file:text-white hover:file:bg-cyan-700 mb-4">
                <div class="flex justify-end gap-2">
                    <button @click="showImport = false" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Hủy</button>
                    <button @click="submitImport" :disabled="!importFile || importing" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-medium hover:bg-cyan-700 disabled:opacity-50">
                        {{ importing ? 'Đang import...' : 'Import' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
    <Teleport to="body">
        <div v-if="showYearImport" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/60" @click="showYearImport = false"></div>
            <div class="relative bg-slate-900 rounded-xl border border-slate-700/50 p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-lg font-semibold text-slate-200 mb-3">Nhập năm sản xuất từ Excel</h3>
                <ol class="text-sm text-slate-400 mb-4 list-decimal pl-5 space-y-1">
                    <li><a :href="yearTemplateUrl()" class="text-cyan-400 hover:text-cyan-300">Tải file mẫu</a> (theo bộ lọc hiện tại, ví dụ chỉ các sản phẩm "Chưa có năm sản xuất").</li>
                    <li>Điền cột "Năm sản xuất" (4 chữ số). Ô để trống được bỏ qua.</li>
                    <li>Tải file lên. Sản phẩm được nhận theo cột ID, nếu không có thì theo SKU.</li>
                </ol>
                <input type="file" accept=".xlsx,.csv" @change="onYearFileChange" class="block w-full text-sm text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-cyan-600 file:text-white hover:file:bg-cyan-700 mb-4">
                <div class="flex justify-end gap-2">
                    <button @click="showYearImport = false" class="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Hủy</button>
                    <button @click="submitYearImport" :disabled="!yearFile || yearImporting" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-medium hover:bg-cyan-700 disabled:opacity-50">
                        {{ yearImporting ? 'Đang nhập...' : 'Nhập' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</AdminLayout>
</template>
