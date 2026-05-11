<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    media: Object,
    folders: Array,
    filters: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash);

// ---- State ----
const search = ref(props.filters?.search || '');
const currentFolder = ref(props.filters?.folder || '/');
const filterType = ref(props.filters?.type || '');
const selectedItems = ref([]);
const viewMode = ref('grid'); // grid | list
const showUploadModal = ref(false);
const showDetailPanel = ref(false);
const detailItem = ref(null);
const showNewFolderModal = ref(false);
const newFolderName = ref('');

// Upload state
const dragOver = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadFiles = ref([]);

// ---- Computed ----
const mediaItems = computed(() => props.media?.data || []);
const totalItems = computed(() => props.media?.total || 0);

// ---- Filtering ----
let searchTimeout = null;
watch(search, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters(), 400);
});

function applyFilters() {
    router.get('/admin/media', {
        search: search.value || undefined,
        folder: currentFolder.value !== '/' ? currentFolder.value : undefined,
        type: filterType.value || undefined,
    }, { preserveState: true, replace: true });
}

function setFolder(folder) {
    currentFolder.value = folder;
    applyFilters();
}

function setType(type) {
    filterType.value = type;
    applyFilters();
}

// ---- Selection ----
function toggleSelect(item) {
    const idx = selectedItems.value.findIndex(s => s.id === item.id);
    if (idx > -1) {
        selectedItems.value.splice(idx, 1);
    } else {
        selectedItems.value.push(item);
    }
}

function isSelected(item) {
    return selectedItems.value.some(s => s.id === item.id);
}

function selectAll() {
    if (selectedItems.value.length === mediaItems.value.length) {
        selectedItems.value = [];
    } else {
        selectedItems.value = [...mediaItems.value];
    }
}

// ---- Detail Panel ----
function openDetail(item) {
    detailItem.value = { ...item };
    showDetailPanel.value = true;
}

function saveDetail() {
    if (!detailItem.value) return;
    router.put(`/admin/media/${detailItem.value.id}`, {
        name: detailItem.value.name,
        alt: detailItem.value.alt,
        folder: detailItem.value.folder,
    }, {
        preserveState: true,
        onSuccess: () => {
            showDetailPanel.value = false;
        },
    });
}

// ---- Upload ----
function onDragOver(e) {
    e.preventDefault();
    dragOver.value = true;
}

function onDragLeave() {
    dragOver.value = false;
}

function onDrop(e) {
    e.preventDefault();
    dragOver.value = false;
    const files = Array.from(e.dataTransfer.files);
    if (files.length) {
        uploadFiles.value = files;
        showUploadModal.value = true;
    }
}

function triggerFileInput() {
    document.getElementById('media-file-input').click();
}

function onFileSelected(e) {
    const files = Array.from(e.target.files);
    if (files.length) {
        uploadFiles.value = files;
        showUploadModal.value = true;
    }
    e.target.value = '';
}

function removeUploadFile(idx) {
    uploadFiles.value.splice(idx, 1);
}

function submitUpload() {
    if (!uploadFiles.value.length) return;
    uploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    uploadFiles.value.forEach(f => formData.append('files[]', f));
    formData.append('folder', currentFolder.value);

    router.post('/admin/media/upload', formData, {
        forceFormData: true,
        onProgress: (progress) => {
            uploadProgress.value = progress.percentage;
        },
        onSuccess: () => {
            uploading.value = false;
            showUploadModal.value = false;
            uploadFiles.value = [];
            selectedItems.value = [];
        },
        onError: () => {
            uploading.value = false;
        },
    });
}

// ---- Delete ----
function deleteSelected() {
    if (!selectedItems.value.length) return;
    if (!confirm(`Xoá ${selectedItems.value.length} file đã chọn?`)) return;

    router.post('/admin/media/bulk-delete', {
        ids: selectedItems.value.map(i => i.id),
    }, {
        onSuccess: () => {
            selectedItems.value = [];
            showDetailPanel.value = false;
        },
    });
}

function deleteSingle(item) {
    if (!confirm(`Xoá file "${item.name}"?`)) return;
    router.delete(`/admin/media/${item.id}`, {
        onSuccess: () => {
            showDetailPanel.value = false;
            selectedItems.value = selectedItems.value.filter(s => s.id !== item.id);
        },
    });
}

// ---- Folder ----
function createFolder() {
    if (!newFolderName.value.trim()) return;
    router.post('/admin/media/folders', {
        name: newFolderName.value.trim(),
        parent: currentFolder.value,
    }, {
        onSuccess: () => {
            showNewFolderModal.value = false;
            newFolderName.value = '';
        },
    });
}

// ---- Copy URL ----
function copyUrl(url) {
    navigator.clipboard.writeText(url);
}

// ---- Pagination ----
function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveState: true });
}

// Format date
function formatDate(date) {
    return new Date(date).toLocaleDateString('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function isImage(item) {
    return item.mime_type?.startsWith('image/');
}
</script>

<template>
    <AdminLayout title="Media Library">
        <!-- Flash Message -->
        <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">
            {{ flash.success }}
        </div>

        <!-- Toolbar -->
        <div class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-4 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <!-- Upload button -->
                    <button @click="triggerFileInput" class="inline-flex items-center gap-2 px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        Upload
                    </button>
                    <input id="media-file-input" type="file" multiple accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" class="hidden" @change="onFileSelected">

                    <!-- New Folder -->
                    <button @click="showNewFolderModal = true" class="inline-flex items-center gap-2 px-3 py-2 border border-slate-700/50 text-slate-300 rounded-lg hover:bg-slate-800/40 transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                        Tạo thư mục
                    </button>

                    <!-- Bulk Delete -->
                    <button v-if="selectedItems.length" @click="deleteSelected"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition-colors text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Xoá {{ selectedItems.length }} file
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="search" type="text" placeholder="Tìm kiếm..."
                            class="pl-10 pr-4 py-2 border border-slate-700/50 rounded-lg text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50 w-56">
                    </div>

                    <!-- Type filter -->
                    <select v-model="filterType" @change="applyFilters()" class="border border-slate-700/50 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-cyan-500/50">
                        <option value="">Tất cả loại</option>
                        <option value="image">Hình ảnh</option>
                        <option value="video">Video</option>
                        <option value="document">Tài liệu</option>
                    </select>

                    <!-- View mode -->
                    <div class="flex border border-slate-700/50 rounded-lg overflow-hidden">
                        <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-cyan-500/10 text-cyan-500' : 'text-slate-400 hover:bg-slate-800/40'" class="p-2 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                        <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-cyan-500/10 text-cyan-500' : 'text-slate-400 hover:bg-slate-800/40'" class="p-2 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="flex gap-4">
            <!-- Folder Sidebar -->
            <div class="w-48 flex-shrink-0 bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-3 self-start">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Thư mục</h3>
                <button @click="setFolder('/')"
                    :class="currentFolder === '/' ? 'bg-cyan-500/10 text-cyan-500 font-medium' : 'text-slate-300 hover:bg-slate-800/40'"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Tất cả
                </button>
                <button v-for="f in folders" :key="f" @click="setFolder(f)"
                    :class="currentFolder === f ? 'bg-cyan-500/10 text-cyan-500 font-medium' : 'text-slate-300 hover:bg-slate-800/40'"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    {{ f === '/' ? 'Root' : f }}
                </button>
            </div>

            <!-- Media Grid / List -->
            <div class="flex-1">
                <!-- Drop Zone -->
                <div
                    @dragover="onDragOver"
                    @dragleave="onDragLeave"
                    @drop="onDrop"
                    :class="dragOver ? 'border-indigo-400 bg-cyan-500/10' : 'border-transparent'"
                    class="border-2 border-dashed rounded-xl transition-colors min-h-[400px]"
                >
                    <!-- Empty State -->
                    <div v-if="!mediaItems.length" class="flex flex-col items-center justify-center py-20 text-slate-500">
                        <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-lg font-medium mb-1">Chưa có file nào</p>
                        <p class="text-sm mb-4">Kéo thả file vào đây hoặc nhấn nút Upload</p>
                        <button @click="triggerFileInput" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium">Upload file</button>
                    </div>

                    <!-- Grid View -->
                    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-3 p-2">
                        <div v-for="item in mediaItems" :key="item.id"
                            @click.exact="toggleSelect(item)"
                            @dblclick="openDetail(item)"
                            :class="isSelected(item) ? 'ring-2 ring-cyan-500/50 bg-cyan-500/10' : 'hover:bg-slate-800/40'"
                            class="group relative rounded-lg border border-slate-800/60 overflow-hidden cursor-pointer transition-all"
                        >
                            <!-- Checkbox -->
                            <div class="absolute top-2 left-2 z-10">
                                <div :class="isSelected(item) ? 'bg-cyan-600 border-indigo-600' : 'bg-slate-900/80 border-slate-700/50 opacity-0 group-hover:opacity-100'"
                                    class="w-5 h-5 rounded border flex items-center justify-center transition-opacity">
                                    <svg v-if="isSelected(item)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            </div>

                            <!-- Thumbnail -->
                            <div class="aspect-square bg-slate-800/60 flex items-center justify-center overflow-hidden">
                                <img v-if="isImage(item)" :src="item.thumbnail_url" :alt="item.alt || item.name" class="w-full h-full object-cover">
                                <div v-else class="text-center p-2">
                                    <svg class="w-10 h-10 text-slate-500 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span class="text-[10px] text-slate-500 uppercase font-medium">{{ item.file_name.split('.').pop() }}</span>
                                </div>
                            </div>

                            <!-- Name -->
                            <div class="px-2 py-1.5">
                                <p class="text-xs text-slate-300 truncate font-medium">{{ item.name }}</p>
                                <p class="text-[10px] text-slate-500">{{ item.human_size }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- List View -->
                    <div v-else class="bg-slate-900 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-slate-800/40">
                            <thead class="bg-slate-800/40">
                                <tr>
                                    <th class="w-10 px-4 py-3">
                                        <input type="checkbox" @change="selectAll" :checked="selectedItems.length === mediaItems.length && mediaItems.length > 0" class="rounded border-slate-700/50 text-cyan-500 focus:ring-cyan-500/50">
                                    </th>
                                    <th class="w-12"></th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tên</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Loại</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Kích thước</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Kích cỡ</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Ngày upload</th>
                                    <th class="w-20"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/40">
                                <tr v-for="item in mediaItems" :key="item.id"
                                    @click="toggleSelect(item)"
                                    :class="isSelected(item) ? 'bg-cyan-500/10' : 'hover:bg-slate-800/40'"
                                    class="cursor-pointer transition-colors">
                                    <td class="px-4 py-2">
                                        <input type="checkbox" :checked="isSelected(item)" @click.stop class="rounded border-slate-700/50 text-cyan-500 focus:ring-cyan-500/50">
                                    </td>
                                    <td class="py-2">
                                        <div class="w-10 h-10 rounded bg-slate-800/60 overflow-hidden flex items-center justify-center">
                                            <img v-if="isImage(item)" :src="item.thumbnail_url" class="w-full h-full object-cover">
                                            <svg v-else class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-slate-200 font-medium">{{ item.name }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-400 uppercase">{{ item.file_name.split('.').pop() }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-400">{{ item.human_size }}</td>
                                    <td class="px-4 py-2 text-sm text-slate-400">
                                        <span v-if="item.width">{{ item.width }}×{{ item.height }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-slate-400">{{ formatDate(item.created_at) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <button @click.stop="openDetail(item)" class="text-cyan-500 hover:text-indigo-800 text-sm font-medium">Chi tiết</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="media?.last_page > 1" class="mt-4 flex items-center justify-between">
                    <p class="text-sm text-slate-400">{{ totalItems }} file tổng cộng</p>
                    <div class="flex gap-1">
                        <button v-for="link in media.links" :key="link.label"
                            @click="goToPage(link.url)"
                            :disabled="!link.url"
                            :class="link.active ? 'bg-cyan-600 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800/40'"
                            class="px-3 py-1.5 text-sm border border-slate-700/50 rounded transition-colors disabled:opacity-40"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== UPLOAD MODAL ====== -->
        <Teleport to="body">
            <div v-if="showUploadModal" class="fixed inset-0 z-[60] flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showUploadModal = false"></div>
                <div class="relative bg-slate-900 rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6">
                    <h3 class="text-lg font-semibold text-slate-200 mb-4">Upload file</h3>

                    <!-- File list -->
                    <div class="max-h-60 overflow-y-auto space-y-2 mb-4">
                        <div v-for="(file, fi) in uploadFiles" :key="fi"
                            class="flex items-center justify-between p-2 bg-slate-800/40 rounded-lg">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 bg-slate-700 rounded flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    <img v-if="file.type.startsWith('image/')" :src="URL.createObjectURL(file)" class="w-full h-full object-cover">
                                    <svg v-else class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm text-slate-200 truncate">{{ file.name }}</p>
                                    <p class="text-xs text-slate-500">{{ (file.size / 1024).toFixed(1) }} KB</p>
                                </div>
                            </div>
                            <button @click="removeUploadFile(fi)" class="text-slate-500 hover:text-red-400 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Add more -->
                    <button @click="triggerFileInput" class="w-full border-2 border-dashed border-slate-700/50 rounded-lg p-3 text-sm text-slate-400 hover:border-indigo-400 hover:text-indigo-500 transition-colors mb-4">
                        + Thêm file
                    </button>

                    <!-- Progress -->
                    <div v-if="uploading" class="mb-4">
                        <div class="h-2 bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-cyan-600 rounded-full transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 text-center">{{ uploadProgress }}%</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <button @click="showUploadModal = false; uploadFiles = []" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg transition-colors">Huỷ</button>
                        <button @click="submitUpload" :disabled="uploading || !uploadFiles.length"
                            class="px-4 py-2 text-sm bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 disabled:opacity-50 transition-colors font-medium">
                            {{ uploading ? 'Đang upload...' : `Upload ${uploadFiles.length} file` }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ====== DETAIL PANEL (Slide-over) ====== -->
        <Teleport to="body">
            <div v-if="showDetailPanel && detailItem" class="fixed inset-0 z-[60] flex justify-end">
                <div class="absolute inset-0 bg-black/30" @click="showDetailPanel = false"></div>
                <div class="relative bg-slate-900 w-full max-w-md shadow-2xl overflow-y-auto">
                    <div class="sticky top-0 bg-slate-900 border-b border-slate-800/60 px-6 py-4 flex items-center justify-between z-10">
                        <h3 class="text-lg font-semibold text-slate-200">Chi tiết file</h3>
                        <button @click="showDetailPanel = false" class="text-slate-500 hover:text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Preview -->
                        <div class="bg-slate-800/60 rounded-xl overflow-hidden flex items-center justify-center min-h-[200px]">
                            <img v-if="isImage(detailItem)" :src="detailItem.url" :alt="detailItem.alt" class="max-w-full max-h-80 object-contain">
                            <div v-else class="text-center p-8">
                                <svg class="w-16 h-16 text-slate-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-sm text-slate-400">{{ detailItem.file_name }}</p>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between py-1.5 border-b border-slate-800/40">
                                <span class="text-slate-400">File gốc</span>
                                <span class="text-slate-200 font-medium">{{ detailItem.file_name }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-slate-800/40">
                                <span class="text-slate-400">Loại</span>
                                <span class="text-slate-200">{{ detailItem.mime_type }}</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-slate-800/40">
                                <span class="text-slate-400">Kích thước</span>
                                <span class="text-slate-200">{{ detailItem.human_size }}</span>
                            </div>
                            <div v-if="detailItem.width" class="flex justify-between py-1.5 border-b border-slate-800/40">
                                <span class="text-slate-400">Kích cỡ ảnh</span>
                                <span class="text-slate-200">{{ detailItem.width }} × {{ detailItem.height }} px</span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-slate-800/40">
                                <span class="text-slate-400">Ngày upload</span>
                                <span class="text-slate-200">{{ formatDate(detailItem.created_at) }}</span>
                            </div>
                        </div>

                        <!-- URL Copy -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">URL</label>
                            <div class="flex gap-2">
                                <input :value="detailItem.url" readonly class="flex-1 text-sm bg-slate-800/40 border border-slate-700/50 rounded-lg px-3 py-2 text-slate-400">
                                <button @click="copyUrl(detailItem.url)" class="px-3 py-2 bg-slate-800/60 text-slate-400 rounded-lg hover:bg-slate-700 transition-colors text-sm" title="Copy URL">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Editable fields -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Tên hiển thị</label>
                            <input v-model="detailItem.name" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1">Alt text</label>
                            <input v-model="detailItem.alt" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50" placeholder="Mô tả hình ảnh">
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button @click="saveDetail" class="flex-1 px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm font-medium transition-colors">Lưu thay đổi</button>
                            <button @click="deleteSingle(detailItem)" class="px-4 py-2 text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 text-sm font-medium transition-colors">Xoá</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ====== NEW FOLDER MODAL ====== -->
        <Teleport to="body">
            <div v-if="showNewFolderModal" class="fixed inset-0 z-[60] flex items-center justify-center">
                <div class="absolute inset-0 bg-black/50" @click="showNewFolderModal = false"></div>
                <div class="relative bg-slate-900 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6">
                    <h3 class="text-lg font-semibold text-slate-200 mb-4">Tạo thư mục mới</h3>
                    <input v-model="newFolderName" @keydown.enter="createFolder" placeholder="Tên thư mục (a-z, 0-9, -, _)"
                        class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500/50 mb-4">
                    <div class="flex justify-end gap-3">
                        <button @click="showNewFolderModal = false" class="px-4 py-2 text-sm text-slate-300 hover:bg-slate-800/60 rounded-lg transition-colors">Huỷ</button>
                        <button @click="createFolder" :disabled="!newFolderName.trim()" class="px-4 py-2 text-sm bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 disabled:opacity-50 transition-colors font-medium">Tạo</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
